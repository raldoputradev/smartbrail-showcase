<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MesinIot;
use App\Models\User;
use App\Support\FingerIdAllocator;
use App\Support\FingerprintSyncLogService;
use App\Support\FingerprintSyncPolicy;
use App\Support\FingerprintSyncService;
use App\Support\FingerprintTemplateCodec;
use App\Support\MahasiswaShiftResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MesinFingerprintController extends Controller
{
    public const SENSOR_SLOT_MAX = FingerIdAllocator::SLOT_MAX;

    /** @deprecated Legacy flag — gunakan blob template 512 byte */
    public const ENROLLED_PLACEHOLDER = FingerprintTemplateCodec::LEGACY_PLACEHOLDER;

    public function resolve(Request $request): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:80'],
        ]);

        $user = $this->findMahasiswaByFingerIdQuery($validated['q']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'finger_id tidak ditemukan. Mahasiswa harus registrasi & verifikasi OTP dulu (finger_id otomatis).',
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun mahasiswa belum aktif.',
            ], 403);
        }

        $mesin->touchHeartbeat();

        return response()->json([
            'success' => true,
            'message' => 'Data mahasiswa ditemukan.',
            'data' => $this->serializeMahasiswaRow($user),
        ]);
    }

    public function mahasiswa(Request $request): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);
        $limit = $validated['limit'] ?? 100;

        $rows = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->orderBy('finger_id')
            ->limit($limit)
            ->get()
            ->map(fn (User $u) => $this->serializeMahasiswaRow($u));

        $mesin->touchHeartbeat();

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'data' => $rows,
        ]);
    }

    /**
     * Konfirmasi enroll + simpan template blob 512 byte (backup DB + sync ke mesin lain).
     */
    public function enrollConfirm(Request $request): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        if ($mesin->tipe === 'loker') {
            return response()->json([
                'success' => false,
                'message' => 'Enroll hanya untuk mesin absensi.',
                'code' => 'ENROLL_ABSENSI_ONLY',
            ], 403);
        }

        $validated = $request->validate([
            'finger_id' => ['required', 'integer', 'min:1', 'max:' . self::SENSOR_SLOT_MAX],
            'id_mesin' => ['nullable', 'string', 'max:100'],
            'template_base64' => ['nullable', 'string'],
            'template' => ['nullable', 'string'],
            'template_hex' => ['nullable', 'string', 'max:1100'],
        ]);

        if (!empty($validated['id_mesin']) && $mesin->id_mesin !== $validated['id_mesin']) {
            return response()->json([
                'success' => false,
                'message' => 'id_mesin tidak cocok dengan mesin autentikasi.',
            ], 422);
        }

        $slot = (int) $validated['finger_id'];

        try {
            $binary = FingerprintTemplateCodec::binaryFromRequest($validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'TEMPLATE_INVALID',
            ], 422);
        }

        if ($binary === null) {
            return response()->json([
                'success' => false,
                'message' => 'Template sidik jari wajib (template_base64 atau template_hex, 512 byte).',
                'code' => 'TEMPLATE_REQUIRED',
            ], 422);
        }

        $user = User::query()
            ->where('role', 'mahasiswa')
            ->where('finger_id', $slot)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "Tidak ada mahasiswa dengan finger_id #{$slot}. Cek registrasi / admin fingerprint.",
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun mahasiswa belum aktif.',
            ], 403);
        }

        if (FingerprintTemplateCodec::hasValidTemplate($user->finger_template)) {
            return response()->json([
                'success' => false,
                'message' => "Mahasiswa {$user->nama_lengkap} sudah enroll. Hapus dulu jika perlu daftar ulang.",
            ], 409);
        }

        $user->finger_template = FingerprintTemplateCodec::encodeForStorage($binary);
        MahasiswaShiftResolver::ensure($user, null, true);
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');
        $shift = $user->shift;

        $mesin->touchHeartbeat();

        FingerprintSyncLogService::record(
            $mesin,
            'enroll_upload',
            $request,
            true,
            1,
            [$user->finger_id],
            null,
            "Enroll {$user->nama_lengkap} · blob tersimpan",
        );

        return response()->json([
            'success' => true,
            'message' => 'Enroll dikonfirmasi — template tersimpan di server (siap sync ke mesin lain).',
            'data' => [
                'user_id' => $user->id,
                'finger_id' => $user->finger_id,
                'shift' => $shift,
                'nitk_nidn' => $user->nitk_nidn,
                'nama_lengkap' => $user->nama_lengkap,
                'fingerprint_status' => 'Sudah Enroll',
                'template_size' => FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH,
                'template_crc' => FingerprintTemplateCodec::crc32Hex($user->finger_template),
                'waktu' => now()->toDateTimeString(),
                'id_mesin' => $mesin->id_mesin,
            ],
        ]);
    }

    /**
     * Pull template blob untuk auto-sync sensor (boot + periodik).
     * Query: id_mesin (wajib di body/query), since (ISO8601 opsional), limit (opsional).
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        $validated = $request->validate([
            'id_mesin' => ['required', 'string', 'max:100'],
            'since' => ['nullable', 'string', 'max:40'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
            'after_finger_id' => ['nullable', 'integer', 'min:0', 'max:' . self::SENSOR_SLOT_MAX],
        ]);

        if ($mesin->id_mesin !== $validated['id_mesin']) {
            return response()->json([
                'success' => false,
                'message' => 'id_mesin tidak cocok dengan mesin autentikasi.',
            ], 422);
        }

        $mesin->touchHeartbeat();

        if (! FingerprintSyncPolicy::allowsDistribution()) {
            $statusPayload = FingerprintSyncService::buildStatusPayload($mesin);

            return response()->json([
                'success' => true,
                'message' => 'Sync template dijeda (mode manual). Admin dapat memicu distribusi dari panel Fingerprint.',
                'data' => [
                    'id_mesin' => $mesin->id_mesin,
                    'nama_mesin' => $mesin->nama_mesin,
                    'template_bytes' => FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH,
                    'sync_since' => $validated['since'] ?? null,
                    'after_finger_id' => $validated['after_finger_id'] ?? null,
                    'sync_token' => $statusPayload['sync_token'] ?? null,
                    'count' => 0,
                    'fingerprints' => [],
                    'sync_paused' => true,
                ],
            ]);
        }

        $payload = FingerprintSyncService::buildSyncPayload(
            $mesin,
            $validated['since'] ?? null,
            $validated['limit'] ?? null,
            isset($validated['after_finger_id']) ? (int) $validated['after_finger_id'] : null
        );

        if (FingerprintSyncLogService::shouldRecordPull($mesin, $payload)) {
            FingerprintSyncLogService::record(
                $mesin,
                'sync_pull',
                $request,
                true,
                (int) ($payload['count'] ?? 0),
                FingerprintSyncLogService::fingerIdsFromPayload($payload),
                $payload['sync_token'] ?? null,
                'Batch template dikirim ke mesin',
                $validated['since'] ?? null,
                isset($validated['after_finger_id']) ? (int) $validated['after_finger_id'] : null,
                isset($validated['limit']) ? (int) $validated['limit'] : null,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Data sync template sidik jari.',
            'data' => $payload,
        ]);
    }

    /**
     * Status ringan untuk delta sync (tanpa blob).
     */
    public function syncStatus(Request $request): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        $validated = $request->validate([
            'id_mesin' => ['required', 'string', 'max:100'],
        ]);

        if ($mesin->id_mesin !== $validated['id_mesin']) {
            return response()->json([
                'success' => false,
                'message' => 'id_mesin tidak cocok dengan mesin autentikasi.',
            ], 422);
        }

        $mesin->touchHeartbeat();

        $statusPayload = FingerprintSyncService::buildStatusPayload($mesin);

        FingerprintSyncLogService::record(
            $mesin,
            'sync_status',
            $request,
            true,
            (int) ($statusPayload['count'] ?? 0),
            FingerprintSyncLogService::fingerIdsFromPayload($statusPayload),
            $statusPayload['sync_token'] ?? null,
            'Ringkasan delta tanpa blob',
        );

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan sync template.',
            'data' => $statusPayload,
        ]);
    }

    public function deleteEnroll(Request $request, int $finger_id): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        if ($finger_id < 1 || $finger_id > self::SENSOR_SLOT_MAX) {
            return response()->json([
                'success' => false,
                'message' => 'finger_id di luar rentang slot sensor.',
            ], 422);
        }

        $user = User::query()
            ->where('role', 'mahasiswa')
            ->where('finger_id', $finger_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "Tidak ada mahasiswa dengan finger_id #{$finger_id}.",
            ], 404);
        }

        if (empty($user->finger_template)) {
            return response()->json([
                'success' => false,
                'message' => "Mahasiswa {$user->nama_lengkap} belum enroll di sistem.",
                'code' => 'BELUM_ENROLL',
            ], 409);
        }

        $user->finger_template = null;
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');

        $mesin->touchHeartbeat();

        return response()->json([
            'success' => true,
            'message' => "Enroll {$user->nama_lengkap} (slot #{$finger_id}) dibatalkan di sistem.",
            'data' => [
                'finger_id' => $finger_id,
                'nama_lengkap' => $user->nama_lengkap,
                'fingerprint_status' => 'Belum Enroll',
            ],
        ]);
    }

    private function findMahasiswaByFingerIdQuery(string $q): ?User
    {
        $q = trim($q);
        if ($q === '' || !ctype_digit($q)) {
            return null;
        }

        $slot = (int) $q;
        if ($slot < 1 || $slot > self::SENSOR_SLOT_MAX) {
            return null;
        }

        $user = User::query()
            ->where('role', 'mahasiswa')
            ->where('finger_id', $slot)
            ->first();

        return $user ? FingerIdAllocator::ensureForMahasiswa($user) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMahasiswaRow(User $user): array
    {
        $hasTemplate = FingerprintTemplateCodec::hasValidTemplate($user->finger_template);
        $status = match (true) {
            $hasTemplate => 'Sudah Enroll',
            !empty($user->finger_id) => 'Belum Enroll',
            default => 'Belum Assign',
        };

        return [
            'id' => $user->id,
            'finger_id' => $user->finger_id,
            'nitk_nidn' => $user->nitk_nidn,
            'nama_lengkap' => $user->nama_lengkap,
            'kelas' => $user->kelas,
            'tim' => $user->tim,
            'shift' => $user->shift,
            'status' => $status,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
