# Endpoint mesin lain (cadangan)

Tempat dokumentasi jika nanti ada tipe mesin / channel baru, misalnya:

- Sensor lingkungan lab
- Pintu akses kedua
- Webhook status relay

## Template tambah endpoint baru

1. Buat subfolder `lainnya/nama-fitur/`
2. Salin pola dari `absensi/`:
   - `README.md` (method, path, field)
   - `contoh-request.json`
   - `contoh-response-berhasil.json`
   - `tes-endpoint.http`
3. Daftarkan route di `routes/api.php`
4. Update tabel di `komunikasi-mesin/README.md`

## Log upload

Tetap pakai `template-log-upload.json` dengan `jenis` custom, misal `"jenis": "sensor-suhu"`.
