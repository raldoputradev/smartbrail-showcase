# Membuat gambar social preview 1280x640 untuk repo GitHub.
# Dipakai di Settings -> Social preview. Sumber gambar: tangkapan layar dashboard asli.

param(
    [string]$Screenshot = "C:\xampp\htdocs\simalas-showcase\docs\screenshots\dashboard.png",
    [string]$Output     = "C:\xampp\htdocs\simalas-showcase\docs\social-preview.png"
)

$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Drawing

$W, $H = 1280, 640
$bmp = New-Object System.Drawing.Bitmap($W, $H)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'AntiAlias'
$g.InterpolationMode = 'HighQualityBicubic'
$g.TextRenderingHint = 'ClearTypeGridFit'

# Latar gelap dengan gradasi halus
$bgRect = New-Object System.Drawing.Rectangle(0, 0, $W, $H)
$bgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    $bgRect,
    [System.Drawing.ColorTranslator]::FromHtml("#0C0C0E"),
    [System.Drawing.ColorTranslator]::FromHtml("#17161A"),
    [System.Drawing.Drawing2D.LinearGradientMode]::ForwardDiagonal)
$g.FillRectangle($bgBrush, $bgRect)

# Garis grid tipis biar tidak terasa kosong
$grid = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(14, 255, 255, 255), 1)
for ($x = 0; $x -lt $W; $x += 40) { $g.DrawLine($grid, $x, 0, $x, $H) }
for ($y = 0; $y -lt $H; $y += 40) { $g.DrawLine($grid, 0, $y, $W, $y) }

$gold = [System.Drawing.ColorTranslator]::FromHtml("#D4A94A")

# Tangkapan layar di kanan, dengan bingkai tipis
$shot = [System.Drawing.Image]::FromFile($Screenshot)
$shotW = 620
$shotH = [int]($shot.Height * ($shotW / $shot.Width))
$shotX = $W - $shotW - 56
$shotY = [int](($H - $shotH) / 2)

$shadow = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(90, 0, 0, 0))
$g.FillRectangle($shadow, ($shotX + 8), ($shotY + 10), $shotW, $shotH)
$g.DrawImage($shot, $shotX, $shotY, $shotW, $shotH)
$border = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(60, 255, 255, 255), 1)
$g.DrawRectangle($border, $shotX, $shotY, $shotW, $shotH)
$shot.Dispose()

# Teks di kiri
$fKicker = New-Object System.Drawing.Font("Consolas", 13, [System.Drawing.FontStyle]::Regular)
$fTitle  = New-Object System.Drawing.Font("Segoe UI Semibold", 46, [System.Drawing.FontStyle]::Bold)
$fLead   = New-Object System.Drawing.Font("Segoe UI", 17, [System.Drawing.FontStyle]::Regular)
$fStack  = New-Object System.Drawing.Font("Consolas", 12, [System.Drawing.FontStyle]::Regular)

$bGold   = New-Object System.Drawing.SolidBrush($gold)
$bWhite  = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(245, 245, 247))
$bMuted  = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(165, 165, 172))

$x = 64
$g.DrawString("RAYENDRA ALDO PUTRA", $fKicker, $bGold, $x, 150)
$g.DrawString("AIoT SIMALAS", $fTitle, $bWhite, ($x - 5), 182)

$g.FillRectangle($bGold, $x, 268, 56, 3)

$leadRect = New-Object System.Drawing.RectangleF($x, 296, 480, 130)
$g.DrawString(
    "Absensi dan loker laboratorium berbasis sidik jari. Mesin ESP32 mencocokkan di perangkat, jadi tetap jalan saat jaringan mati.",
    $fLead, $bMuted, $leadRect)

$g.DrawString("Laravel / Blade / React / Flutter / ESP32 / MySQL", $fStack, $bMuted, $x, 452)

$dir = Split-Path $Output -Parent
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
$bmp.Save($Output, [System.Drawing.Imaging.ImageFormat]::Png)

$g.Dispose(); $bmp.Dispose()
Write-Host "Tersimpan: $Output ($([math]::Round((Get-Item $Output).Length/1KB)) KB)"
