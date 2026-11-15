<?php

namespace App\Support;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluasi satu tap fingerprint mahasiswa -> tentukan masuk/pulang, validasi urutan & jadwal efektif,
 * simpan log_absensi dan sinkronkan absensi_harian (kelengkapan vs ketepatan waktu).
 */
class AbsensiTapEvaluator
{
    /** Cooldown minimal antar tap agar jari nempel dua kali tidak langsung jadi "pulang". */
    public const COOLDOWN_DUPLIKAT_DETIK = 60;

    public function evaluate(User $user, string $idMesin, ?Carbon $at = null): array
    {
        if ($user->role !== 'mahasiswa') {
            return $this->fail('Hanya akun mahasiswa yang memiliki sesi absensi.', 'BUKAN_MAHASISWA', 422);
        }

        if (! $user->is_active || $user->approval_status !== 'approved') {
            return $this->fail('Akun mahasiswa belum aktif / belum disetujui.', 'AKUN_TIDAK_AKTIF', 403);
        }

        $shift = $user->shift ?: 'pagi';
        $baseSetting = AbsensiSesiSetting::forShift($shift);
        $tz = $baseSetting->timezone ?: 'Asia/Jakarta';
        $now = ($at ? $at->copy() : now())->timezone($tz);

        $setting = AbsensiEffectiveSchedule::effectiveSesiSetting($now, $shift);

        if (! $setting->aktif) {
            return $this->fail("Sesi {$shift} sedang dinonaktifkan oleh laboran.", 'SESI_NONAKTIF', 403);
        }

        [$jamMasuk, $jamPulang] = AbsensiLatenessCalculator::sessionDateTimes($now, $setting);
        $tanggalSesi = $jamMasuk->format('Y-m-d');

        return DB::transaction(function () use ($user, $idMesin, $shift, $setting, $now, $tanggalSesi, $jamMasuk, $jamPulang) {
            $harian = AbsensiHarian::query()
                ->where('user_id', $user->id)
                ->whereDate('tanggal', $tanggalSesi)
                ->where('sesi', $shift)
                ->lockForUpdate()
                ->first();

            // Penentuan otomatis jenis tap (masuk vs pulang) berdasarkan state harian.
            if (! $harian || ! $harian->masuk_log_id) {
                $jenis = 'masuk';
            } elseif (! $harian->pulang_log_id) {
                if (in_array($harian->status, ['izin', 'sakit', 'dispensasi'], true)) {
                    return $this->fail(
                        "Kehadiran tanggal {$tanggalSesi} sudah tercatat sebagai {$harian->status}.",
                        'STATUS_FINAL',
                        409
                    );
                }

                // Cek cooldown terhadap tap masuk supaya tap ganda cepat tidak langsung dianggap pulang.
                $masukLog = LogAbsensi::find($harian->masuk_log_id);
                if ($masukLog && $masukLog->waktu_tap) {
                    $selisih = abs($now->diffInSeconds($masukLog->waktu_tap));
                    if ($selisih < self::COOLDOWN_DUPLIKAT_DETIK) {
                        return $this->fail(
                            'Tap masuk baru saja tercatat. Tunggu sebentar sebelum tap lagi.',
                            'TAP_DUPLIKAT',
                            429
                        );
                    }
                }
                $jenis = 'pulang';
            } else {
                return $this->fail(
                    'Absensi hari ini sudah lengkap (masuk & pulang sudah tercatat).',
                    'SUDAH_LENGKAP',
                    409
                );
            }

            // Validasi jendela waktu sesi.
            // Tap lebih awal, terlambat, atau pulang lebih awal (pulang cepat) tetap dicatat selama sesi masih buka.
            $windowCheck = $this->assertWithinSessionWindow($now, $setting, $jenis);
            if ($windowCheck !== null) {
                return $windowCheck;
            }

            $menitTelat = $jenis === 'masuk'
                ? AbsensiLatenessCalculator::menitTerlambatMasuk($now, $setting, $shift)
                : AbsensiLatenessCalculator::menitTerlambatPulang($now, $setting, $shift);

            $menitPulangCepat = $jenis === 'pulang'
                ? AbsensiLatenessCalculator::menitPulangCepat($now, $setting, $shift)
                : 0;
            $pulangCepat = $jenis === 'pulang' && $menitPulangCepat > 0;

            $slotLab = ($jenis === 'masuk' && $shift === 'pagi')
                ? AbsensiLabSlotResolver::resolveForShift($setting, $now)
                : null;

            $pulangLembur = $jenis === 'pulang' && ! $pulangCepat
                ? AbsensiLatenessCalculator::isPulangLembur($now, $setting, $shift)
                : false;

            $logPayload = [
                'user_id' => $user->id,
                'id_mesin' => $idMesin,
                'sesi' => $shift,
                'jenis' => $jenis,
                'menit_terlambat' => $menitTelat,
                'slot_lab' => $slotLab,
                'pulang_lembur' => $pulangLembur,
                'waktu_tap' => $now,
            ];
            if (Schema::hasColumn('log_absensi', 'menit_pulang_cepat')) {
                $logPayload['menit_pulang_cepat'] = $menitPulangCepat;
                $logPayload['pulang_cepat'] = $pulangCepat;
            }

            $log = LogAbsensi::create($logPayload);

            $hasHarianPulangCepat = Schema::hasColumn('absensi_harian', 'menit_pulang_cepat');

            if ($jenis === 'masuk') {
                if (! $harian) {
                    $harianPayload = [
                        'user_id' => $user->id,
                        'tanggal' => $tanggalSesi,
                        'sesi' => $shift,
                        'status' => 'masuk',
                        'masuk_log_id' => $log->id,
                        'menit_terlambat_masuk' => $menitTelat,
                        'slot_lab_masuk' => $slotLab,
                    ];
                    $harian = AbsensiHarian::create($harianPayload);
                } else {
                    $harian->update([
                        'status' => 'masuk',
                        'masuk_log_id' => $log->id,
                        'menit_terlambat_masuk' => $menitTelat,
                        'slot_lab_masuk' => $slotLab,
                    ]);
                }

                // Jika sebelumnya sudah terlanjur digenerate alfa (mis. re-open), bersihkan.
                AbsensiAlfa::query()
                    ->where('user_id', $user->id)
                    ->whereDate('tanggal', $tanggalSesi)
                    ->where('sesi', $shift)
                    ->delete();
            } else {
                $updateHarian = [
                    'pulang_log_id' => $log->id,
                    'menit_terlambat_pulang' => $menitTelat,
                    'pulang_lembur' => $pulangLembur,
                    'status' => 'lengkap',
                    'lengkap_at' => $now,
                ];
                if ($hasHarianPulangCepat) {
                    $updateHarian['menit_pulang_cepat'] = $menitPulangCepat;
                    $updateHarian['pulang_cepat'] = $pulangCepat;
                }
                $harian->update($updateHarian);
            }

            return [
                'ok' => true,
                'http' => 200,
                'code' => $jenis === 'masuk' ? 'MASUK_OK' : 'PULANG_OK',
                'message' => $this->buildSuccessMessage($jenis, $menitTelat, $slotLab, $pulangLembur, $pulangCepat, $menitPulangCepat),
                'data' => [
                    'log_id' => $log->id,
                    'harian_id' => $harian->id,
                    'user_id' => $user->id,
                    'nama' => $user->nama_lengkap,
                    'nim' => $user->nitk_nidn,
                    'tanggal' => $tanggalSesi,
                    'sesi' => $shift,
                    'jenis' => $jenis,
                    'status_harian' => $harian->status,
                    'menit_terlambat' => $menitTelat,
                    'menit_pulang_cepat' => $menitPulangCepat,
                    'slot_lab' => $slotLab,
                    'pulang_lembur' => $pulangLembur,
                    'pulang_cepat' => $pulangCepat,
                    'waktu_tap' => $now->toIso8601String(),
                    'jadwal' => [
                        'masuk' => $jamMasuk->format('H:i'),
                        'pulang' => $jamPulang->format('H:i'),
                    ],
                ],
            ];
        });
    }

