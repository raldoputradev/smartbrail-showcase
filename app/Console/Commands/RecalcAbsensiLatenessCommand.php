<?php

namespace App\Console\Commands;

use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Support\AbsensiLatenessCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalcAbsensiLatenessCommand extends Command
{
    protected $signature = 'absensi:recalc-lateness
                            {--date= : Tanggal Y-m-d (default: hari ini)}
                            {--shift= : pagi atau malam (kosong = semua sesi aktif)}';

    protected $description = 'Hitung ulang telat masuk/pulang & lembur sesuai toleransi (perbaiki data tap lama)';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();

        $settings = AbsensiSesiSetting::query()->where('aktif', true);
        if ($shift = $this->option('shift')) {
            $settings->where('shift', $shift);
        }
        $settings = $settings->get();

        if ($settings->isEmpty()) {
            $this->warn('Tidak ada sesi aktif.');

            return self::FAILURE;
        }

        $updated = 0;

        foreach ($settings as $setting) {
            $rows = AbsensiHarian::query()
                ->whereDate('tanggal', $date)
                ->where('sesi', $setting->shift)
                ->with(['masukLog', 'pulangLog'])
                ->get();

            foreach ($rows as $harian) {
                $changes = [];

                if ($harian->masukLog?->waktu_tap) {
                    $tap = $harian->masukLog->waktu_tap->copy()->timezone($setting->timezone ?: 'Asia/Jakarta');
                    $menitMasuk = AbsensiLatenessCalculator::menitTerlambatMasuk($setting, $tap);
                    if ((int) $harian->menit_terlambat_masuk !== $menitMasuk) {
                        $harian->menit_terlambat_masuk = $menitMasuk;
                        $changes['menit_terlambat_masuk'] = $menitMasuk;
                    }
                    if ((int) $harian->masukLog->menit_terlambat !== $menitMasuk) {
                        DB::table('log_absensi')->where('id', $harian->masukLog->id)->update([
                            'menit_terlambat' => $menitMasuk,
                        ]);
                    }
                }

                if ($harian->pulangLog?->waktu_tap) {
                    $tap = $harian->pulangLog->waktu_tap->copy()->timezone($setting->timezone ?: 'Asia/Jakarta');
                    $metrics = AbsensiLatenessCalculator::pulangMetrics($setting, $tap);
                    if ((int) $harian->menit_terlambat_pulang !== $metrics['menit_terlambat']) {
                        $harian->menit_terlambat_pulang = $metrics['menit_terlambat'];
                        $changes['menit_terlambat_pulang'] = $metrics['menit_terlambat'];
                    }
                    if ((bool) $harian->pulang_lembur !== $metrics['pulang_lembur']) {
                        $harian->pulang_lembur = $metrics['pulang_lembur'];
                        $changes['pulang_lembur'] = $metrics['pulang_lembur'];
                    }
                    DB::table('log_absensi')->where('id', $harian->pulangLog->id)->update([
                        'menit_terlambat' => $metrics['menit_terlambat'],
                        'pulang_lembur' => $metrics['pulang_lembur'],
                    ]);
                }

                if ($changes !== []) {
                    $harian->save();
                    $updated++;
                    $this->line(sprintf(
                        '- %s %s: M=%s P=%s lembur=%s',
                        $date,
                        $harian->sesi,
                        (string) ($harian->menit_terlambat_masuk ?? 0),
                        (string) ($harian->menit_terlambat_pulang ?? 0),
                        $harian->pulang_lembur ? 'ya' : 'tidak'
                    ));
                }
            }

            $batasPulang = $setting->batasAmanPulangOn(Carbon::parse($date, $setting->timezone ?: 'Asia/Jakarta'));
            $this->info(sprintf(
                'Sesi %s: toleransi pulang aman sampai %s (jam pulang %s + %d menit)',
                $setting->shift,
                $batasPulang->format('H:i'),
                $setting->jamPulangOn(Carbon::parse($date, $setting->timezone ?: 'Asia/Jakarta'))->format('H:i'),
                $setting->toleransiPulangMenit()
            ));
        }

        $this->info("Selesai — {$updated} baris absensi harian diperbarui untuk {$date}.");

        return self::SUCCESS;
    }
}
