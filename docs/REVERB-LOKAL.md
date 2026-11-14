# Reverb (WebSocket) lokal — SIMALAS

Reverb hanya **membangunkan** halaman absensi staf (log, rekap/alfa/tidak lengkap, fingerprint) agar mengambil snapshot JSON terbaru. Data tetap diambil lewat endpoint snapshot yang sama dengan polling. Jika Reverb mati, absensi mesin tetap berjalan dan halaman otomatis kembali ke polling.

## 1. Proses yang dibutuhkan

| Proses | Wajib? | Keterangan |
|---|---|---|
| Apache + MySQL XAMPP | Ya | Web dan API mesin seperti biasa. |
| `artisan reverb:start` | Untuk mode Soket | Tanpa proses ini halaman tetap jalan dengan polling. |
| Queue worker | Tidak | Event memakai `ShouldBroadcastNow` setelah commit; tidak lewat antrean. |
| Vite / `npm run build` | Tidak | Klien memakai `public/js/simalas-live.js` dan `public/js/simalas-snapshot.js` (statis, tanpa Echo/pusher-js). |

Reverb **wajib** dijalankan dengan PHP 8.3, bukan `php` di PATH (XAMPP 8.2):

```powershell
cd C:\xampp\htdocs\Simalas
C:\tools\php83\php.exe artisan reverb:start --host=127.0.0.1 --port=8080
```

Tambahkan `--debug` untuk melihat koneksi/subscribe di terminal.

**Berhenti:** tekan `Ctrl+C` di terminal Reverb. Bila terminal sudah tertutup atau proses berjalan di latar, hentikan proses `php.exe` pemilik port (menghentikan jendela PowerShell pembungkusnya saja tidak cukup):

```powershell
$id = (Get-NetTCPConnection -LocalPort 8080 -State Listen).OwningProcess
Get-CimInstance Win32_Process -Filter "ProcessId=$id" | Select-Object ProcessId, CommandLine   # pastikan reverb:start
Stop-Process -Id $id
```

Reverb bukan service Windows; setelah restart PC perlu dijalankan lagi.

## 2. Port, bind, dan host

| Peran | Kunci | Nilai lokal saat ini | Dipakai oleh |
|---|---|---|---|
| Bind server | `REVERB_SERVER_HOST` / `--host` | `127.0.0.1` | Reverb hanya mendengar di loopback PC server. |
| Port server | `REVERB_SERVER_PORT` / `--port` | `8080` | WebSocket browser **dan** HTTP API broadcast backend (port sama). |
| Host backend | `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | `127.0.0.1`, `8080`, `http` | Laravel mengirim event (`POST /apps/{id}/events`) ke alamat ini. |
| Host frontend | (sama: `REVERB_HOST/PORT/SCHEME`) | `ws://127.0.0.1:8080` | Dirender ke halaman oleh `resources/views/absensi/_snapshot_boot.blade.php`. |
| Timeout backend | `REVERB_CONNECT_TIMEOUT`, `REVERB_TIMEOUT` | default `1` dan `2` detik (di `config/broadcasting.php`, tidak perlu di `.env`) | Batas tunggu broadcast setelah tap. |

Konsekuensi konfigurasi saat ini: **mode Soket hanya aktif untuk browser di PC server itu sendiri** (`http://localhost/...`). Browser dari PC/HP lain di LAN akan mencoba `ws://127.0.0.1:8080` miliknya sendiri, gagal, lalu memakai **Polling** — fungsi tetap benar, hanya tidak instan.

`.env` tidak boleh menyimpan nilai rahasia di dokumen ini. `REVERB_APP_KEY` bersifat publik (dikirim ke browser); `REVERB_APP_SECRET` hanya di server dan tidak pernah dirender.

## 3. allowed_origins

`config/reverb.php` saat ini memakai `'allowed_origins' => ['*']`. Selama bind `127.0.0.1`, hanya proses di PC server yang bisa menjangkau port 8080, sehingga risikonya rendah. Kanal tetap **private**: subscribe butuh tanda tangan dari `/broadcasting/auth` yang memeriksa sesi login dan peran (laboran/dosen), dan payload hanya berisi `delivery_id`, `surfaces`, `committed_at`, `event_id`.

Sebelum membuka ke LAN, `allowed_origins` sebaiknya dibatasi ke host halaman SIMALAS (lihat bagian 6). Reverb hanya mencocokkan **host** dari header `Origin` (tanpa skema/port) dan mendukung pola `*`, mis. `['localhost', '127.0.0.1', '192.168.1.10']`.

## 4. Perilaku saat Reverb mati atau macet