    private function assertWithinSessionWindow(Carbon $now, AbsensiSesiSetting $setting, string $jenis): ?array
    {
        $bukaTapMasuk = AbsensiLatenessCalculator::tapMasukBukaAt($now, $setting);
        $tutup = AbsensiLatenessCalculator::sessionCloseAt($now, $setting);

        if ($jenis === 'masuk' && $now->lessThan($bukaTapMasuk)) {
            return $this->fail(
                'Sesi absensi belum dibuka (buka pukul ' . $bukaTapMasuk->format('H:i') . ').',
                'BELUM_BUKA_SESI',
                403
            );
        }

        if ($now->greaterThan($tutup)) {
            return $this->fail(
                'Sesi absensi sudah ditutup (tutup pukul ' . $tutup->format('H:i') . ').',
                'SESI_DITUTUP',
                403
            );
        }

        return null;
    }

    private function buildSuccessMessage(
        string $jenis,
        int $menitTelat,
        ?int $slotLab,
        bool $pulangLembur,
        bool $pulangCepat = false,
        int $menitPulangCepat = 0
    ): string {
        if ($jenis === 'masuk') {
            $slotInfo = $slotLab !== null ? " (slot {$slotLab})" : '';
            if ($menitTelat > 0) {
                return "Absen masuk tercatat (terlambat {$menitTelat} menit){$slotInfo}. Jangan lupa absen pulang.";
            }

            return "Absen masuk tepat waktu{$slotInfo}. Jangan lupa absen pulang.";
        }

        if ($pulangCepat && $menitPulangCepat > 0) {
            return "Absen pulang tercatat lebih awal ({$menitPulangCepat} menit sebelum jam jadwal). Absensi hari ini lengkap.";
        }

        if ($pulangLembur || $menitTelat > 0) {
            return "Absen pulang tercatat melewati jam jadwal (+{$menitTelat} menit). Absensi hari ini lengkap.";
        }

        return 'Absen pulang tercatat. Absensi hari ini lengkap.';
    }

    private function fail(string $message, string $code, int $http): array
    {
        return [
            'ok' => false,
            'http' => $http,
            'code' => $code,
            'message' => $message,
        ];
    }
}
