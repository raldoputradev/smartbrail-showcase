@echo off
title SIMALAS Scheduler (Kamis)
cd /d "%~dp0"
echo SIMALAS - menjalankan scheduler absensi (alfa / tutup sesi)
echo Pastikan XAMPP Apache sudah jalan.
echo Tutup jendela ini = scheduler berhenti.
echo.
php artisan schedule:work
