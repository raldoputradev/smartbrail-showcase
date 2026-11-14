# Contributing — SmartBrail

## Alur kerja
1. Fork repo ini (private) ke akun Anda.
2. Buat branch: `feat/nama-fitur` atau `fix/nama-bug`.
3. Commit dengan prefix: `feat:` / `fix:` / `ui:` / `docs:` / `chore:` — Bahasa Indonesia, singkat.
4. Buka Pull Request ke `main` milik `raldoputradev/smartbrail`.
5. Tunggu review & approval pemilik — jangan push langsung ke `main`.

## Aturan
- Jangan commit `.env`, kredensial, data akun asli, template fingerprint, atau file APK privat.
- `main` = stabil; semua perubahan lewat PR.
- Perubahan yang menyentuh absensi, akses ruangan, PBL, atau kredensial wajib lolos php -l + build.
- Audit log (`pbl_activity_logs`, ruangan access events) bersifat append-only — jangan tambahkan kemampuan hapus/edit jejak.
