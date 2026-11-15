# Komunikasi Mesin IoT — SIMALAS

Folder ini untuk **dokumentasi, contoh payload, dan arsip log** komunikasi antara **ESP32 / perangkat mesin** dengan **server Laravel**.

Gunakan saat:
- Integrasi firmware absensi
- Debug tap gagal / timeout / API key salah
- Review bersama tim atau asisten AI — cukup **upload file JSON** ke `uploads/incoming/`

## Struktur

| Folder / file | Isi |
|---------------|-----|
| `absensi/` | Endpoint tap absensi (`POST /api/mesin/tap`) + firmware `contoh-skeleton-minimal/` |
| `lainnya/` | Placeholder endpoint mesin baru nanti |
| `uploads/incoming/` | **Taruh log nyata** yang ingin dicek (format lihat `template-log-upload.json`) |
| `uploads/archive/` | Log yang sudah selesai ditinjau |
| `CHECKLIST-DEBUG.md` | Langkah cek cepat saat ada masalah |
| `template-log-upload.json` | Format standar satu sesi komunikasi |

## Autentikasi (semua endpoint mesin)

```
Header: X-API-KEY: <api_key_plain_dari_admin>
Content-Type: application/json
Base URL contoh lokal: http://127.0.0.1:8000/api
```

API key disimpan **hash** di database (`mesin_iot.api_key`). Ambil key asli dari admin **Mesin IoT** (hanya tampil saat dibuat/di-reset).

## Endpoint ringkas

| Jenis | Method | Path |
|-------|--------|------|
| Heartbeat | `GET` / `POST` | `/api/mesin/heartbeat` |
| Absensi tap | `POST` | `/api/mesin/tap` |
| Fingerprint resolve | `GET` | `/api/mesin/fingerprint/resolve?q=<finger_id>` |
| Fingerprint list | `GET` | `/api/mesin/fingerprint/mahasiswa` |
| Fingerprint enroll | `POST` | `/api/mesin/fingerprint/enroll` |
| Fingerprint hapus enroll | `DELETE` | `/api/mesin/fingerprint/{finger_id}` |
| Fingerprint sync | `GET` | `/api/mesin/fingerprint/sync` |

**Jadwal absensi:** menu Atur Absensi.

Kode sumber: `routes/api.php`, `app/Http/Middleware/MesinApiAuth.php`, controller di `app/Http/Controllers/Api/Mesin*.php`.

## Cara upload log untuk dicek

1. Salin `template-log-upload.json` → `uploads/incoming/YYYY-MM-DD-jenis-deskripsi.json`
2. Isi `request`, `response`, `http_status`, dan `catatan` (jelaskan gejala)
3. **Jangan** masukkan API key asli ke repo — ganti dengan `***` di file upload
4. Setelah selesai review, pindahkan ke `uploads/archive/`

## Tes manual

- File `*.http` di subfolder (VS Code / Cursor REST Client, atau JetBrains HTTP Client)
- Atau Postman: import body dari `contoh-*.json`
