<?php

namespace App\Support;

use App\Models\User;

class RekapAbsensiExportHtml
{
    /**
     * @param  array<string, string>  $filter
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     */
    public static function render(array $filter, array $rows, array $summary, ?User $exportedBy = null): string
    {
        $esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $logo = self::logoDataUri();
        $cols = ['tanggal', 'nim', 'nama', 'kelas', 'tim', 'prodi', 'semester', 'shift', 'sesi', 'status', 'slot_lab_masuk', 'pulang_lembur', 'jam_masuk', 'jam_pulang', 'menit_terlambat_masuk', 'menit_terlambat_pulang'];
        $headers = ['Tanggal', 'NIM', 'Nama', 'Kelas', 'Tim PBL', 'Prodi', 'Semester', 'Shift', 'Sesi', 'Status', 'Slot Lab', 'Pulang Lembur', 'Jam Masuk', 'Jam Pulang', 'Telat Masuk', 'Telat Pulang'];

        $statusBadge = static function (string $status) use ($esc): string {
            $colors = [
                'lengkap' => ['bg' => '#d1fae5', 'fg' => '#047857'],
                'alfa' => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
                'tidak_lengkap' => ['bg' => '#ffedd5', 'fg' => '#c2410c'],
                'masuk' => ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
                'sakit' => ['bg' => '#ede9fe', 'fg' => '#6d28d9'],
                'izin' => ['bg' => '#cffafe', 'fg' => '#0e7490'],
            ];
            $c = $colors[$status] ?? ['bg' => '#f1f5f9', 'fg' => '#475569'];
            $label = str_replace('_', ' ', $status);

            return '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;background:'
                . $c['bg'] . ';color:' . $c['fg'] . ';">' . $esc($label) . '</span>';
        };

        $html = "\xEF\xBB\xBF<!DOCTYPE html><html lang=\"id\"><head><meta charset=\"UTF-8\"><title>Rekap Absensi SIMALAS</title>";
        $html .= self::styles() . '</head><body><div class="wrap">';

        $html .= '<div class="hero">';
        if ($logo !== '') {
            $html .= '<img src="' . $logo . '" alt="SIMALAS">';
        }
        $html .= '<div><h1>REKAP ABSENSI SIMALAS</h1>';
        $html .= '<p>AIoT · Smart Lab System · Politeknik Negeri Batam</p>';
        if ($exportedBy) {
            $html .= '<p class="exported-by">Diekspor oleh: ' . $esc($exportedBy->nama_lengkap)
                . ' · ' . $esc(now()->timezone('Asia/Jakarta')->format('d M Y H:i')) . ' WIB</p>';
        }
        $html .= '</div></div>';

        $html .= '<div class="body">';

        $html .= '<div class="stats">';
        $html .= self::statCard((string) ($summary['hadir'] ?? 0), 'Hadir', 'ok');
        $html .= self::statCard((string) ($summary['terlambat'] ?? 0), 'Terlambat', 'warn');
        $html .= self::statCard((string) $summary['lengkap'], 'Lengkap', 'ok');
        $html .= self::statCard((string) $summary['alfa'], 'Alfa', 'bad');
        $html .= self::statCard((string) $summary['tidak_lengkap'], 'Tidak lengkap', 'warn');
        $html .= self::statCard((string) ($summary['persentase_lengkap'] ?? 0) . '%', '% Lengkap', 'info');
        $html .= '</div>';

        $html .= '<table class="meta">';
        $html .= '<tr><td>Periode</td><td colspan="3">' . $esc($filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir']) . '</td></tr>';
        $html .= '<tr><td>Kelas</td><td>' . $esc($filter['kelas'] ?: 'Semua') . '</td><td>Tim PBL</td><td>' . $esc($filter['tim'] ?: 'Semua') . '</td></tr>';
        $html .= '<tr><td>Shift</td><td>' . $esc($filter['shift'] ?: 'Semua') . '</td><td>Semester</td><td>' . $esc(($filter['semester'] ?? '') !== '' ? $filter['semester'] : 'Semua') . '</td></tr>';
        $html .= '<tr><td>Status filter</td><td colspan="3">' . $esc($filter['status'] ? str_replace('_', ' ', $filter['status']) : 'Semua') . ' · ' . $esc(AbsensiKebijakan::labelMode()) . '</td></tr>';
        $html .= '</table>';

        $html .= '<h2 style="font-size:14px;margin:18px 0 8px;color:#0c4a6e;">Detail tap</h2>';
        $html .= '<table class="data"><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . $esc($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($cols as $col) {
                if ($col === 'status') {
                    $html .= '<td>' . $statusBadge((string) ($row[$col] ?? '')) . '</td>';
                } else {
                    $html .= '<td>' . $esc($row[$col] ?? '') . '</td>';
                }
            }
            $html .= '</tr>';
        }

