<?php

namespace App\Support;

use App\Models\RuanganAccessEvent;
use App\Models\RuanganIzin;
use App\Models\RuanganPetugas;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolusi identitas akses ruangan: fingerprint/RFID/PIN pribadi → User yang sama,
 * atau petugas non-portal → RuanganPetugas. Pemeriksaan izin identik.
 * PIN tim bersama tidak didukung — setiap kredensial mengarah ke satu identitas.
 */
class AksesRuanganService
{
    /**
     * @return array{0:?User,1:?RuanganPetugas}
     */
    public static function resolveIdentitas(string $tipe, string $nilai): array
    {
        if ($tipe === 'fingerprint') {
            $user = User::where('finger_id', (int) $nilai)->first();
            $petugas = RuanganPetugas::where('tipe_kredensial', 'fingerprint')
                ->get()->first(fn ($p) => hash_equals((string) $p->kredensial_hash, (string) $nilai));

            return [$user, $petugas];
        }
        if ($tipe === 'rfid') {
            $uid = Str::upper(trim($nilai));
            $user = User::where('rfid_uid', $uid)->first();
            $petugas = RuanganPetugas::where('tipe_kredensial', 'rfid')
                ->get()->first(fn ($p) => hash_equals((string) $p->kredensial_hash, hash('sha256', $uid)));

            return [$user, $petugas];
        }
        if ($tipe === 'pin') {
            // PIN pribadi: hash per user; bandingkan kandidat (jumlah kecil, lokal).
            $user = User::whereNotNull('akses_pin')->get()
                ->first(fn ($u) => Hash::check($nilai, (string) $u->akses_pin));
            $petugas = RuanganPetugas::where('tipe_kredensial', 'pin')
                ->get()->first(fn ($p) => Hash::check($nilai, (string) $p->kredensial_hash));

            // PIN per-izin mahasiswa (akses_pin_enc di ruangan_izin) → identitas pemilik izin.
            // cekIzin tetap diverifikasi ulang — PIN tidak pernah melewati keputusan izin.
            if (!$user) {
                $izinPin = RuanganIzin::where('status', RuanganIzin::STATUS_AKTIF)
                    ->whereNotNull('akses_pin_enc')
                    ->get(['id', 'user_id', 'akses_pin_enc'])
                    ->first(fn ($z) => $z->aksesPin() === trim($nilai));
                if ($izinPin) {
                    $user = User::find($izinPin->user_id);
                }
            }

            return [$user, $petugas];
        }

        return [null, null];
    }

    /**
     * Periksa izin pada waktu tertentu. Tidak otomatis mengizinkan saat device offline —
     * keputusan selalu dari data izin backend pada waktu kejadian.
     *
     * @return array{diizinkan:bool, izin:?RuanganIzin, alasan:string}
     */
    public static function cekIzin(User $user, int $ruanganId, Carbon $waktu): array
    {
        // Hak akses per role: admin/laboran/dosen bebas memakai ruangan (tetap tercatat
        // sebagai event akses); mahasiswa hanya lewat izin pengajuan yang disetujui.
        if (in_array($user->role, ['admin', 'laboran', 'dosen'], true)) {
            return ['diizinkan' => true, 'izin' => null, 'alasan' => 'akses_staf'];
        }

        // Tandai izin lewat sebagai kedaluwarsa (lazy, tanpa scheduler).
        RuanganIzin::where('status', RuanganIzin::STATUS_AKTIF)
            ->where('selesai', '<', $waktu)
            ->update(['status' => RuanganIzin::STATUS_KEDALUWARSA]);

        $izin = RuanganIzin::berlakuPada($waktu)
            ->where('user_id', $user->id)
            ->where('ruangan_id', $ruanganId)
            ->latest('selesai')
            ->first();

        if ($izin) {
            return ['diizinkan' => true, 'izin' => $izin, 'alasan' => 'izin_aktif'];
        }

        $ada = RuanganIzin::where('user_id', $user->id)->where('ruangan_id', $ruanganId)
            ->orderByDesc('selesai')->first();

        return [
            'diizinkan' => false,
            'izin' => null,
            'alasan' => $ada ? 'izin_'.$ada->status : 'tidak_ada_izin',
        ];
    }

    /** Petugas diizinkan bila jadwalnya mencakup ruangan+hari+jam dan masa berlaku aktif. */
    public static function cekIzinPetugas(RuanganPetugas $petugas, int $ruanganId, Carbon $waktu): array
    {
        if (!$petugas->dalamMasaBerlaku($waktu)) {
            return ['diizinkan' => false, 'izin' => null, 'alasan' => 'petugas_tidak_berlaku'];
        }
        foreach ((array) $petugas->jadwal as $j) {
            if ((int) ($j['ruangan_id'] ?? 0) !== $ruanganId) {
                continue;
            }
            $hari = array_map('intval', (array) ($j['hari'] ?? []));
            if ($hari !== [] && !in_array((int) $waktu->dayOfWeekIso, $hari, true)) {
                continue;
            }
            $jam = $waktu->format('H:i');
            if ($jam >= ($j['jam_mulai'] ?? '00:00') && $jam <= ($j['jam_selesai'] ?? '23:59')) {
                return ['diizinkan' => true, 'izin' => null, 'alasan' => 'jadwal_petugas'];
            }
        }

        return ['diizinkan' => false, 'izin' => null, 'alasan' => 'diluar_jadwal_petugas'];
    }

    public static function catatEvent(int $mesinId, int $ruanganId, string $jenis, array $data = []): RuanganAccessEvent
    {
        return RuanganAccessEvent::create([
            'mesin_iot_id' => $mesinId,
            'ruangan_id' => $ruanganId,           // selalu dari mesin terikat, bukan payload klien
            'user_id' => $data['user_id'] ?? null,
            'petugas_id' => $data['petugas_id'] ?? null,
            'izin_id' => $data['izin_id'] ?? null,
            'jenis' => $jenis,
            'hasil' => $data['hasil'] ?? null,
            'meta' => $data['meta'] ?? null,
            'terjadi_pada' => $data['terjadi_pada'] ?? now(),
        ]);
    }
}
