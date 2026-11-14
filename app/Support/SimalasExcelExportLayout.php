<?php

namespace App\Support;

use App\Models\User;

/**
 * Tata letak & gaya HTML Excel (.xls) seragam untuk SIMALAS.
 */
class SimalasExcelExportLayout
{
    public static function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function openDocument(): string
    {
        return "\xEF\xBB\xBF"
            . '<!DOCTYPE html><html xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel">'
            . '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
            . self::styles()
            . '</head><body>';
    }

    public static function closeDocument(): string
    {
        return '</body></html>';
    }

    public static function styles(): string
    {
        return '<style>
            body{font-family:Calibri,Arial,sans-serif;color:#1f2937;margin:0;padding:8px}
            .title{font-size:20px;font-weight:800;color:#1e3a5f}
            .subtitle{color:#64748b;font-size:12px}
            table{border-collapse:collapse;width:100%;margin-top:12px}
            th{background:#1e3a5f;color:#ffffff;font-weight:700;border:1px solid #94a3b8;padding:8px 6px;font-size:11pt}
            td{border:1px solid #cbd5e1;padding:7px 6px;font-size:11pt;vertical-align:middle}
            .section{background:#dbeafe;color:#1e3a5f;font-weight:800;font-size:11pt;border:1px solid #93c5fd;padding:8px}
            .meta{border-collapse:collapse;width:100%;margin-bottom:4px;table-layout:fixed}
            .meta td{border:1px solid #cbd5e1;padding:7px 8px;font-size:11pt;background:#ffffff}
            td.logo-cell{text-align:left!important;vertical-align:top!important;background:#f8fafc}
            td.logo-cell .logo-wrap{text-align:left}
            td.logo-cell img.logo-img{display:block;border:0}
            .meta .label{font-weight:700;background:#f1f5f9;color:#334155;width:120px}
            .meta .stats-label{font-weight:700;background:#e0f2fe;color:#0c4a6e}
            .right{text-align:right}
            .center{text-align:center}
            .muted{color:#64748b;font-style:italic}
            .data tr:nth-child(even) td{background:#f8fafc}
            .foot{margin-top:14px;font-size:10pt;color:#94a3b8;text-align:right}
            .nim{mso-number-format:"\@";}
        </style>';
    }

    /** Tinggi tampilan logo di Excel (px) — sama kira-kira laporan periodik (~64–72). */
    private const LOGO_DISPLAY_HEIGHT_PX = 72;

    /**
     * @return array{0: string, 1: int, 2: int, 3: int} [data-uri, imgW, imgH, colWidthPx]
     */
    public static function logoAsset(): array
    {
        foreach (['logo-aiot.png', 'logo-simalas.png'] as $file) {
            $path = public_path('images/' . $file);
            if (!is_readable($path)) {
                continue;
            }

            [$imgW, $imgH] = self::fitLogoByHeight($path, self::LOGO_DISPLAY_HEIGHT_PX);
            $colWidth = $imgW + 28;

            return [
                'data:image/png;base64,' . base64_encode((string) file_get_contents($path)),
                $imgW,
                $imgH,
                $colWidth,
            ];
        }

        return ['', 0, 0, 84];
    }

    public static function logoColumnWidthPx(): int
    {
        return self::logoAsset()[3];
    }

    public static function logoCellHtml(int $rowSpan = 3): string
    {
        [$logo, $imgW, $imgH, $colW] = self::logoAsset();

        if ($logo === '') {
            return '<td rowspan="' . $rowSpan . '" class="logo-cell" '
                . 'style="width:' . $colW . 'px;font-weight:700;color:#1e3a5f;">AIoT</td>';
        }

        $img = '<img src="' . $logo . '" alt="AIoT" class="logo-img" '
            . 'width="' . $imgW . '" height="' . $imgH . '" '
            . 'style="width:' . $imgW . 'px;height:' . $imgH . 'px;border:0;">';

        // Sedikit ke kiri (padding kanan lebih besar) & ke bawah (padding atas).
        $wrap = '<div class="logo-wrap" align="left" style="text-align:left;padding:14px 22px 2px 4px;">'
            . $img
            . '</div>';

        return '<td rowspan="' . $rowSpan . '" class="logo-cell" align="left" valign="top" '
            . 'style="width:' . $colW . 'px;mso-width-source:userset;text-align:left;vertical-align:top;">'
            . $wrap
            . '</td>';
    }

    public static function logoDataUri(): string
    {
        return self::logoAsset()[0];
    }

    /**
     * Kunci tinggi gambar, lebar mengikuti rasio asli (cegah gepeng di sel rowspan).
     *
     * @return array{0: int, 1: int}
     */
    private static function fitLogoByHeight(string $path, int $targetHeightPx): array
    {
        $info = @getimagesize($path);
        if (!$info || $info[0] < 1 || $info[1] < 1) {
            return [$targetHeightPx, $targetHeightPx];
        }

        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        $imgW = max(1, (int) round($targetHeightPx * $srcW / $srcH));
        $imgH = $targetHeightPx;

        return [$imgW, $imgH];
    }

    /**
     * @param  list<string>  $lines  Subtitle lines under main title
     */
    public static function reportHeaderBlock(string $title, array $lines = []): string
    {
        $colSpan = 8;
        $rowSpan = max(1, 1 + count($lines));
        $logoColPx = self::logoColumnWidthPx();
        $headerRowPt = 22;
        $html = '<table class="meta">';
        $html .= '<colgroup><col style="width:' . $logoColPx . 'px;mso-width-source:userset">'
            . '<col span="' . $colSpan . '" style="width:auto"></colgroup>';
        $html .= '<tr style="height:' . $headerRowPt . 'pt;mso-height-source:userset">';
        $html .= self::logoCellHtml($rowSpan);
        $html .= '<td colspan="' . $colSpan . '" class="title">' . self::esc($title) . '</td></tr>';

        foreach ($lines as $line) {
            $html .= '<tr style="height:' . $headerRowPt . 'pt;mso-height-source:userset">'
                . '<td colspan="' . $colSpan . '" class="subtitle">' . self::esc($line) . '</td></tr>';
        }

        $html .= '</table>';

        return $html;
    }

    public static function metaTableOpen(): string
    {
        return '<table class="meta">';
    }

    public static function metaTableClose(): string
    {
        return '</table>';
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs  [label, value]
     */
    public static function metaRow(array $pairs): string
    {
        $html = '<tr>';
        foreach ($pairs as [$label, $value]) {
            $html .= '<td class="label">' . self::esc($label) . '</td>';
            $html .= '<td>' . self::esc($value) . '</td>';
        }
        $html .= '</tr>';

        return $html;
    }

    public static function exportedByFooter(?User $exportedBy): string
    {
        $line = 'Diekspor: ' . ($exportedBy?->nama_lengkap ?? 'Sistem')
            . ' · ' . now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') . ' WIB';

        return '<p class="foot">' . self::esc($line) . '</p>';
    }
}
