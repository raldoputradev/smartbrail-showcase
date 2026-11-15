# Membuat kartu komposisi bahasa untuk README profil.
# Angka byte diambil dari GitHub linguist (endpoint /languages) lalu ditulis di sini,
# supaya kartunya berupa gambar statis yang selalu tampil tanpa bergantung layanan luar.
# Perbarui angkanya kalau ada repo baru.

param(
    [string]$Output = "C:\xampp\htdocs\raldoputradev\assets\bahasa.png"
)

$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Drawing

# nama, byte, warna (mengikuti palet linguist GitHub)
$langs = @(
    @{ name = "PHP";        bytes = 1190020; color = "#787CB5" },
    @{ name = "Blade";      bytes = 1099143; color = "#F7523F" },
    @{ name = "Dart";       bytes = 706077;  color = "#00B4AB" },
    @{ name = "CSS";        bytes = 315592;  color = "#8B5CC7" },
    @{ name = "JavaScript"; bytes = 269632;  color = "#F1E05A" },
    @{ name = "C++";        bytes = 106585;  color = "#F34B7D" },
    @{ name = "C";          bytes = 11258;   color = "#8B949E" }
)

$total = ($langs | ForEach-Object { $_.bytes } | Measure-Object -Sum).Sum

$W, $H = 760, 210
$bmp = New-Object System.Drawing.Bitmap($W, $H)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'AntiAlias'
$g.TextRenderingHint = 'ClearTypeGridFit'

$g.Clear([System.Drawing.ColorTranslator]::FromHtml("#0D1117"))
$edge = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(40, 255, 255, 255), 1)
$g.DrawRectangle($edge, 0, 0, ($W - 1), ($H - 1))

# Bilah bertumpuk
$barX, $barY, $barW, $barH = 36, 46, ($W - 72), 16
$cursor = [double]$barX
foreach ($l in $langs) {
    $w = ($l.bytes / $total) * $barW
    $brush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml($l.color))
    $g.FillRectangle($brush, [float]$cursor, [float]$barY, [float]([math]::Ceiling($w)), [float]$barH)
    $cursor += $w
}

# Keterangan dua kolom
$fLang = New-Object System.Drawing.Font("Segoe UI", 11, [System.Drawing.FontStyle]::Regular)
$fPct  = New-Object System.Drawing.Font("Consolas", 11, [System.Drawing.FontStyle]::Regular)
$bText = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(230, 233, 239))
$bDim  = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(150, 155, 165))

$i = 0
foreach ($l in $langs) {
    $col = [math]::Floor($i / 4)
    $row = $i % 4
    $lx = 36 + ($col * 350)
    $ly = 96 + ($row * 26)

    $dot = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml($l.color))
    $g.FillEllipse($dot, $lx, ($ly + 4), 9, 9)
    $g.DrawString($l.name, $fLang, $bText, ($lx + 16), $ly)
    $pct = [math]::Round(($l.bytes / $total) * 100, 1)
    $g.DrawString("$pct%", $fPct, $bDim, ($lx + 250), ($ly + 1))
    $i++
}

$dir = Split-Path $Output -Parent
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
$bmp.Save($Output, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bmp.Dispose()
Write-Host "Tersimpan: $Output ($([math]::Round((Get-Item $Output).Length/1KB)) KB)"
