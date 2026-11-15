# Menyalin Simalas ke folder showcase publik TANPA data nyata dan tanpa kredensial.
# Jalankan ulang setiap kali kode berubah, lalu commit di folder target.
#
#   powershell -ExecutionPolicy Bypass -File .\export-showcase.ps1
#
# Yang sengaja TIDAK ikut: .env, database/backups, public/uploads, public/downloads,
# scripts/ (deploy VPS), tools/, google-services.json, firebase_options.dart, vendor, node_modules.

param(
    [string]$Source = "C:\xampp\htdocs\Simalas",
    [string]$Target = "C:\xampp\htdocs\simalas-showcase"
)

$ErrorActionPreference = "Stop"

function Copy-Tree {
    param([string]$From, [string]$To, [string[]]$ExcludeDirs = @(), [string[]]$ExcludeFiles = @())

    if (-not (Test-Path $From)) { Write-Host "  lewat (tidak ada): $From"; return }

    $args = @($From, $To, "/E", "/NFL", "/NDL", "/NJH", "/NJS", "/NP", "/R:1", "/W:1")
    if ($ExcludeDirs.Count) { $args += "/XD"; $args += $ExcludeDirs }
    if ($ExcludeFiles.Count) { $args += "/XF"; $args += $ExcludeFiles }

    robocopy @args | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy gagal untuk $From (kode $LASTEXITCODE)" }
    Write-Host "  ok: $(Split-Path $From -Leaf)"
}

Write-Host "Sumber : $Source"
Write-Host "Target : $Target`n"

# Berkas yang hanya ada di repo showcase (tidak ada di proyek asli) dan harus selamat
$keep = @("README.md", "LICENSE", ".gitignore", ".env.example", "docs", ".github",
          "Dockerfile", "compose.yaml", ".dockerignore", "docker",
          "database\seeders\DemoSeeder.php", "database\seeders\DatabaseSeeder.php")

$stash = Join-Path $env:TEMP "showcase-keep-$(Get-Date -Format yyyyMMddHHmmss)"
if (Test-Path $Target) {
    New-Item -ItemType Directory -Path $stash -Force | Out-Null
    foreach ($item in $keep) {
        $from = Join-Path $Target $item
        if (-not (Test-Path $from)) { continue }
        $to = Join-Path $stash $item
        New-Item -ItemType Directory -Path (Split-Path $to -Parent) -Force | Out-Null
        Copy-Item $from $to -Recurse -Force
    }
    Get-ChildItem $Target -Force | Where-Object { $_.Name -ne ".git" } | Remove-Item -Recurse -Force
} else {
    New-Item -ItemType Directory -Path $Target | Out-Null
}

Write-Host "Menyalin kode:"
Copy-Tree "$Source\app" "$Target\app"
Copy-Tree "$Source\bootstrap" "$Target\bootstrap" -ExcludeDirs @("cache")
Copy-Tree "$Source\config" "$Target\config"
Copy-Tree "$Source\database\migrations" "$Target\database\migrations"
Copy-Tree "$Source\database\factories" "$Target\database\factories"
New-Item -ItemType Directory -Path "$Target\database\seeders" -Force | Out-Null
if (Test-Path "$Source\database\seeders\RolePermissionSeeder.php") {
    Copy-Item "$Source\database\seeders\RolePermissionSeeder.php" "$Target\database\seeders\RolePermissionSeeder.php" -Force
}
Copy-Tree "$Source\resources" "$Target\resources"
Copy-Tree "$Source\routes" "$Target\routes"
Copy-Tree "$Source\tests" "$Target\tests"

