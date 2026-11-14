<?php

namespace App\Support;

use App\Models\User;

/**
 * Export rekap harian .xls — tata letak selaras laporan periodik SIMALAS.
 */
class RekapAbsensiExportExcel
{
    /**
     * @param  array<string, string>  $filter
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     */
    public static function render(array $filter, array $rows, array $summary, ?User $exportedBy = null): string
    {
        $esc = [SimalasExcelExportLayout::class, 'esc'];
        $tanggal = $filter['tanggal_mulai'] === $filter['tanggal_akhir']
            ? $filter['tanggal_mulai']
            : $filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir'];

        $html = SimalasExcelExportLayout::openDocument();

        $html .= SimalasExcelExportLayout::reportHeaderBlock('REKAP ABSENSI HARIAN', [
            'AIoT · Sistem Manajemen Laboratorium Pintar Berbasis IoT',
            'Politeknik Negeri Batam',
        ]);

        $html .= SimalasExcelExportLayout::metaTableOpen();
        $html .= SimalasExcelExportLayout::metaRow([
            ['Tanggal', $tanggal],
            ['Kebijakan', AbsensiKebijakan::labelMode()],
        ]);
        $html .= SimalasExcelExportLayout::metaRow([
            ['Kelas', $filter['kelas'] ?: 'Semua'],
            ['Tim PBL', $filter['tim'] ?: 'Semua'],
            ['Shift', $filter['shift'] ?: 'Semua'],
            ['Semester', ($filter['semester'] ?? '') !== '' ? (string) $filter['semester'] : 'Semua'],
        ]);
        $html .= '<tr>';
        $html .= '<td class="stats-label">Total baris</td><td class="right">' . $esc(number_format((int) ($summary['total'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Wajib absensi</td><td class="right">' . $esc(number_format((int) ($summary['denominator_wajib'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Hadir</td><td class="right">' . $esc(number_format((int) ($summary['hadir'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Terlambat</td><td class="right">' . $esc(number_format((int) ($summary['terlambat'] ?? 0))) . '</td>';
        $html .= '</tr><tr>';
        $html .= '<td class="stats-label">Lengkap</td><td class="right">' . $esc(number_format((int) ($summary['lengkap'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Tidak lengkap</td><td class="right">' . $esc(number_format((int) ($summary['tidak_lengkap'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Alfa</td><td class="right">' . $esc(number_format((int) ($summary['alfa'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Belum tap</td><td class="right">' . $esc(number_format((int) ($summary['belum'] ?? 0))) . '</td>';
        $html .= '</tr><tr>';
        $html .= '<td class="stats-label">% Lengkap</td><td class="right">' . $esc(($summary['persentase_lengkap'] ?? 0) . '%') . '</td>';
        $html .= '<td class="stats-label">Tidak wajib</td><td class="right">' . $esc(number_format((int) ($summary['tidak_wajib'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Hanya masuk</td><td class="right">' . $esc(number_format((int) ($summary['masuk'] ?? 0))) . '</td>';
        $html .= '<td class="stats-label">Filter status</td><td>' . $esc($filter['status'] ?? 'Semua') . '</td>';
        $html .= '</tr>';
        $html .= SimalasExcelExportLayout::metaTableClose();

        $colCount = 17;
        $html .= '<table class="data">';
        $html .= '<tr><td colspan="' . $colCount . '" class="section">DETAIL TAP PER MAHASISWA</td></tr>';
        $headers = [
            'Tanggal', 'NIM', 'Nama', 'Kelas', 'Tim PBL', 'Prodi', 'Semester', 'Shift',
            'Sesi', 'Status', 'Slot Lab', 'Pulang Lembur', 'Jam Masuk', 'Jam Pulang', 'Telat Masuk (m)', 'Telat Pulang (m)',
        ];
        $html .= '<tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $esc($header) . '</th>';
        }
        $html .= '</tr>';

        foreach ($rows as $row) {
            $status = str_replace('_', ' ', (string) ($row['status'] ?? ''));
            $html .= '<tr>';
            $html .= '<td class="center">' . $esc($row['tanggal'] ?? '') . '</td>';
            $html .= '<td class="nim">' . $esc($row['nim'] ?? '') . '</td>';
            $html .= '<td>' . $esc($row['nama'] ?? '') . '</td>';
            $html .= '<td>' . $esc($row['kelas'] ?? '') . '</td>';
            $html .= '<td>' . $esc($row['tim'] ?? '') . '</td>';
            $html .= '<td>' . $esc($row['prodi'] ?? '') . '</td>';
            $html .= '<td class="center">' . $esc($row['semester'] ?? '') . '</td>';
            $html .= '<td>' . $esc($row['shift'] ?? '') . '</td>';
            $html .= '<td>' . $esc($row['sesi'] ?? '') . '</td>';
            $html .= '<td>' . $esc($status) . '</td>';
            $html .= '<td class="center">' . $esc($row['slot_lab_masuk'] ? 'S' . $row['slot_lab_masuk'] : '—') . '</td>';
            $html .= '<td class="center">' . $esc(!empty($row['pulang_lembur']) ? 'Ya' : '—') . '</td>';
            $html .= '<td class="center">' . $esc($row['jam_masuk'] ?? '—') . '</td>';
            $html .= '<td class="center">' . $esc($row['jam_pulang'] ?? '—') . '</td>';
            $html .= '<td class="right">' . $esc((string) ($row['menit_terlambat_masuk'] ?? 0)) . '</td>';
            $html .= '<td class="right">' . $esc((string) ($row['menit_terlambat_pulang'] ?? 0)) . '</td>';
            $html .= '</tr>';
        }

        if ($rows === []) {
            $html .= '<tr><td colspan="' . $colCount . '" class="muted">Tidak ada data pada filter ini.</td></tr>';
        }

        $html .= '</table>';
        $html .= SimalasExcelExportLayout::exportedByFooter($exportedBy);
        $html .= SimalasExcelExportLayout::closeDocument();

        return $html;
    }
}
