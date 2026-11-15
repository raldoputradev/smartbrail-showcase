<?php

namespace App\Support;

use App\Models\AbsensiHarian;
use App\Models\LogAbsensi;
use App\Models\User;
use Carbon\Carbon;

class PeriodicStudentDetailBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(
        User $student,
        string $tanggalMulai,
        string $tanggalAkhir,
        string $aktivitas = 'semua'
    ): array {
        $aktivitas = 'absensi';

        try {
            $start = Carbon::parse($tanggalMulai)->startOfDay();
            $end = Carbon::parse($tanggalAkhir)->endOfDay();
        } catch (\Throwable) {
            $start = now()->subDays(30)->startOfDay();
            $end = now()->endOfDay();
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $student->loadMissing('programStudi');

        $harianRows = collect();
        $taps = collect();

        $harianRows = AbsensiHarian::query()
                ->with(['masukLog', 'pulangLog'])
                ->where('user_id', $student->id)
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->orderByDesc('tanggal')
                ->orderBy('sesi')
                ->get()
                ->map(fn (AbsensiHarian $h) => self::mapHarianRow($h))
                ->values();

            $taps = LogAbsensi::query()
                ->where('user_id', $student->id)
                ->whereBetween('waktu_tap', [$start, $end])
                ->orderByDesc('waktu_tap')
                ->get()
                ->map(fn (LogAbsensi $log) => [
                    'waktu' => $log->waktu_tap,
                    'waktu_label' => $log->waktu_tap?->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i:s') ?? '—',
                    'jenis' => $log->jenis ?: 'tap',
                    'jenis_label' => self::tapJenisLabel($log->jenis),
                    'id_mesin' => $log->id_mesin ?? '—',
                    'sesi' => $log->sesi ?? '—',
                    'menit_terlambat' => (int) ($log->menit_terlambat ?? 0),
                    'terlambat_label' => (int) ($log->menit_terlambat ?? 0) > 0
                        ? 'Terlambat ' . (int) $log->menit_terlambat . ' menit'
                        : 'Tepat waktu',
                ])
                ->values();

        $summary = [
            'hadir' => $harianRows->where('kategori', 'hadir')->count(),
            'tidak_lengkap' => $harianRows->where('kategori', 'tidak_lengkap')->count(),
            'alfa' => $harianRows->where('kategori', 'alfa')->count(),
            'total_tap' => $taps->count(),
        ];

        return [
            'student' => [
                'nama' => $student->nama_lengkap,
                'nim' => $student->nitk_nidn ?? '—',
                'kelas' => $student->kelas ?: '—',
                'tim' => $student->tim ?: '—',
                'prodi' => $student->programStudi?->nama ?? '—',
                'semester' => $student->semester ? (string) $student->semester : '—',
            ],
            'periode' => [
                'mulai' => $start->toDateString(),
                'akhir' => $end->toDateString(),
                'mulai_label' => $start->translatedFormat('d M Y'),
                'akhir_label' => $end->translatedFormat('d M Y'),
            ],
            'aktivitas' => $aktivitas,
            'summary' => $summary,
            'harianRows' => $harianRows,
            'taps' => $taps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapHarianRow(AbsensiHarian $h): array
    {
        $status = (string) $h->status;
        $telatMasuk = (int) $h->menit_terlambat_masuk;
        $telatPulang = (int) $h->menit_terlambat_pulang;

        $kategori = match (true) {
            $status === 'alfa' => 'alfa',
            in_array($status, ['masuk', 'tidak_lengkap'], true) => 'tidak_lengkap',
            $status === 'lengkap' => 'hadir',
            default => 'lain',
        };

        $ringkasan = match (true) {
            $status === 'alfa' => 'Alfa',
            $status === 'masuk' => 'Tidak lengkap — hanya masuk',
            $status === 'tidak_lengkap' => 'Tidak lengkap',
            $status === 'lengkap' && ($telatMasuk > 0 || $telatPulang > 0) => 'Hadir · terlambat',
            $status === 'lengkap' => 'Hadir · tepat waktu',
            default => ucfirst(str_replace('_', ' ', $status)),
        };

        return [
            'tanggal' => $h->tanggal->format('Y-m-d'),
            'tanggal_label' => $h->tanggal->translatedFormat('d M Y'),
            'sesi' => $h->sesi,
            'status' => $status,
            'kategori' => $kategori,
            'ringkasan' => $ringkasan,
            'jam_masuk' => $h->masukLog?->waktu_tap?->timezone('Asia/Jakarta')->format('H:i:s') ?? '—',
            'jam_pulang' => $h->pulangLog?->waktu_tap?->timezone('Asia/Jakarta')->format('H:i:s') ?? '—',
            'menit_terlambat_masuk' => $telatMasuk,
            'menit_terlambat_pulang' => $telatPulang,
            'pulang_lembur' => (bool) $h->pulang_lembur,
            'slot_lab_masuk' => $h->slot_lab_masuk,
        ];
    }

    private static function tapJenisLabel(?string $jenis): string
    {
        return match ($jenis) {
            'masuk' => 'Masuk',
            'pulang' => 'Pulang',
            default => 'Tap',
        };
    }
}