# Dokumentasi kontrak API mesin + firmware. Folder uploads hanya struktur, bukan log nyata.
Copy-Tree "$Source\komunikasi-mesin" "$Target\komunikasi-mesin" `
    -ExcludeFiles @("contoh-log-sudah-diisi.json")

# public: hanya aset statis. APK, bukti foto, dan hasil build tidak ikut.
Copy-Tree "$Source\public\css" "$Target\public\css"
Copy-Tree "$Source\public\js" "$Target\public\js"
Copy-Tree "$Source\public\images" "$Target\public\images"
Copy-Tree "$Source\public\branding" "$Target\public\branding"
foreach ($f in @("index.php", ".htaccess", "robots.txt", "favicon.ico")) {
    if (Test-Path "$Source\public\$f") { Copy-Item "$Source\public\$f" "$Target\public\$f" -Force }
}

# Flutter: kode saja, tanpa artefak build dan tanpa konfigurasi Firebase
Copy-Tree "$Source\simalas_mobile\lib" "$Target\simalas_mobile\lib" -ExcludeFiles @("firebase_options.dart")
Copy-Tree "$Source\simalas_mobile\assets" "$Target\simalas_mobile\assets"
Copy-Tree "$Source\simalas_mobile\test" "$Target\simalas_mobile\test"
Copy-Tree "$Source\simalas_mobile\android" "$Target\simalas_mobile\android" `
    -ExcludeDirs @("build", ".gradle", ".cxx", ".kotlin") `
    -ExcludeFiles @("google-services.json", "key.properties", "*.jks", "*.keystore", "local.properties")
foreach ($f in @("pubspec.yaml", "pubspec.lock", "analysis_options.yaml", ".metadata", ".gitignore", "README.md")) {
    if (Test-Path "$Source\simalas_mobile\$f") { Copy-Item "$Source\simalas_mobile\$f" "$Target\simalas_mobile\$f" -Force }
}

foreach ($f in @("artisan", "composer.json", "composer.lock", "package.json", "package-lock.json",
                 "vite.config.js", "phpunit.xml", ".editorconfig", ".gitattributes")) {
    if (Test-Path "$Source\$f") { Copy-Item "$Source\$f" "$Target\$f" -Force }
}

# storage: hanya kerangka folder
Write-Host "`nMenyiapkan kerangka storage:"
foreach ($dir in @("storage\app\private", "storage\app\public", "storage\framework\cache\data",
                   "storage\framework\sessions", "storage\framework\testing", "storage\framework\views",
                   "storage\logs", "bootstrap\cache", "public\uploads")) {
    New-Item -ItemType Directory -Path "$Target\$dir" -Force | Out-Null
    New-Item -ItemType File -Path "$Target\$dir\.gitkeep" -Force | Out-Null
}
Write-Host "  ok"

# Kembalikan berkas khas showcase yang tadi diselamatkan
if (Test-Path $stash) {
    Write-Host "`nMengembalikan berkas showcase:"
    foreach ($item in $keep) {
        $from = Join-Path $stash $item
        if (-not (Test-Path $from)) { continue }
        $to = Join-Path $Target $item
        New-Item -ItemType Directory -Path (Split-Path $to -Parent) -Force | Out-Null
        Copy-Item $from $to -Recurse -Force
        Write-Host "  ok: $item"
    }
    Remove-Item $stash -Recurse -Force
}

# Ganti nilai nyata yang tertulis langsung di kode dengan placeholder
Write-Host "`nMembersihkan nilai bawaan:"
$patches = @(
    @{ File = "komunikasi-mesin\loker\firmware\loker.ino"
       Map  = @{ 'const char* ssid = "Tes";' = 'const char* ssid = "GANTI_DENGAN_SSID_WIFI";'
                 'const char* password = "11223344";' = 'const char* password = "GANTI_DENGAN_PASSWORD_WIFI";' } },
    @{ File = "app\Console\Commands\TestEmail.php"
       Map  = @{ "'aiotsimalass@gmail.com'" = "'tujuan@example.test'" } }
)

foreach ($patch in $patches) {
    $path = Join-Path $Target $patch.File
    if (-not (Test-Path $path)) { continue }

    $text = Get-Content $path -Raw
    $changed = $false
    foreach ($pair in $patch.Map.GetEnumerator()) {
        if ($text.Contains($pair.Key)) {
            $text = $text.Replace($pair.Key, $pair.Value)
            $changed = $true
        }
    }
    if ($changed) {
        Set-Content -Path $path -Value $text -NoNewline
        Write-Host "  ok: $($patch.File)"
    }
}

# Pemeriksaan akhir: pastikan tidak ada data atau kredensial yang lolos
Write-Host "`nPemeriksaan:"
$forbidden = @(".env", "database\backups", "public\downloads",
               "simalas_mobile\android\app\google-services.json",
               "simalas_mobile\lib\firebase_options.dart", "scripts", "tools")
$leak = $false
foreach ($p in $forbidden) {
    if (Test-Path "$Target\$p") { Write-Host "  BOCOR: $p" -ForegroundColor Red; $leak = $true }
}
$dumps = Get-ChildItem $Target -Recurse -File -Include *.sql, *.sqlite, *.apk -ErrorAction SilentlyContinue
foreach ($d in $dumps) { Write-Host "  BOCOR: $($d.FullName)" -ForegroundColor Red; $leak = $true }
if (-not $leak) { Write-Host "  bersih: tidak ada dump, APK, .env, atau kunci" -ForegroundColor Green }

$size = (Get-ChildItem $Target -Recurse -File -Force | Where-Object { $_.FullName -notmatch "\\\.git\\" } | Measure-Object Length -Sum).Sum
Write-Host "`nUkuran hasil: $([math]::Round($size/1MB,1)) MB"
Write-Host "Selesai. Cek 'git status' di $Target sebelum commit."
