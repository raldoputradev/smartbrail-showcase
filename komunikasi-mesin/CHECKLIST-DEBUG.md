# Checklist debug komunikasi mesin



Centang dari atas ke bawah saat tap / request gagal.



## Umum (semua jenis mesin)



- [ ] Mesin terdaftar di admin **Mesin IoT**, `is_active` = aktif

- [ ] `tipe` mesin adalah `absensi`

- [ ] Header `X-API-KEY` terkirim (bukan typo / key lama setelah reset)

- [ ] Header `X-ID-MESIN` atau `id_mesin` di body/query **sama persis** dengan database

- [ ] Server bisa dijangkau dari ESP32 (IP/port, firewall, HTTPS jika dipakai)



## Absensi (`POST /api/mesin/tap`)



- [ ] Body: `id_mesin`, `finger_id` (integer ≥ 1)

- [ ] Mahasiswa punya `finger_id` yang sama di database

- [ ] Mahasiswa **sudah enroll** (blob template 512 byte di DB, bukan placeholder lama) — jika belum: respons `403` + code `BELUM_ENROLL`

- [ ] Jadwal absensi dari menu **Atur Absensi** (shift pagi/malam, jam masuk/pulang, libur)

- [ ] Respons 404 → finger tidak terdaftar

- [ ] Respons 403 + code `HARI_LIBUR` / `BELUM_WAKTU_MASUK` / `SESI_DITUTUP` → di luar jendela sesi absensi

- [ ] Heartbeat `GET|POST /api/mesin/heartbeat` jalan agar mesin tampil online di monitoring



## Enroll absensi



- [ ] `GET /api/mesin/fingerprint/resolve?q=<finger_id>` — `q` harus **angka finger_id**, bukan NIM

- [ ] Scan 2× di sensor → `POST /api/mesin/fingerprint/enroll` dengan `{ "finger_id": N, "id_mesin": "...", "template_hex": "..." }` (1024 hex = 512 byte)
- [ ] Mesin absensi lain menarik template lewat `GET /api/mesin/fingerprint/sync`

- [ ] Hapus 1 slot: firmware perintah `d` → `DELETE /api/mesin/fingerprint/{finger_id}` + hapus di sensor



## Setelah perbaikan



- [ ] Simpan log final ke `uploads/archive/`

- [ ] Update contoh JSON di subfolder jika format API berubah

