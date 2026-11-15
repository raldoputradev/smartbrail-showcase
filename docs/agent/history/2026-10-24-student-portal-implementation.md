# Histori: Implementasi Website/Backend dan Portal Mahasiswa

Waktu: 2026-10-24, checkpoint 16:19 +07.

## Pengamanan

- Database aktual: `simalas`, 36 tabel InnoDB.
- Snapshot kode: `C:\xampp\mysql-backups\code-baseline-20261024-155140`, hash seluruh 525 file cocok.
- Git di-init dan 526 file aman staged setelah peninjauan, tetapi tidak di-commit karena identitas tidak tersedia.
- Backup: `C:\xampp\mysql-backups\operational-20261024-155335`; restore `simalas_restore_20261024_155335` terverifikasi pada 36 definisi dan 36 jumlah baris tabel.
- Dua migration diterapkan pada operasional: `no_hp` batch 48 dan `account_verify` batch 49. Seluruh jumlah baris non-repository tetap sesuai.

## Implementasi

- Hasil import menampilkan status distribusi kode dan tidak ada undangan otomatis. Importer existing tetap menjadi satu jalur CSV/XLSX dengan preview, duplikasi, kontak kosong, dan idempotensi.
- Portal mahasiswa dibuat dengan session web khusus, `StudentAuthController`, dan `StudentPortalController`. Payload absensi/PBL/notifikasi memakai service/controller existing.
- Tersedia beranda, absensi sendiri, profil/foto, tim PBL, notifikasi, enrollment status, dan saran bernomor referensi dengan balasan/read state.
- Rekap/ekspor staf tetap memakai sumber existing. API mesin, Flutter, dan endpoint mobile tidak diubah kontraknya. Tidak ada schema tambahan setelah dua migration yang disetujui.

## Verifikasi aktual

- `AccountImportActivationTest`: 12 tests/304 assertions.
- `StudentPortalTest`: 8 tests/64 assertions, termasuk role, isolasi dua mahasiswa, rekap/ekspor, dan saran.
- `MesinApiAuthTest`: 4 tests/6 assertions.
- `AbsensiTapIdempotencyTest`: 11 tests/120 assertions.
- Seluruh suite setelah perubahan: **74 tests/1033 assertions — OK**.

Test memakai SQLite memori/fixture sintetis. Email, notifikasi, HTTP keluar, Gemini, FCM, dan pekerjaan eksternal di-fake/diblokir. Tidak ada akun asli atau request jaringan.

## Batas

Browser fisik/iPhone, LAN/HTTPS/cookie, foto upload aktual, penggunaan operasional, dan email nyata belum diuji. Tidak ada deployment atau klaim production-ready. Baseline dan implementasi belum di-commit.

## Perubahan file

Source portal/routes/UI/test dan dokumentasi agent diubah/dibuat. Backup, database test/restore, dan snapshot dipertahankan. Tidak ada perubahan firmware, dependency, provider, atau layanan.