        if ($rows === []) {
            $html .= '<tr><td colspan="' . count($cols) . '" style="text-align:center;color:#64748b;padding:20px;">Tidak ada data pada filter ini.</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p class="foot">Dokumen ini dibuat otomatis oleh SIMALAS · ' . $esc(now()->format('Y-m-d H:i:s')) . '</p>';
        $html .= '</div></div></body></html>';

        return $html;
    }

    private static function statCard(string $value, string $label, string $tone): string
    {
        return '<div class="stat ' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') . '"><b>'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</b><span>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></div>';
    }

    private static function styles(): string
    {
        return '<style>
            body{font-family:Calibri,"Segoe UI",Arial,sans-serif;color:#0f172a;margin:0;padding:24px;background:#f8fafc}
            .wrap{max-width:1200px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(15,23,42,.08);border:1px solid #e2e8f0}
            .hero{background:linear-gradient(135deg,#0c4a6e 0%,#0891b2 55%,#22d3ee 100%);color:#fff;padding:22px 26px;display:flex;gap:18px;align-items:center}
            .hero img{width:48px;height:48px;max-height:48px;object-fit:contain;border-radius:8px;background:rgba(255,255,255,.12);padding:4px}
            .hero h1{margin:0;font-size:22px;font-weight:800}
            .hero p{margin:4px 0 0;font-size:12px;opacity:.92}
            .exported-by{font-size:11px!important;margin-top:8px!important;opacity:.85!important}
            .body{padding:22px 26px 28px}
            .stats{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin:0 0 18px}
            table.data.matrix{margin-bottom:18px}
            table.data.matrix td:nth-child(n+6){text-align:center}
            .stat{border-radius:12px;padding:12px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc}
            .stat b{display:block;font-size:20px;font-weight:800}
            .stat span{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-top:4px;display:block}
            .stat.ok b{color:#059669}.stat.bad b{color:#dc2626}.stat.warn b{color:#ea580c}.stat.info b{color:#2563eb}
            .meta{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12px}
            .meta td{padding:7px 10px;border:1px solid #e2e8f0}
            .meta td:first-child{font-weight:700;background:#f1f5f9;width:110px;color:#334155}
            table.data{width:100%;border-collapse:collapse;font-size:11px}
            table.data th{background:linear-gradient(180deg,#0c4a6e,#155e75);color:#fff;padding:9px 8px;border:1px solid #0e7490;font-weight:700}
            table.data td{padding:7px 8px;border:1px solid #e2e8f0;vertical-align:middle}
            table.data tr:nth-child(even) td{background:#f8fafc}
            .foot{margin-top:16px;font-size:10px;color:#94a3b8;text-align:right}
        </style>';
    }

    private static function logoDataUri(): string
    {
        foreach (['logo-simalas.png', 'logo-aiot.png'] as $file) {
            $path = public_path('images/' . $file);
            if (is_readable($path)) {
                return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
            }
        }

        return '';
    }
}
