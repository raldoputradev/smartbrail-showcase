<?php

namespace App\Support;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiKalenderLibur;
use App\Models\AbsensiSesiSetting;
use App\Models\AbsensiTidakLengkap;
use App\Models\LogAbsensi;
use App\Models\User;
use App\Support\AbsensiKebijakan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AbsensiTapEvaluator
{
    /**
     * @return array{ok: bool, http: int, message: string, code: string, data?: array<string, mixed>}
     */
    public function evaluate(User $user, string $idMesin, ?Carbon $at = null): array
    {
        if ($user->role !== 'mahasiswa' || !$user->is_active) {
            return $this->fail('Akun tidak aktif untuk absensi.', 'AKUN_TIDAK_AKTIF', 403);
        }

        if (! FingerprintTemplateCodec::hasValidTemplate($user->finger_template)) {
            return $this->fail(
                'Sidik jari belum enroll. Scan enroll di mesin terlebih dahulu (menu e).',
                'BELUM_ENROLL',
                403
            );
        }

        $shift = MahasiswaShiftResolver::ensure($user, $at);
        if (!in_array($shift, ['pagi', 'malam'], true)) {
            return $this->fail('Shift absensi belum diatur. Isi shift (pagi/malam) di profil mahasiswa atau tim PBL.', 'SHIFT_BELUM_DIATUR', 422);
        }

        $user->refresh();
        $setting = AbsensiSesiSetting::forShift($shift);
        if (!$setting) {
            return $this->fail('Sesi absensi sedang nonaktif.', 'SESI_NONAKTIF', 403);
        }

        $now = $setting->timezoneCarbon($at);
        $tanggal = $now->toDateString();

        if (AbsensiKalenderLibur::isLibur($tanggal)) {
            return $this->fail('Hari libur — mesin tidak beroperasi.', 'HARI_LIBUR', 403);
        }

        // Hari non-wajib: tap sukarela tetap dicatat (muncul di rekap sebagai hadir), tanpa alfa.

        $harian = AbsensiHarian::query()
            ->where('user_id', $user->id)
            ->whereDate('tanggal', $tanggal)
            ->where('sesi', $shift)
            ->first();

        if ($harian?->status === 'libur') {
            return $this->fail('Hari libur — absensi tidak dihitung.', 'HARI_LIBUR', 403);
        }

        if ($harian && ($harian->status === 'lengkap' || ($harian->masuk_log_id && $harian->pulang_log_id))) {
            return $this->fail('Absensi sesi ini sudah lengkap.', 'SUDAH_SELESAI', 409);
        }

        $hasMasuk = $harian?->masuk_log_id !== null;
        $jenis = $hasMasuk ? 'pulang' : 'masuk';

        $windowCheck = $this->assertWithinSessionWindow($setting, $now, $jenis);
        if ($windowCheck !== null) {
            return $windowCheck;
        }

        if ($jenis === 'pulang' && !$hasMasuk) {
            return $this->fail('Belum absen masuk hari ini.', 'BELUM_MASUK', 403);
        }

        if (!$harian) {
            $harian = AbsensiHarian::query()->create([
                'user_id' => $user->id,
                'tanggal' => $tanggal,
                'sesi' => $shift,
                'status' => 'belum',
            ]);
        }

        if ($jenis === 'masuk') {
            return $this->recordMasuk($user, $idMesin, $setting, $now, $harian);
        }

        return $this->recordPulang($user, $idMesin, $setting, $now, $harian);
    }

    /**
     * @return array{ok: bool, http: int, message: string, code: string}|null
     */
    private function assertWithinSessionWindow(AbsensiSesiSetting $setting, Carbon $now, string $jenis): ?array
    {
        $jamMasuk = $setting->jamMasukOn($now);
        $jamPulang = $setting->jamPulangOn($now);

        if ($jenis === 'masuk') {
            $earliest = $this->earliestMasukTap($setting, $now);
            if ($now->lessThan($earliest)) {
                return $this->fail(
                    'Belum waktunya absen masuk (mulai ' . $earliest->format('H:i') . ').',
                    'BELUM_WAKTU_MASUK',
                    403
                );
            }
            if ($now->greaterThanOrEqualTo($jamPulang)) {
                return $this->fail(
                    'Sesi sudah lewat jam pulang (' . $jamPulang->format('H:i') . '). Tap masuk tidak dicatat.',
                    'SESI_DITUTUP',
                    403
                );
            }
        }

        if ($jenis === 'pulang' && $now->lessThan($jamPulang)) {
            return $this->fail(
                'Belum waktunya absen pulang (mulai ' . $jamPulang->format('H:i') . ').',
                'BELUM_WAKTU_PULANG',
                403
            );
        }

        return null;
    }

    private function earliestMasukTap(AbsensiSesiSetting $setting, Carbon $now): Carbon
    {
        if ($setting->shift === 'pagi' && $setting->jam_mulai_tap_masuk) {
            return $setting->jamMulaiTapOn($now);
        }

        return $setting->jamMasukOn($now)->copy()->subMinutes((int) $setting->toleransi_masuk_menit);
    }

    /**
     * @return array{ok: bool, http: int, message: string, code: string, data?: array<string, mixed>}
     */
    private function recordMasuk(User $user, string $idMesin, AbsensiSesiSetting $setting, Carbon $now, AbsensiHarian $harian): array
    {
        $menitTerlambat = AbsensiLatenessCalculator::menitTerlambatMasuk($setting, $now);
        $slotLab = AbsensiLabSlotResolver::resolveForShift($setting, $now);

        return DB::transaction(function () use ($user, $idMesin, $setting, $now, $harian, $menitTerlambat, $slotLab) {
            $log = LogAbsensi::query()->create([
                'user_id' => $user->id,
                'id_mesin' => $idMesin,
                'sesi' => $setting->shift,
                'jenis' => 'masuk',
                'menit_terlambat' => $menitTerlambat,
                'slot_lab' => $slotLab,
                'waktu_tap' => $now->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            ]);

            $harian->update([
                'status' => 'masuk',
                'masuk_log_id' => $log->id,
                'menit_terlambat_masuk' => $menitTerlambat,
                'slot_lab_masuk' => $slotLab,
            ]);

            $this->deactivateAlfaIfAny($user->id, $harian->tanggal->toDateString(), $setting->shift);

            $message = 'MASUK OK';
            if ($menitTerlambat > 0) {
                $message = "MASUK TERLAMBAT {$menitTerlambat}m";
            }
            if ($slotLab !== null) {
                $message .= " · S{$slotLab}";
            }

            return $this->ok($message, 'MASUK_OK', [
                'jenis' => 'masuk',
                'sesi' => $setting->shift,
                'menit_terlambat' => $menitTerlambat,
                'slot_lab' => $slotLab,
                'log_id' => $log->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nitk_nidn' => $user->nitk_nidn,
                'waktu_tap' => $now->toDateTimeString(),
            ]);
        });
    }

    /**
     * @return array{ok: bool, http: int, message: string, code: string, data?: array<string, mixed>}
     */
    private function recordPulang(User $user, string $idMesin, AbsensiSesiSetting $setting, Carbon $now, AbsensiHarian $harian): array
    {
        $pulangMetrics = AbsensiLatenessCalculator::pulangMetrics($setting, $now);
        $menitTerlambat = $pulangMetrics['menit_terlambat'];
        $pulangLembur = $pulangMetrics['pulang_lembur'];

        return DB::transaction(function () use ($user, $idMesin, $setting, $now, $harian, $menitTerlambat, $pulangLembur) {
            $log = LogAbsensi::query()->create([
                'user_id' => $user->id,
                'id_mesin' => $idMesin,
                'sesi' => $setting->shift,
                'jenis' => 'pulang',
                'menit_terlambat' => $menitTerlambat,
                'pulang_lembur' => $pulangLembur,
                'waktu_tap' => $now->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            ]);

            $harian->update([
                'status' => 'lengkap',
                'pulang_log_id' => $log->id,
                'menit_terlambat_pulang' => $menitTerlambat,
                'pulang_lembur' => $pulangLembur,
                'lengkap_at' => $now->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            ]);

            $this->resolveTidakLengkap($user->id, $harian->tanggal->toDateString(), $setting->shift, 'tanpa_pulang');
            $this->deactivateAlfaIfAny($user->id, $harian->tanggal->toDateString(), $setting->shift);

            if ($pulangLembur) {
                $message = 'PULANG LEMBUR';
                if ($menitTerlambat > 0) {
                    $message .= ' +' . $this->formatMenit($menitTerlambat);
                }
                $code = 'PULANG_LEMBUR';
            } elseif ($menitTerlambat > 0) {
                $message = 'PULANG TERLAMBAT ' . $this->formatMenit($menitTerlambat);
                $code = 'PULANG_OK';
            } else {
                $message = 'PULANG OK';
                $code = 'PULANG_OK';
            }

            return $this->ok($message, $code, [
                'jenis' => 'pulang',
                'sesi' => $setting->shift,
                'menit_terlambat' => $menitTerlambat,
                'pulang_lembur' => $pulangLembur,
                'status_harian' => 'lengkap',
                'log_id' => $log->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nitk_nidn' => $user->nitk_nidn,
                'waktu_tap' => $now->toDateTimeString(),
            ]);
        });
    }

    public function finalizeTidakLengkapForDate(string $tanggal, string $sesi): int
    {
        $count = 0;
        $rows = AbsensiHarian::query()
            ->whereDate('tanggal', $tanggal)
            ->where('sesi', $sesi)
            ->whereIn('status', ['masuk'])
            ->whereNull('pulang_log_id')
            ->get();

        foreach ($rows as $harian) {
            $harian->update(['status' => 'tidak_lengkap']);
            AbsensiTidakLengkap::query()->updateOrCreate(
                [
                    'user_id' => $harian->user_id,
                    'tanggal' => $tanggal,
                    'sesi' => $sesi,
                    'jenis' => 'tanpa_pulang',
                ],
                ['selesai' => false]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Alfa untuk mahasiswa tanpa tap sama sekali di hari wajib (tanpa menunggu grace).
     */
    public function assignAlfaForNoTapStudents(string $tanggal, string $sesi): int
    {
        if (!AbsensiKebijakan::isHariWajib($tanggal)) {
            return 0;
        }

        if (AbsensiKalenderLibur::isLibur($tanggal)) {
            return 0;
        }

        $setting = AbsensiSesiSetting::forShift($sesi);
        if (!$setting) {
            return 0;
        }

        $dayAnchor = Carbon::parse($tanggal, $setting->timezone ?: 'Asia/Jakarta');

        $students = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($students as $user) {
            $resolvedShift = MahasiswaShiftResolver::resolve($user, $dayAnchor);
            if ($resolvedShift !== $sesi) {
                continue;
            }

            $userId = $user->id;
            $hasTap = LogAbsensi::query()
                ->where('user_id', $userId)
                ->where('sesi', $sesi)
                ->whereDate('waktu_tap', $tanggal)
                ->exists();

            if ($hasTap) {
                continue;
            }

            AbsensiHarian::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'tanggal' => $tanggal,
                    'sesi' => $sesi,
                ],
                ['status' => 'alfa']
            );

            AbsensiAlfa::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'tanggal' => $tanggal,
                    'sesi' => $sesi,
                ],
                [
                    'sumber' => 'otomatis',
                    'aktif' => true,
                    'dibatalkan_at' => null,
                    'dibatalkan_oleh' => null,
                ]
            );

            $count++;
        }

        return $count;
    }

    /** Backfill / tutup sesi paksa: alfa (tanpa tap) + tidak lengkap (hanya masuk). */
    public function generateAlfaForDate(string $tanggal, string $sesi): int
    {
        $alfa = $this->assignAlfaForNoTapStudents($tanggal, $sesi);
        $this->finalizeTidakLengkapForDate($tanggal, $sesi);

        return $alfa;
    }

    private function deactivateAlfaIfAny(int $userId, string $tanggal, string $sesi): void
    {
        AbsensiAlfa::query()
            ->where('user_id', $userId)
            ->whereDate('tanggal', $tanggal)
            ->where('sesi', $sesi)
            ->where('aktif', true)
            ->update([
                'aktif' => false,
                'dibatalkan_at' => now(),
            ]);
    }

    private function resolveTidakLengkap(int $userId, string $tanggal, string $sesi, string $jenis): void
    {
        AbsensiTidakLengkap::query()
            ->where('user_id', $userId)
            ->whereDate('tanggal', $tanggal)
            ->where('sesi', $sesi)
            ->where('jenis', $jenis)
            ->update([
                'selesai' => true,
                'ditangani_at' => now(),
            ]);
    }

    private function formatMenit(int $menit): string
    {
        if ($menit < 60) {
            return "{$menit}m";
        }

        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;

        return $sisa > 0 ? "{$jam}j {$sisa}m" : "{$jam}j";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, http: int, message: string, code: string, data?: array<string, mixed>}
     */
    private function ok(string $message, string $code, array $data = []): array
    {
        return [
            'ok' => true,
            'http' => 200,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ];
    }

    /**
     * @return array{ok: bool, http: int, message: string, code: string}
     */
    private function fail(string $message, string $code, int $http): array
    {
        return [
            'ok' => false,
            'http' => $http,
            'message' => $message,
            'code' => $code,
        ];
    }
}
