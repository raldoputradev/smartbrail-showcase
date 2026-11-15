# SIMALAS — daftarkan Task Scheduler Windows untuk artisan schedule:run (tiap menit)
# Jalankan PowerShell sebagai Administrator dari folder proyek:
#   Set-ExecutionPolicy -Scope Process Bypass -Force
#   .\install-simalas-scheduler.ps1

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$taskName = 'SIMALAS-Absensi-Schedule'

$phpCandidates = @(
    'C:\xampp\php\php.exe',
    (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
)
$php = $phpCandidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
if (-not $php) {
    Write-Error 'PHP tidak ditemukan. Pasang XAMPP atau tambahkan php ke PATH.'
}

$action = New-ScheduledTaskAction -Execute $php -Argument 'artisan schedule:run' -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null

Write-Host "Task '$taskName' terdaftar."
Write-Host "PHP: $php"
Write-Host "Folder: $projectRoot"
Write-Host ''
Write-Host 'Alternatif Kamis (lebih sederhana): double-click start-simalas-kamis.bat (schedule:work, biarkan jendela terbuka).'
Write-Host 'Cek kesiapan: php artisan simalas:absensi-readiness'
