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
}
