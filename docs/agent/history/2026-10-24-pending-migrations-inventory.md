# Histori: Inventaris Migration Pending dan Rencana Uji Terisolasi

Waktu: 2026-10-24 14:08 +07.

## Hasil inventaris

Menggunakan PHP 8.3 dan bootstrap/koneksi lokal yang sama, daftar file migration dibandingkan dengan seluruh nama/batch repository:

- 58 file migration.
- 56 catatan repository `migrations`.
- 2 pending: `2026_10_09_120000_add_no_hp_to_users_table` dan `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type`.

Tidak ada pending lain yang perlu diklasifikasikan atau disertakan otomatis.

## Dependensi dan kelayakan

- `no_hp` hanya memerlukan tabel `users`; `up()` menambah kolom nullable tanpa backfill.
- `account_verify` memerlukan `email_verification_tokens.type`; `up()` mengubah enum tanpa backfill/menghapus token lama.
- Prasyarat tabel tersedia. Tidak ada dependensi migration wajib di antara keduanya; keduanya digabungkan hanya oleh kebutuhan alur aktivasi.
- Keduanya layak diuji dengan path eksplisit pada MariaDB terisolasi, bukan pada database operasional.

`down()` keduanya dapat menolak rollback ketika data baru tersedia. Oleh karena itu rollback/transaction tidak dijadikan jaminan pemulihan schema.

## Rencana

`development-plan.md` bagian 7–8 menyimpan rancangan schema-only, database MariaDB khusus test, guard nama/host/driver, data sintetis, dua path migration, pemeriksaan preservasi, alur aktivasi, Mail::fake/broadcast null/provider nonaktif, kriteria lulus, dan pemulihan dengan membangun ulang database test saja.

Tidak ada database test, dump/import, migration, test, HTTP request, email, atau koneksi eksternal yang dijalankan pada tahap ini.

## Perubahan

Hanya `docs/agent/current-state.md`, `docs/agent/development-plan.md`, dan catatan histori ini yang diubah/dibuat. Source code, konfigurasi, database, layanan, firmware, dan Git tidak diubah.
