<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MesinIot;
use App\Support\AksesRuanganService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Kontrak perangkat ruangan SMART BRAIL.
 * Ruangan SELALU diambil dari binding mesin (mesin_iot.ruangan_id) — room_id klien diabaikan.
 * 'check' = verifikasi izin; 'event' = kejadian (buka/tolak/telemetri) terpisah dari tap absensi.
 */
class RuanganAccessController extends Controller
{
    private function mesinTerikat(Request $request): ?MesinIot
    {
        $mesin = $request->attributes->get('mesin_iot');

        return ($mesin && $mesin->ruangan_id) ? $mesin : null;
    }

    private function unbound()
    {
        return response()->json([
            'ok' => false,
            'error' => 'mesin_tidak_terikat_ruangan',
            'message' => 'Perangkat ini belum diikat ke ruangan.',
        ], 403);
    }

    /** POST /api/mesin/ruangan/access/check {tipe:nilai identitas} */
    public function check(Request $request)
    {
        $mesin = $this->mesinTerikat($request);
        if (!$mesin) {
            return $this->unbound();
        }

        $data = $request->validate([
            'tipe' => 'required|in:fingerprint,rfid,pin',
            'nilai' => 'required|string|max:128',
            'waktu' => 'nullable|date',
        ]);
        $waktu = isset($data['waktu']) ? Carbon::parse($data['waktu']) : now();

        [$user, $petugas] = AksesRuanganService::resolveIdentitas($data['tipe'], $data['nilai']);

        if (!$user && !$petugas) {
            AksesRuanganService::catatEvent($mesin->id, $mesin->ruangan_id, 'tolak', [
                'hasil' => 'ditolak', 'meta' => ['alasan' => 'identitas_tidak_dikenal', 'tipe' => $data['tipe']],
            ]);

            return response()->json(['ok' => true, 'diizinkan' => false, 'alasan' => 'identitas_tidak_dikenal']);
        }

        $hasil = $user
            ? AksesRuanganService::cekIzin($user, $mesin->ruangan_id, $waktu)
            : AksesRuanganService::cekIzinPetugas($petugas, $mesin->ruangan_id, $waktu);

        AksesRuanganService::catatEvent($mesin->id, $mesin->ruangan_id, 'check', [
            'user_id' => $user?->id,
            'petugas_id' => $petugas?->id,
            'izin_id' => $hasil['izin']?->id,
            'hasil' => $hasil['diizinkan'] ? 'diizinkan' : 'ditolak',
            'meta' => ['alasan' => $hasil['alasan'], 'tipe' => $data['tipe']],
        ]);

        return response()->json([
            'ok' => true,
            'diizinkan' => $hasil['diizinkan'],
            'alasan' => $hasil['alasan'],
            'identitas' => $user ? 'user' : 'petugas',
            'izin_id' => $hasil['izin']?->id,
        ]);
    }

    /**
     * POST /api/mesin/ruangan/access/event — laporan kejadian dari perangkat.
     * 'buka' = bukti pintu dibuka; izin diberikan ≠ perintah dijalankan ≠ pintu terbuka.
     */
    public function event(Request $request)
    {
        $mesin = $this->mesinTerikat($request);
        if (!$mesin) {
            return $this->unbound();
        }

        $data = $request->validate([
            'jenis' => 'required|in:buka,tolak,telemetri',
            'user_id' => 'nullable|integer',
            'petugas_id' => 'nullable|integer',
            'izin_id' => 'nullable|integer',
            'hasil' => 'nullable|string|max:30',
            'meta' => 'nullable|array',
            'terjadi_pada' => 'nullable|date',
        ]);

        $event = AksesRuanganService::catatEvent($mesin->id, $mesin->ruangan_id, $data['jenis'], [
            'user_id' => $data['user_id'] ?? null,
            'petugas_id' => $data['petugas_id'] ?? null,
            'izin_id' => $data['izin_id'] ?? null,
            'hasil' => $data['hasil'] ?? null,
            'meta' => $data['meta'] ?? null,
            'terjadi_pada' => isset($data['terjadi_pada']) ? Carbon::parse($data['terjadi_pada']) : now(),
        ]);

        return response()->json(['ok' => true, 'event_id' => $event->id]);
    }

