<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AbsensiSesiSetting;
use App\Models\User;
use App\Support\AbsensiKebijakan;
use App\Support\PeriodicReportBuilder;
use App\Support\PeriodicStudentDetailBuilder;
use App\Support\SimalasExcelExportLayout;
use App\Support\TablePagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait HandlesPeriodicReports
{
    protected function periodicReportView(): string
    {
        return 'reports.periodic';
    }

    protected function periodicReportPrintView(): string
    {
        return 'admin.reports_print';
    }

    protected function periodicReportDosen(): ?User
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function periodicReportPanel(): array
    {
        return [
            'sidebar' => 'admin._sidebar',
            'is_dosen' => false,
            'routes' => [
                'index' => 'admin.reports',
                'export_excel' => 'admin.reports.export.excel',
                'export_word' => 'admin.reports.export.word',
                'print' => 'admin.reports.print',
                'rekap' => 'admin.attendance',
                'student_detail' => 'admin.reports.student-detail',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPeriodicReportPayload(Request $request, bool $paginateTables = true): array
    {
        $payload = array_merge(
            PeriodicReportBuilder::build($request, $this->periodicReportDosen()),
            [
                'reportPanel' => $this->periodicReportPanel(),
                'kebijakanLabel' => AbsensiKebijakan::labelMode(),
                'jadwalSesi' => [
                    'pagi' => AbsensiSesiSetting::forShift('pagi'),
                    'malam' => AbsensiSesiSetting::forShift('malam'),
                ],
            ]
        );

        if ($paginateTables) {
            // Halaman utama pakai rankings (top 10), bukan daftar penuh.
            // studentRecaps tetap full collection untuk export saat paginateTables=false.
        }

        return $payload;
    }

    public function reports(Request $request)
    {
        return view($this->periodicReportView(), $this->buildPeriodicReportPayload($request));
    }

    public function periodicStudentDetail(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'mahasiswa') {
            abort(404);
        }

        $this->authorizePeriodicStudent($user);

        $validated = $request->validate([
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_akhir' => ['required', 'date'],
            'aktivitas' => ['sometimes', 'in:semua,absensi,loker'],
        ]);

        $aktivitas = $validated['aktivitas'] ?? 'semua';
        $payload = PeriodicStudentDetailBuilder::build(
            $user,
            $validated['tanggal_mulai'],
            $validated['tanggal_akhir'],
            $aktivitas,
        );

        return response()->json([
            'ok' => true,
            'html' => view('reports.partials.student-detail-content', array_merge($payload, [
                'aktivitas' => $aktivitas,
            ]))->render(),
        ]);
    }

    protected function authorizePeriodicStudent(User $student): void
    {
        if (!$student->is_active) {
            abort(403);
        }
    }

    public function exportReportExcel(Request $request)
    {
        $payload = $this->buildPeriodicReportPayload($request, paginateTables: false);
        $filter = $payload['filter'];
        $scope = collect([
            $filter['periode'] ?? 'custom',
            $filter['kelas'] ? 'kelas-' . \Illuminate\Support\Str::slug($filter['kelas']) : null,
            $filter['tim'] ? 'tim-' . \Illuminate\Support\Str::slug($filter['tim']) : null,
        ])->filter()->implode('-');
        $filename = 'laporan-absensi-' . $scope . '-' . $filter['tanggal_mulai'] . '_' . $filter['tanggal_akhir'] . '.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = static function () use ($payload): void {
            $esc = [SimalasExcelExportLayout::class, 'esc'];
            $filter = $payload['filter'];
            $rows = $payload['studentRecapsActive'] ?? $payload['studentRecaps'];
            $isAbsensi = ($filter['aktivitas'] ?? 'absensi') !== 'loker';

            echo SimalasExcelExportLayout::openDocument();
            echo SimalasExcelExportLayout::reportHeaderBlock('LAPORAN ABSENSI SIMALAS', [
                'AIoT · Sistem Manajemen Laboratorium Pintar Berbasis IoT',
                'Politeknik Negeri Batam',
            ]);

            echo SimalasExcelExportLayout::metaTableOpen();
            echo SimalasExcelExportLayout::metaRow([
                ['Periode', ($payload['periodeLabel'] ?? '') . ' (' . $filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir'] . ')'],
            ]);
            echo SimalasExcelExportLayout::metaRow([
                ['Cakupan data', $payload['activityLabel']],
                ['Rekor', ($filter['rekor'] ?? 'terajin') . ' / ' . ($filter['rekor_group'] ?? 'semua')],
            ]);
            echo SimalasExcelExportLayout::metaRow([
                ['Kelas', $filter['kelas'] ?: 'Semua'],
                ['Tim PBL', $filter['tim'] ?: 'Semua'],
            ]);
            echo '<tr>';
            echo '<td class="stats-label">Mahasiswa dalam cakupan (orang)</td><td class="right">' . $esc(number_format($payload['summary']['mahasiswa'])) . '</td>';
            echo '<td class="stats-label">Mahasiswa dalam laporan (orang)</td><td class="right">' . $esc(number_format($rows->count())) . '</td>';
            if ($isAbsensi) {
                echo '<td class="stats-label">Hadir (sesi-mahasiswa)</td><td class="right">' . $esc(number_format($payload['summary']['hadir'] ?? 0)) . '</td>';
                echo '<td class="stats-label">Tidak lengkap (sesi-mahasiswa)</td><td class="right">' . $esc(number_format($payload['summary']['tidak_lengkap'] ?? 0)) . '</td>';
            }
            echo '</tr>';
            if ($isAbsensi) {
                echo '<tr>';
                echo '<td class="stats-label">Alfa (sesi-mahasiswa)</td><td class="right" colspan="7">' . $esc(number_format($payload['summary']['alfa'] ?? 0)) . '</td>';
                echo '</tr>';
            }
            echo '<tr><td colspan="8" class="muted">Dalam laporan = mahasiswa dalam cakupan yang punya catatan hadir/tidak lengkap/alfa di periode. 1 sesi-mahasiswa = 1 mahasiswa pada 1 sesi di 1 tanggal; dua sesi di tanggal yang sama dihitung dua.</td></tr>';
            echo SimalasExcelExportLayout::metaTableClose();

            // Rekor ringkas
            $rankings = $payload['rankings'] ?? null;
            if ($rankings && !empty($rankings['items'])) {
                echo '<table class="data">';
                echo '<tr><td colspan="5" class="section">REKOR: ' . $esc($rankings['title'] ?? '') . '</td></tr>';
                echo '<tr><th>Peringkat</th><th>Nama / Grup</th><th>Keterangan</th><th>Nilai</th></tr>';
                foreach ($rankings['items'] as $item) {
                    echo '<tr>';
                    echo '<td class="right">' . $esc($item['rank']) . '</td>';
                    echo '<td>' . $esc($item['label']) . '</td>';
                    echo '<td>' . $esc($item['meta'] ?? '') . '</td>';
                    echo '<td class="right">' . $esc($item['value_label'] ?? $item['value']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            echo '<table class="data">';
            echo '<tr><td colspan="7" class="section">REKAP MAHASISWA DALAM LAPORAN (punya catatan hadir/tidak lengkap/alfa di periode)</td></tr>';
            echo '<tr><th>No</th><th>Nama</th><th>NIM</th><th>Kelas</th><th>Tim</th><th>Hadir (sesi)</th><th>Tidak Lengkap (sesi)</th><th>Alfa (sesi)</th></tr>';
            $no = 1;
            foreach ($rows as $row) {
                if (!$isAbsensi) {
                    continue;
                }
                echo '<tr>';
                echo '<td class="right">' . $esc($no++) . '</td>';
                echo '<td>' . $esc($row['nama']) . '</td>';
                echo '<td class="nim">' . $esc($row['nim']) . '</td>';
                echo '<td>' . $esc($row['kelas']) . '</td>';
                echo '<td>' . $esc($row['tim']) . '</td>';
                echo '<td class="right">' . $esc($row['hadir'] ?? 0) . '</td>';
                echo '<td class="right">' . $esc($row['tidak_lengkap'] ?? 0) . '</td>';
                echo '<td class="right">' . $esc($row['alfa'] ?? 0) . '</td>';
                echo '</tr>';
            }
            if ($rows->isEmpty()) {
                echo '<tr><td colspan="8" class="muted">Tidak ada data sesuai filter periode/kelas/tim.</td></tr>';
            }
            echo '</table>';

            echo SimalasExcelExportLayout::closeDocument();
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }

    public function exportReportWord(Request $request)
    {
        $payload = $this->buildPeriodicReportPayload($request, paginateTables: false);
        $filter = $payload['filter'];
        $scope = collect([
            $filter['periode'] ?? 'custom',
            $filter['kelas'] ? 'kelas-' . \Illuminate\Support\Str::slug($filter['kelas']) : null,
            $filter['tim'] ? 'tim-' . \Illuminate\Support\Str::slug($filter['tim']) : null,
        ])->filter()->implode('-');
        $filename = 'laporan-absensi-' . $scope . '-' . $filter['tanggal_mulai'] . '_' . $filter['tanggal_akhir'] . '.doc';
        $headers = [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = static function () use ($payload): void {
            $esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $filter = $payload['filter'];
            $rows = $payload['studentRecapsActive'] ?? $payload['studentRecaps'];

            echo "\xEF\xBB\xBF";
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            echo '<style>
                body{font-family:Calibri,Arial,sans-serif;color:#1f2937}
                .title{font-size:20px;font-weight:800;color:#1e3a5f}
                .subtitle{color:#64748b;font-size:12px}
                table{border-collapse:collapse;width:100%;margin-top:12px}
                th{background:#1e3a5f;color:#ffffff;font-weight:700;border:1px solid #d1d5db;padding:8px}
                td{border:1px solid #d1d5db;padding:7px}
                .section{background:#dbeafe;color:#1e3a5f;font-weight:800}
                .meta td{background:#f8fafc}
                .right{text-align:right}
                .muted{color:#64748b}
            </style></head><body>';

            $logoUrl = asset('images/logo-aiot.png');

            echo '<table class="meta">';
            echo '<tr><td colspan="2" rowspan="3" style="width:72px;"><img src="' . $esc($logoUrl) . '" alt="AIoT" height="64" style="max-width:64px;"></td>';
            echo '<td colspan="6" class="title">LAPORAN ABSENSI SIMALAS</td></tr>';
            echo '<tr><td colspan="6" class="subtitle">AIoT · Sistem Manajemen Laboratorium Pintar Berbasis IoT</td></tr>';
            echo '<tr><td colspan="6" class="subtitle">Politeknik Negeri Batam</td></tr>';
            echo '<tr><td>Periode</td><td colspan="7">' . $esc(($payload['periodeLabel'] ?? '') . ' · ' . $filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir']) . '</td></tr>';
            echo '<tr><td>Cakupan</td><td colspan="7">' . $esc($payload['activityLabel']) . ' · Kelas: ' . $esc($filter['kelas'] ?: 'Semua') . ' · Tim: ' . $esc($filter['tim'] ?: 'Semua') . '</td></tr>';
            echo '<tr><td>Mahasiswa dalam cakupan</td><td>' . $esc(number_format($payload['summary']['mahasiswa'])) . ' orang</td><td>Mahasiswa dalam laporan</td><td>' . $esc(number_format($rows->count())) . ' orang</td><td colspan="4"></td></tr>';
            echo '<tr><td>Hadir</td><td>' . $esc(number_format($payload['summary']['hadir'] ?? 0)) . ' sesi-mahasiswa</td><td>Tidak lengkap</td><td>' . $esc(number_format($payload['summary']['tidak_lengkap'] ?? 0)) . ' sesi-mahasiswa</td><td>Alfa</td><td>' . $esc(number_format($payload['summary']['alfa'] ?? 0)) . ' sesi-mahasiswa</td><td colspan="2"></td></tr>';
            echo '<tr><td colspan="8" class="muted">Dalam laporan = mahasiswa dalam cakupan yang punya catatan hadir/tidak lengkap/alfa di periode. 1 sesi-mahasiswa = 1 mahasiswa pada 1 sesi di 1 tanggal; dua sesi di tanggal yang sama dihitung dua.</td></tr>';
            echo '</table>';

            $rankings = $payload['rankings'] ?? null;
            if ($rankings && !empty($rankings['items'])) {
                echo '<table>';
                echo '<tr><td colspan="4" class="section">REKOR: ' . $esc($rankings['title'] ?? '') . '</td></tr>';
                echo '<tr><th>#</th><th>Nama / Grup</th><th>Keterangan</th><th>Nilai</th></tr>';
                foreach ($rankings['items'] as $item) {
                    echo '<tr>';
                    echo '<td class="right">' . $esc($item['rank']) . '</td>';
                    echo '<td>' . $esc($item['label']) . '</td>';
                    echo '<td>' . $esc($item['meta'] ?? '') . '</td>';
                    echo '<td class="right">' . $esc($item['value_label'] ?? $item['value']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            echo '<table>';
            echo '<tr><td colspan="8" class="section">REKAP MAHASISWA DALAM LAPORAN (punya catatan hadir/tidak lengkap/alfa di periode)</td></tr>';
            echo '<tr><th>No</th><th>Nama</th><th>NIM</th><th>Kelas</th><th>Tim</th><th>Hadir (sesi)</th><th>Tidak Lengkap (sesi)</th><th>Alfa (sesi)</th></tr>';
            $no = 1;
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td class="right">' . $esc($no++) . '</td>';
                echo '<td>' . $esc($row['nama']) . '</td>';
                echo '<td>' . $esc($row['nim']) . '</td>';
                echo '<td>' . $esc($row['kelas']) . '</td>';
                echo '<td>' . $esc($row['tim']) . '</td>';
                echo '<td class="right">' . $esc($row['hadir'] ?? 0) . '</td>';
                echo '<td class="right">' . $esc($row['tidak_lengkap'] ?? 0) . '</td>';
                echo '<td class="right">' . $esc($row['alfa'] ?? 0) . '</td>';
                echo '</tr>';
            }
            if ($rows->isEmpty()) {
                echo '<tr><td colspan="8" class="muted">Tidak ada data sesuai filter.</td></tr>';
            }
            echo '</table>';

            echo '</body></html>';
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }

    public function printReport(Request $request)
    {
        return view($this->periodicReportPrintView(), $this->buildPeriodicReportPayload($request, paginateTables: false));
    }
}
