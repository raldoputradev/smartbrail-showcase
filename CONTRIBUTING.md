# Contributing — SmartBrail

## Alur kerja
1. Terima undangan **collaborator** dari pemilik repo (repo private tidak bisa di-fork di akun personal).
2. `git pull origin main`, lalu buat branch: `feat/nama-fitur` atau `fix/nama-bug`. Setup lokal: lihat [SETUP.md](SETUP.md).
3. Commit dengan prefix: `feat:` / `fix:` / `ui:` / `docs:` / `chore:` — Bahasa Indonesia, singkat.
4. Buka Pull Request ke `main` milik `raldoputradev/smartbrail`.
5. Tunggu review & approval pemilik — jangan push langsung ke `main`.

## Aturan
- Jangan commit `.env`, kredensial, data akun asli, template fingerprint, atau file APK privat.
- `main` = stabil; semua perubahan lewat PR.
- Perubahan yang menyentuh absensi, akses ruangan, PBL, atau kredensial wajib lolos php -l + build.
- Audit log (`pbl_activity_logs`, ruangan access events) bersifat append-only — jangan tambahkan kemampuan hapus/edit jejak.