    /**
     * POST /api/mesin/ruangan/telemetry — snapshot kondisi ruangan dari edge.
     * Satu baris per ruangan (upsert); bukan histori. metrics bebas — kunci
     * yang dikenali UI: apd_ok, apd_total, listrik_watt, suhu_c, kelembapan_pct,
     * printer_state, printer_nozzle_c, printer_bed_c, printer_progress_pct,
     * printer_eta_s, solder_active, solder_temp_c.
     */
    public function telemetry(Request $request)
    {
        $mesin = $this->mesinTerikat($request);
        if (!$mesin) {
            return $this->unbound();
        }

        $data = $request->validate([
            'metrics' => 'required|array',
            'alerts' => 'nullable|array|max:20',
            'alerts.*' => 'string|max:60',
            'waktu' => 'nullable|date',
        ]);

        \App\Models\RuanganTelemetri::query()->updateOrCreate(
            ['ruangan_id' => (int) $mesin->ruangan_id],
            [
                'id_mesin' => $mesin->id_mesin,
                'metrics' => $data['metrics'],
                'alerts' => $data['alerts'] ?? null,
                'recorded_at' => isset($data['waktu']) ? Carbon::parse($data['waktu']) : now(),
            ],
        );

        try {
            broadcast(new \App\Events\RuanganTelemetriBaru((int) $mesin->ruangan_id));
        } catch (\Throwable) {
            // Broadcast opsional — panel tetap menyegarkan lewat polling.
        }

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/mesin/ruangan/access/acl — daftar izin untuk keputusan lokal.
     * Perangkat menyimpan entri ini supaya pintu tetap bisa memutuskan saat
     * server/WiFi terputus. PIN bcrypt (akses_pin, PIN petugas) tidak pernah
     * dikirim — verifikasi PIN tersebut tetap wajib lewat /access/check.
     * Entri: t=jenis, v=nilai kredensial, s/e=jendela ISO8601, rules=jadwal berulang.
     */
    public function acl(Request $request)
    {
        $mesin = $this->mesinTerikat($request);
        if (!$mesin) {
            return $this->unbound();
        }

        $ruanganId = (int) $mesin->ruangan_id;
        $now = now();
        $entries = [];

        // Staf (admin/laboran/dosen): akses bebas — tanpa jendela waktu.
        \App\Models\User::whereIn('role', ['admin', 'laboran', 'dosen'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('finger_id')->orWhereNotNull('rfid_uid');
            })
            ->get(['finger_id', 'rfid_uid'])
            ->each(function ($u) use (&$entries) {
                if ($u->finger_id) {
                    $entries[] = ['t' => 'finger', 'v' => (string) $u->finger_id, 'why' => 'staf'];
                }
                if ($u->rfid_uid) {
                    $entries[] = ['t' => 'rfid', 'v' => \Illuminate\Support\Str::upper($u->rfid_uid), 'why' => 'staf'];
                }
            });

        // Izin booking aktif/mendatang (7 hari ke depan) + PIN per-izin bila ada.
        \App\Models\RuanganIzin::where('ruangan_id', $ruanganId)
            ->where('status', \App\Models\RuanganIzin::STATUS_AKTIF)
            ->where('selesai', '>=', $now->copy()->subHour())
            ->where('mulai', '<=', $now->copy()->addDays(7))
            ->with('user:id,finger_id,rfid_uid')
            ->get()
            ->each(function ($z) use (&$entries) {
                $s = $z->mulai->toIso8601String();
                $e = $z->selesai->toIso8601String();
                if ($z->user?->finger_id) {
                    $entries[] = ['t' => 'finger', 'v' => (string) $z->user->finger_id, 's' => $s, 'e' => $e, 'why' => 'izin'];
                }
                if ($z->user?->rfid_uid) {
                    $entries[] = ['t' => 'rfid', 'v' => \Illuminate\Support\Str::upper($z->user->rfid_uid), 's' => $s, 'e' => $e, 'why' => 'izin'];
                }
                if ($pin = $z->aksesPin()) {
                    $entries[] = ['t' => 'pin', 'v' => $pin, 's' => $s, 'e' => $e, 'why' => 'izin'];
                }
            });

        // Petugas (CS): jadwal berulang — kredensial fingerprint/rfid saja.
        \App\Models\RuanganPetugas::where('status', 'aktif')->get()
            ->each(function ($p) use (&$entries, $ruanganId, $now) {
                if (!$p->dalamMasaBerlaku($now)) {
                    return;
                }
                $rules = [];
                foreach ((array) $p->jadwal as $j) {
                    if ((int) ($j['ruangan_id'] ?? 0) !== $ruanganId) {
                        continue;
                    }
                    $rules[] = [
                        'hari' => array_map('intval', (array) ($j['hari'] ?? [])),
                        'jm' => $j['jam_mulai'] ?? '00:00',
                        'js' => $j['jam_selesai'] ?? '23:59',
                    ];
                }
                if ($rules === []) {
                    return;
                }
                match ($p->tipe_kredensial) {
                    'fingerprint' => $entries[] = ['t' => 'finger', 'v' => (string) $p->kredensial_hash, 'why' => 'petugas', 'rules' => $rules],
                    'rfid' => $entries[] = ['t' => 'rfid_sha256', 'v' => (string) $p->kredensial_hash, 'why' => 'petugas', 'rules' => $rules],
                    default => null,
                };
            });

        return response()->json([
            'ok' => true,
            'ruangan_id' => $ruanganId,
            'generated_at' => $now->toIso8601String(),
            'ttl' => 300,
            'entries' => $entries,
        ]);
    }
}