- Tap mesin tetap diproses; respons HTTP mengikuti hasil absensi (bukan 500), receipt dan replay tetap idempoten.
- Broadcast pertama yang gagal menambah paling lama sekitar 2,6 detik (uji soket macet); firmware memakai timeout HTTP 5 detik.
- Setelah gagal, broadcast dijeda 30 detik (tap berikutnya tidak menunggu soket sama sekali).
- `storage/logs/laravel.log` mencatat paling banyak satu peringatan per 5 menit: `Soket Reverb tidak terjangkau; halaman absensi memakai polling sampai tersambung lagi.` Saat pulih: `Soket Reverb tersambung kembali.` Pesan log tidak memuat tanda tangan/kunci Reverb.
- Halaman berpindah ke Polling tanpa reload; mencoba tersambung lagi dengan jeda 1, 2, 4, lalu 8 detik. Begitu tersambung, halaman mengambil satu snapshot dan kembali ke Soket.

## 5. Cara memastikan halaman benar-benar memakai Soket

1. **Label status** di atas tabel: `Soket · Diperbarui HH:MM:SS` (bukan `Polling · …`).
2. **Atribut DOM**: di Console DevTools jalankan
   `document.querySelector('[data-transport]').dataset.transport` → harus `socket`.
3. **Network DevTools**:
   - filter `WS`: ada koneksi `ws://127.0.0.1:8080/app/...` berstatus `101`, frame berisi `pusher:connection_established` lalu `pusher_internal:subscription_succeeded`;
   - ada `POST /broadcasting/auth` berstatus `200`.
   Bila yang terlihat hanya request snapshot berkala tanpa koneksi WS, halaman sedang Polling.
4. Terminal `reverb:start --debug` menampilkan koneksi dan subscribe kanal `private-absensi.staff.*`.

## 6. Konfigurasi LAN (BELUM diterapkan — perlu persetujuan)

Kondisi terbaca 2026-10-09 (baca-saja): Wi-Fi `10.20.3.246/22` (DHCP, bisa berubah), profil jaringan Windows **Public**. Aturan inbound "Apache HTTP Server" yang sudah ada berlaku di profil Public — itu jalur mesin/HP ke Apache port 80 saat ini.

Agar browser lain di jaringan lokal juga memakai Soket:

| Bagian | Sekarang | Usulan LAN |
|---|---|---|
| Bind Reverb | `127.0.0.1:8080` | `0.0.0.0:8080` (IP DHCP bisa berubah; pembatasan dilakukan firewall + origin) |
| Host backend (Laravel → Reverb) | `REVERB_HOST=127.0.0.1` | tetap `127.0.0.1` (broadcast tidak lewat jaringan) |
| Host frontend (browser → Reverb) | ikut `REVERB_HOST` (`127.0.0.1`) | host halaman yang sedang dibuka (`window.location.hostname`), port/skema tetap dari config — perlu perubahan kecil di `_snapshot_boot.blade.php` |
| `allowed_origins` | `['*']` | `['localhost', '127.0.0.1', '10.20.3.246']` (sesuaikan bila IP berubah) |

Firewall — dua pilihan, keduanya **belum diterapkan**:

- **Pilihan F1** (bila jaringan ini memang jaringan lab/hotspot tepercaya): ubah profil Wi-Fi menjadi *Private*, lalu
  `New-NetFirewallRule -DisplayName "SIMALAS Reverb 8080 (LAN)" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8080 -Program "C:\tools\php83\php.exe" -Profile Private -RemoteAddress LocalSubnet`.
  Catatan: profil Private juga mengubah perilaku Windows lain di jaringan itu (network discovery/sharing); aturan Apache yang ada berprofil Public, jadi perlu dicek ulang agar mesin tetap terjangkau.
- **Pilihan F2** (profil tetap Public): aturan yang sama dengan `-Profile Public` dan `-RemoteAddress` berisi IP perangkat staf tertentu saja (bukan seluruh `/22` kampus).

Tidak ada port forwarding router, tidak ada akses internet, firewall tidak dimatikan.

## 7. Pengujian terkait

| Perintah | Isi |
|---|---|
| `C:\tools\php83\php.exe vendor\bin\phpunit` | Full suite (SQLite memori), termasuk `AbsensiBroadcastIsolationTest`. |
| `C:\tools\php83\php.exe tests\Support\run_reverb_test.php` | Otorisasi kanal, rollback/commit, payload, replay (DB uji `simalas_cleanup_test`). |
| `C:\tools\php83\php.exe tests\Support\run_reverb_failure_test.php mati` | Tap HTTP nyata saat port 8080 tertutup (DB uji). |
| `C:\tools\php83\php.exe tests\Support\run_reverb_failure_test.php hidup` | Sama, saat Reverb berjalan; memeriksa catatan pulih. |
| `C:\tools\php83\php.exe tests\Support\run_reverb_failure_test.php lambat` | Soket macet di `127.0.0.1:8097` (perlu listener uji yang menerima koneksi tanpa membalas). |

Semua skrip uji menolak berjalan bila target bukan MySQL lokal `simalas_cleanup_test`.
