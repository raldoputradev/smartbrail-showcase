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
        $panel = $this->periodicReportPanel();

        // Panel admin/laboran → halaman React (app-admin root). Dosen punya
        // method reports() sendiri yang merender DosenReports — jalur ini hanya
        // dipakai AdminController.
        if (!($panel['is_dosen'] ?? false)) {
            $user = $request->user();
            $p = $this->buildPeriodicReportPayload($request);
            $routes = $panel['routes'];

            \Inertia\Inertia::setRootView('app-admin');

            return \Inertia\Inertia::render('LaporanPeriodik', [
                'menu' => $this->adminSidebarMenu($user),
                'notif' => $this->adminNotifications($user),
                'report' => [
                    'filter' => $p['filter'],
                    'periode_label' => $p['periodeLabel'],
                    'activity_label' => $p['activityLabel'],
                    'kelas_options' => collect($p['kelasOptions'])->values()->all(),
                    'tim_options' => collect($p['timOptions'])->values()->all(),
                    'summary' => $p['summary'],
                    'chart' => $p['chart'],
                    'chart_weekly' => $p['chartWeekly'],
                    'kelas_chart' => $p['kelasChart'],
                    'weekday_chart' => $p['weekdayChart'],
                    'at_risk' => $p['atRisk'],
                    'periode_options' => $p['periodeOptions'],
                    'semester_aktif' => ($p['activePeriod'] ?? null) ? [
                        'id' => $p['activePeriod']->id,
                        'label' => $p['activePeriod']->label,
                        'jenis' => $p['activePeriod']->jenis,
                        'tanggal_mulai' => $p['activePeriod']->tanggal_mulai->toDateString(),
                        'tanggal_selesai' => $p['activePeriod']->tanggal_selesai->toDateString(),
                    ] : null,
                    'machines' => collect($p['topMachines'])->values()->all(),
                    'students' => collect($p['studentRecapsActive'])->map(fn ($r) => array_merge($r, [
                        'terakhir' => !empty($r['terakhir'])
                            ? \Carbon\Carbon::parse($r['terakhir'])->translatedFormat('d M Y · H:i')
                            : null,
                    ]))->values()->all(),
                ],
                'urls' => [
                    'index' => route($routes['index']),
                    'excel' => route($routes['export_excel']),
                    'word' => route($routes['export_word']),
                    'print' => route($routes['print']),
                    'rekap_harian' => route($routes['rekap']),
                ],
            ]);
        }

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
            'aktivitas' => ['sometimes', 'in:semua,absensi'],
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

    protected function guardDosenKelasAssignment(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->isDosen() && ! $user->isLaboran()
            && ! \App\Support\DosenKelasScope::hasSupervision($user)) {
            return back()->with('error', 'Belum ada mahasiswa dalam bimbingan Anda. Minta laboran menugaskan kelas atau peran tim PBL.');
        }

        return null;
    }

    public function exportReportExcel(Request $request)
    {
        if ($guard = $this->guardDosenKelasAssignment($request)) {
            return $guard;
        }
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
            $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $cellS = static fn ($v, string $style = '') => '<Cell' . ($style ? ' ss:StyleID="' . $style . '"' : '') . '><Data ss:Type="String">' . $esc($v) . '</Data></Cell>';
            $cellN = static fn ($v, string $style = '') => '<Cell' . ($style ? ' ss:StyleID="' . $style . '"' : '') . '><Data ss:Type="Number">' . (int) $v . '</Data></Cell>';
            $row = static fn (array $cells) => '<Row>' . implode('', $cells) . '</Row>';
            $filter = $payload['filter'];
            $summary = $payload['summary'];
            $rows = $payload['studentRecapsActive'] ?? $payload['studentRecaps'];
            $totalSesi = (int) ($summary['hadir'] ?? 0) + (int) ($summary['tidak_lengkap'] ?? 0) + (int) ($summary['alfa'] ?? 0);

            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>'
                . '<Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#1E3A5F"/></Style>'
                . '<Style ss:ID="head"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/></Style>'
                . '<Style ss:ID="lbl"><Font ss:Bold="1"/></Style>'
                . '<Style ss:ID="num"><NumberFormat ss:Format="#,##0"/></Style>'
                . '<Style ss:ID="pct"><NumberFormat ss:Format="0.0&quot;%&quot;"/></Style>'
                . '</Styles>';

            // Sheet 1: PETUNJUK
            echo '<Worksheet ss:Name="PETUNJUK"><Table>';
            echo $row([$cellS('SMARTBRAIL | TEMPLATE LAPORAN ABSENSI PERIODIK', 'title')]);
            echo $row([$cellS('')]);
            foreach ([
                'Workbook ini dihasilkan otomatis oleh SmartBrail sesuai filter halaman Laporan Periodik.',
                'Sheet RINGKASAN: informasi periode dan total status kehadiran.',
                'Sheet REKAP_MAHASISWA: rekap per mahasiswa yang tercatat di periode.',
                'Sheet REKAP_HARIAN: total status per tanggal.',
                'Sheet DETAIL_ABSENSI: cuplikan log tap perangkat (maks. 250 baris terakhir).',
                'Catatan: 1 sesi-mahasiswa = 1 mahasiswa pada 1 sesi di 1 tanggal.',
            ] as $line) {
                echo $row([$cellS('• ' . $line)]);
            }
            echo '</Table></Worksheet>';

            // Sheet 2: RINGKASAN
            echo '<Worksheet ss:Name="RINGKASAN"><Table>';
            echo $row([$cellS('SMARTBRAIL | LAPORAN ABSENSI PERIODIK', 'title')]);
            echo $row([$cellS('')]);
            echo $row([$cellS('Periode', 'lbl'), $cellS(($payload['periodeLabel'] ?? '') . ' (' . $filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir'] . ')')]);
            echo $row([$cellS('Tanggal cetak', 'lbl'), $cellS(now()->translatedFormat('d F Y H:i'))]);
            echo $row([$cellS('Kelas', 'lbl'), $cellS($filter['kelas'] ?: 'Semua'), $cellS('Tim PBL', 'lbl'), $cellS($filter['tim'] ?: 'Semua')]);
            echo $row([$cellS('')]);
            echo $row([$cellS('Metrik', 'head'), $cellS('Nilai', 'head')]);
            echo $row([$cellS('Mahasiswa dalam cakupan (orang)'), $cellN($summary['mahasiswa'] ?? 0, 'num')]);
            echo $row([$cellS('Mahasiswa dalam laporan (orang)'), $cellN($rows->count(), 'num')]);
            echo $row([$cellS('TOTAL SESI'), $cellN($totalSesi, 'num')]);
            echo $row([$cellS('HADIR'), $cellN($summary['hadir'] ?? 0, 'num')]);
            echo $row([$cellS('TIDAK LENGKAP'), $cellN($summary['tidak_lengkap'] ?? 0, 'num')]);
            echo $row([$cellS('ALFA'), $cellN($summary['alfa'] ?? 0, 'num')]);
            echo $row([$cellS('')]);
            echo $row([$cellS('Dalam laporan = mahasiswa dalam cakupan yang punya catatan hadir/tidak lengkap/alfa di periode.')]);
            echo '</Table></Worksheet>';

            // Sheet 3: REKAP_MAHASISWA
            echo '<Worksheet ss:Name="REKAP_MAHASISWA"><Table>';
            echo $row([$cellS('REKAPITULASI PER MAHASISWA (DALAM LAPORAN)', 'title')]);
            echo $row(array_map(fn ($h) => $cellS($h, 'head'), ['No', 'NIM', 'Nama', 'Kelas', 'Tim PBL', 'Hadir', 'Tidak Lengkap', 'Alfa', 'Total Sesi', '% Hadir', 'Catatan']));
            $no = 1;
            foreach ($rows as $r) {
                $tot = (int) ($r['hadir'] ?? 0) + (int) ($r['tidak_lengkap'] ?? 0) + (int) ($r['alfa'] ?? 0);
                $pctHadir = $tot > 0 ? round(($r['hadir'] / $tot) * 100, 1) : 0;
                echo $row([
                    $cellN($no++), $cellS($r['nim'] ?? '-'), $cellS($r['nama'] ?? '-'),
                    $cellS($r['kelas'] ?? '-'), $cellS($r['tim'] ?? '-'),
                    $cellN($r['hadir'] ?? 0, 'num'), $cellN($r['tidak_lengkap'] ?? 0, 'num'), $cellN($r['alfa'] ?? 0, 'num'),
                    $cellN($tot, 'num'),
                    '<Cell ss:StyleID="pct"><Data ss:Type="Number">' . $pctHadir . '</Data></Cell>',
                    $cellS(!empty($r['terakhir']) ? 'Terakhir: ' . \Carbon\Carbon::parse($r['terakhir'])->format('d/m/Y') : ''),
                ]);
            }
            if ($rows->isEmpty()) {
                echo $row([$cellS('Tidak ada data sesuai filter periode/kelas/tim.')]);
            }
            echo '</Table></Worksheet>';

            // Sheet 4: REKAP_HARIAN
            echo '<Worksheet ss:Name="REKAP_HARIAN"><Table>';
            echo $row([$cellS('REKAP HARIAN', 'title')]);
            echo $row(array_map(fn ($h) => $cellS($h, 'head'), ['Tanggal', 'Hadir', 'Tidak Lengkap', 'Alfa', 'Total Sesi', 'Catatan']));
            foreach (($payload['chart']['labels'] ?? []) as $ci => $tgl) {
                $h = (int) ($payload['chart']['hadir'][$ci] ?? 0);
                $tl = (int) ($payload['chart']['tidak_lengkap'][$ci] ?? 0);
                $a = (int) ($payload['chart']['alfa'][$ci] ?? 0);
                echo $row([
                    $cellS(\Carbon\Carbon::parse($tgl)->format('d/m/Y')),
                    $cellN($h, 'num'), $cellN($tl, 'num'), $cellN($a, 'num'), $cellN($h + $tl + $a, 'num'), $cellS(''),
                ]);
            }
            echo '</Table></Worksheet>';

            // Sheet 5: DETAIL_ABSENSI
            echo '<Worksheet ss:Name="DETAIL_ABSENSI"><Table>';
            echo $row([$cellS('DETAIL ABSENSI (LOG TAP PERANGKAT — MAKS. 250 BARIS TERAKHIR)', 'title')]);
            echo $row(array_map(fn ($h) => $cellS($h, 'head'), ['Tanggal', 'Waktu Masuk', 'Waktu Pulang', 'NIM', 'Nama', 'Kelas', 'Tim PBL', 'Mesin', 'Status', 'Keterangan']));
            foreach (($payload['detailRows'] ?? []) as $d) {
                $waktu = !empty($d['waktu']) ? \Carbon\Carbon::parse($d['waktu']) : null;
                $isMasuk = ($d['tap_jenis'] ?? '') === 'masuk';
                echo $row([
                    $cellS($waktu ? $waktu->format('d/m/Y') : '-'),
                    $cellS($waktu && $isMasuk ? $waktu->format('H:i:s') : ''),
                    $cellS($waktu && !$isMasuk ? $waktu->format('H:i:s') : ''),
                    $cellS($d['nim'] ?? '-'), $cellS($d['nama'] ?? '-'),
                    $cellS($d['kelas'] ?? '-'), $cellS($d['tim'] ?? '-'),
                    $cellS($d['id_mesin'] ?? '-'), $cellS($d['status'] ?? '-'), $cellS($d['status_meta'] ?? ''),
                ]);
            }
            echo '</Table></Worksheet>';

            echo '</Workbook>';
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }

    public function exportReportWord(Request $request)
    {
        if ($guard = $this->guardDosenKelasAssignment($request)) {
            return $guard;
        }
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

            $logoUrl = asset('images/logo-smartbrail.png');

            $summary = $payload['summary'];
            $totalSesi = (int) ($summary['hadir'] ?? 0) + (int) ($summary['tidak_lengkap'] ?? 0) + (int) ($summary['alfa'] ?? 0);

            echo '<table class="meta">';
            echo '<tr><td colspan="2" rowspan="3" style="width:72px;"><img src="' . $esc($logoUrl) . '" alt="SmartBrail" height="64" style="max-width:64px;"></td>';
            echo '<td colspan="6" class="title">LAPORAN ABSENSI | SMARTBRAIL</td></tr>';
            echo '<tr><td colspan="6" class="subtitle">SISTEM INFORMASI MANAJEMEN LABORATORIUM</td></tr>';
            echo '<tr><td colspan="6" class="subtitle">Politeknik Negeri Batam</td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<tr><td colspan="4" class="section">01 · INFORMASI LAPORAN</td></tr>';
            echo '<tr><td>Periode</td><td>' . $esc(($payload['periodeLabel'] ?? '') . ' · ' . $filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir']) . '</td><td>Tanggal Cetak</td><td>' . $esc(now()->translatedFormat('d F Y')) . '</td></tr>';
            echo '<tr><td>Mode</td><td>' . $esc($payload['activityLabel']) . '</td><td>Unit/Prodi</td><td>BRAIL · Politeknik Negeri Batam</td></tr>';
            echo '<tr><td>Filter Kelas</td><td>' . $esc($filter['kelas'] ?: 'Semua') . '</td><td>Filter Tim PBL</td><td>' . $esc($filter['tim'] ?: 'Semua') . '</td></tr>';
            echo '<tr><td>Disusun Oleh</td><td>' . $esc(auth()->user()->nama_lengkap ?? '-') . '</td><td>Diperiksa Oleh</td><td>____________________</td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<tr><td colspan="5" class="section">02 · RINGKASAN KEHADIRAN</td></tr>';
            echo '<tr><th>Mhs dalam Cakupan</th><th>Mhs dalam Laporan</th><th>Hadir</th><th>Tidak Lengkap</th><th>Alfa</th></tr>';
            echo '<tr>'
                . '<td class="right">' . $esc(number_format($summary['mahasiswa'] ?? 0)) . '</td>'
                . '<td class="right">' . $esc(number_format($rows->count())) . '</td>'
                . '<td class="right">' . $esc(number_format($summary['hadir'] ?? 0)) . '</td>'
                . '<td class="right">' . $esc(number_format($summary['tidak_lengkap'] ?? 0)) . '</td>'
                . '<td class="right">' . $esc(number_format($summary['alfa'] ?? 0)) . '</td></tr>';
            echo '<tr><td colspan="5" class="muted">Total sesi-mahasiswa: ' . $esc(number_format($totalSesi)) . '. Dalam laporan = mahasiswa dalam cakupan yang punya catatan hadir/tidak lengkap/alfa di periode. 1 sesi-mahasiswa = 1 mahasiswa pada 1 sesi di 1 tanggal.</td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<tr><td colspan="9" class="section">03 · REKAPITULASI PER MAHASISWA</td></tr>';
            echo '<tr><th>No</th><th>Nama Lengkap</th><th>NIM/ID</th><th>Kelas</th><th>Tim PBL</th><th>Hadir (sesi)</th><th>Tidak Lengkap (sesi)</th><th>Alfa (sesi)</th><th>Aktivitas Terakhir</th></tr>';
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
                echo '<td>' . $esc(!empty($row['terakhir']) ? \Carbon\Carbon::parse($row['terakhir'])->format('d/m/Y') : '-') . '</td>';
                echo '</tr>';
            }
            if ($rows->isEmpty()) {
                echo '<tr><td colspan="9" class="muted">Tidak ada data sesuai filter.</td></tr>';
            }
            echo '</table>';

            echo '</body></html>';
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }

    public function printReport(Request $request)
    {
        if ($guard = $this->guardDosenKelasAssignment($request)) {
            return $guard;
        }

        return view($this->periodicReportPrintView(), $this->buildPeriodicReportPayload($request, paginateTables: false));
    }
}
