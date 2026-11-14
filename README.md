<p align="center">
  <img src="public/branding/app-logo.png" width="110" alt="SmartBrail">
</p>

<h1 align="center">SmartBrail</h1>

<p align="center">
  Sistem manajemen laboratorium pintar — absensi sidik jari ESP32, akses ruangan IoT,<br>
  manajemen PBL, dan portal multi-peran untuk BRAIL, Politeknik Negeri Batam.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black" alt="React">
  <img src="https://img.shields.io/badge/Inertia.js-9553E9" alt="Inertia">
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Reverb-realtime-0ea5e9" alt="Reverb">
</p>

---

## Tentang

SmartBrail adalah platform operasional laboratorium yang menyatukan **hardware IoT (ESP32 + sensor sidik jari)** dengan **portal web dan aplikasi mobile**. Sistem menangani absensi biometrik harian, kontrol akses ruangan berbasis jadwal/izin, pengelolaan tim Project-Based Learning (PBL), serta pelaporan periodik untuk empat peran pengguna.

## Fitur Utama

| Modul | Ringkasan |
|---|---|
| **Absensi biometrik** | Tap sidik jari di mesin ESP32, sinkronisasi real-time, deteksi terlambat/pulang cepat, penilaian lengkap–tidak lengkap–alfa |
| **Akses ruangan** | Permintaan akses oleh mahasiswa/tim PBL & cleaning service, persetujuan laboran, audit setiap percobaan buka pintu, akses bebas untuk staf |
| **Manajemen PBL** | Proyek tim dengan manpro/co-manpro, ketua & anggota, foto/deskripsi, jejak audit append-only (`pbl_activity_logs`) |
| **Laporan periodik** | Rekap rentang tanggal profesional (Word/Excel) sesuai template, ekspor untuk admin, laboran, dan dosen ter-scope |
| **Portal multi-peran** | Admin · Laboran · Dosen · Mahasiswa — masing-masing dengan dashboard, izin, dan asisten AI sendiri |
| **Realtime & monitoring** | Laravel Reverb (WebSocket) untuk notifikasi live, Pusat Kendali admin (pengguna online, mesin down, antrian approval, aktivitas PBL), presence heartbeat |
| **Asisten AI** | Copilot kontekstual per peran (Gemini + fallback lokal), chatbot mahasiswa toleran typo/slang dengan dashboard pelatihan |
| **Import akun** | Template Excel multi-sheet per peran (laboran, mahasiswa, dosen, cleaning service), kartu kredensial sekali-tampil |

## Peran Pengguna

- **Admin** — manajemen akun/import, kebijakan, konfigurasi ruangan & perangkat, Pusat Kendali.
- **Laboran** — persetujuan ruangan, jadwal & aturan, operasi fingerprint, laporan, status perangkat.
- **Dosen** — rekap absensi ter-scope, supervisi kelas/tim PBL, pengumuman, feedback.
- **Mahasiswa** — absensi & jadwal pribadi, profil, laporan, permintaan ruangan tim PBL.
- **Cleaning Service** — non-portal; jadwal akses disinkron ke mesin.

## Stack

**Backend:** Laravel 12 (PHP 8.3) · MySQL · Laravel Reverb (WebSocket) · Sanctum (API mobile)
**Frontend:** Inertia.js + React 19 + TypeScript · Vite · Tailwind · Blade (legacy shells)
**IoT/Mobile:** ESP32 + sensor sidik jari · REST API ter-autentikasi token perangkat · Flutter app
**AI:** Google Gemini (streaming) + toolchain lokal & fallback

## Struktur Proyek

```
app/            Kode backend (controllers, services, models, support)
resources/js/   Shell React per peran (admin, dosen, student, public-site)
resources/views/ Blade legacy & template laporan cetak
routes/         web.php · api.php · channels.php (broadcast Reverb)
docs/agent/     Dokumentasi agent: current-state, architecture, decisions
public/js,css/  Aset shell Blade & chart
```

## Kolaborasi

Repo kerja bersifat **private** — kontribusi melalui fork + pull request (lihat [CONTRIBUTING.md](CONTRIBUTING.md)). Histori commit di repo ini menampilkan progres pengerjaan nyata.

---

<p align="center"><sub>BRAIL — Barelang Robotics and Artificial Intelligence Lab, Politeknik Negeri Batam</sub></p>
