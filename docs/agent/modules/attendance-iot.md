# Modul Absensi, Fingerprint, dan IoT

Kembali ke `../architecture.md`. Pemetaan dari kode 2026-10-24; tidak ada tap/test perangkat yang dijalankan.

## 1. Perangkat → autentikasi → tap

**Entry point:** `POST /api/mesin/tap` (`routes/api.php`) → `MesinAbsensiController::tap` → `AbsensiTapIdempotency::handle`.

1. `MesinApiAuth`/`MesinApiAuthenticator` memeriksa `X-API-KEY`, ID mesin, status aktif, dan tipe `absensi`. Header ID menang atas body; mismatch ditolak.
2. `MesinHeartbeatController`/tap/sync menyentuh `last_seen_at`; `MesinIot::online()` memakai threshold heartbeat.
3. `AbsensiTapIdempotency` memvalidasi `finger_id`, waktu opsional, dan UUID `event_id` opsional.
4. Dengan `event_id`, keputusan terminal disimpan di `tap_receipts` bersama hash payload. Replay mengembalikan keputusan yang sama; UUID dengan payload berbeda menjadi konflik; unique race dibaca ulang.
5. Tanpa `event_id`, jalur firmware lama tetap mengevaluasi tap tetapi **tidak idempoten**.

Fingerprint resolve/enroll/sync berada di `MesinFingerprintController`. Enroll menerima template biner tervalidasi, menyimpannya pada user, dan mencatat sync log. Sync/status dapat dibatasi `FingerprintSyncPolicy`; `FingerprintSyncService` membentuk delta payload. Tidak ada template aktual yang dibaca dalam pemetaan.

## 2. Evaluasi tap → log dan status harian

`AbsensiTapEvaluator::evaluate` memeriksa:

- role mahasiswa dan akun aktif;
- template valid;
- shift dari `MahasiswaShiftResolver` dan setting aktif;
- hari libur/status harian;
- tap sebelumnya dan jenis berikutnya (`masuk` lalu `pulang`);
- jendela waktu sesi.

**Masuk:** dalam transaksi, buat `log_absensi(jenis=masuk)` dengan keterlambatan/slot, lalu update `absensi_harian(status=masuk, masuk_log_id)` dan nonaktifkan alfa yang ada.

**Pulang:** buat `log_absensi(jenis=pulang)`, update status `lengkap`, waktu lengkap, keterlambatan/lembur, selesaikan `absensi_tidak_lengkap`, dan nonaktifkan alfa.

Sumber data dengan demikian bukan satu tabel: log mentah dan status harian saling berelasi melalui `masuk_log_id`/`pulang_log_id`. `absensi_alfa` dan `absensi_tidak_lengkap` menyimpan dimensi operasional/koreksi tambahan.

## 3. Penutupan sesi dan koreksi

`AbsensiSessionCloser`:

- pada/sesudah jam pulang, menandai mahasiswa tanpa tap sebagai alfa pada hari wajib;
- setelah `grace_alfa_menit`, mengubah hanya-masuk menjadi tidak-lengkap dan membuat side record;
- memakai cache penanda per shift/tanggal untuk mencegah pengulangan, kecuali force/backfill.

`bootstrap/app.php` menjadwalkan `absensi:close-sessions` tiap 15 menit, `absensi:generate-alfa` sebagai cadangan harian, heartbeat, dan prune log. Admin juga dapat memicu melalui `AbsensiManagementController`.

`AbsensiManagementController::koreksiHarian`, `batalkanAlfa`, dan `selesaiTidakLengkap` memperbarui status/side record serta metadata petugas, lalu membangunkan snapshot. Route dosen tersedia dengan permission, tetapi helper koreksi tertentu secara eksplisit hanya mengizinkan laboran; tinjau kedua lapisan sebelum mengubah akses.

**Ketidakpastian:** `GenerateAbsensiAlfa` dan `GenerateAbsensiAlfaCommand` memiliki signature Artisan sama. Resolusi command belum diperiksa.

## 4. Rekap, laporan, dan ekspor

- `AbsensiManagementController::snapshot` membangun rekap dari mahasiswa aktif, status harian, tap, filter kelas/tim/prodi/dosen, serta ringkasan/tab.
- `RekapAbsensiExportExcel` dan `RekapAbsensiExportHtml` memakai konteks rekap yang sama untuk ekspor/cetak.
- `PeriodicReportBuilder::build` mengagregasi `absensi_harian` dan `log_absensi` per periode, membuat ringkasan, grafik, rekap mahasiswa, ranking, dan detail. Controller admin/dosen dan Copilot memakai builder yang sama.
- Mobile membaca hanya log/status milik `mobile_user` dan memfilter hari terjadwal melalui `AbsensiKebijakan`.

Data dummy dashboard tidak menggantikan jalur ini. `DashboardDemoData::overlayRekap` dan `overlayPeriodic` pass-through.

## 5. Reverb dan snapshot

Setelah perubahan, `AbsensiSnapshotSignals::committed` menunda broadcast dengan `DB::afterCommit`. `AbsensiSnapshotWake` memakai `ShouldBroadcastNow` ke kanal privat laboran/dosen. Payload hanya `delivery_id`, `surfaces`, `committed_at`, `event_id`.

Browser tetap mengambil data lewat HTTP snapshot. Jika broadcast gagal, circuit breaker menjeda percobaan 30 detik dan UI menggunakan polling; kegagalan Reverb tidak membatalkan absensi. Lihat `docs/REVERB-LOKAL.md` untuk operasi existing, bukan izin menjalankannya.

## 6. Firmware dan offline queue

Skeleton ESP32 berada di `komunikasi-mesin/absensi/firmware/contoh-skeleton-minimal/` dengan OLED/keypad, NTP, WiFi, dan LittleFS offline queue maksimum 50 menurut README. Tap offline dikirim ulang dengan `waktu_tap`; enroll membutuhkan WiFi. Versi firmware fisik belum diverifikasi. Dok firmware lama perlu dibandingkan dengan dukungan `event_id` pada backend.

## 7. Dependensi lintas modul

- Akun aktif + finger allocation + enroll adalah prasyarat tap.
- `users.shift` dapat disinkronkan dari PBL; perubahan tim dapat memengaruhi sesi absensi.
- Dosen scope dan permission memengaruhi rekap/laporan.
- Copilot memakai adapter rekap/laporan yang sama, bukan database dummy.
- Notifikasi staff mengamati mesin offline, belum enroll, alfa/tidak-lengkap.
