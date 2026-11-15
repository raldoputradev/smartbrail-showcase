# Membuat banner atas untuk README profil GitHub (raldoputradev/raldoputradev).
# Gaya mengikuti portofolio: latar gelap, aksen emas, motif jalur sirkuit.

param(
    [string]$Output = "C:\xampp\htdocs\raldoputradev\assets\banner.png"
)

$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Drawing

$W, $H = 1280, 340
$bmp = New-Object System.Drawing.Bitmap($W, $H)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'AntiAlias'
$g.TextRenderingHint = 'ClearTypeGridFit'

$gold = [System.Drawing.ColorTranslator]::FromHtml("#D4A94A")

$bgRect = New-Object System.Drawing.Rectangle(0, 0, $W, $H)
$bgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    $bgRect,
    [System.Drawing.ColorTranslator]::FromHtml("#0B0B0D"),
    [System.Drawing.ColorTranslator]::FromHtml("#191820"),
    [System.Drawing.Drawing2D.LinearGradientMode]::Horizontal)
$g.FillRectangle($bgBrush, $bgRect)

$grid = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(12, 255, 255, 255), 1)
for ($x = 0; $x -lt $W; $x += 32) { $g.DrawLine($grid, $x, 0, $x, $H) }
for ($y = 0; $y -lt $H; $y += 32) { $g.DrawLine($grid, 0, $y, $W, $y) }

# Motif jalur sirkuit di sisi kanan
$trace = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(70, 212, 169, 74), 2)
$pad   = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(120, 212, 169, 74))
$paths = @(
    @(760, 70, 900, 70, 900, 150, 1020, 150),
    @(820, 250, 960, 250, 960, 190, 1100, 190),
    @(1040, 60, 1160, 60, 1160, 120),
    @(880, 300, 1080, 300, 1080, 240, 1210, 240),
    @(1120, 150, 1220, 150)
)
foreach ($p in $paths) {
    for ($i = 0; $i -lt ($p.Count - 2); $i += 2) {
        $g.DrawLine($trace, $p[$i], $p[$i + 1], $p[$i + 2], $p[$i + 3])
    }
    $g.FillEllipse($pad, ($p[0] - 4), ($p[1] - 4), 8, 8)
    $g.FillEllipse($pad, ($p[$p.Count - 2] - 4), ($p[$p.Count - 1] - 4), 8, 8)
}

# Chip di kanan
$chipPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(150, 212, 169, 74), 2)
$g.DrawRectangle($chipPen, 985, 130, 90, 90)
$fChip = New-Object System.Drawing.Font("Consolas", 11, [System.Drawing.FontStyle]::Bold)
$g.DrawString("ESP32", $fChip, (New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(190, 212, 169, 74))), 1000, 168)

# Teks kiri
$fName = New-Object System.Drawing.Font("Segoe UI Semibold", 44, [System.Drawing.FontStyle]::Bold)
$fRole = New-Object System.Drawing.Font("Segoe UI", 18, [System.Drawing.FontStyle]::Regular)
$fSub  = New-Object System.Drawing.Font("Consolas", 13, [System.Drawing.FontStyle]::Regular)

$bWhite = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(246, 246, 248))
$bMuted = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(168, 168, 176))
$bGold  = New-Object System.Drawing.SolidBrush($gold)

$x = 70
$g.DrawString("Rayendra Aldo Putra", $fName, $bWhite, ($x - 5), 74)
$g.FillRectangle($bGold, $x, 176, 60, 3)
$g.DrawString("Teknik Robotika - Politeknik Negeri Batam", $fRole, $bMuted, $x, 202)
$g.DrawString("firmware  /  backend  /  dashboard  /  mobile", $fSub, $bGold, $x, 244)

$dir = Split-Path $Output -Parent
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
$bmp.Save($Output, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bmp.Dispose()
Write-Host "Tersimpan: $Output ($([math]::Round((Get-Item $Output).Length/1KB)) KB)"
