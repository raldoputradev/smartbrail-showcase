# Referensi API absensi (Laravel SIMALAS)

## Endpoint

```
POST /api/mesin/tap
```

Base URL contoh:
- PC sama jaringan WiFi: `http://192.168.x.x:8000/api`
- Production: `https://domain-anda.com/api`

## Header wajib

| Header | Nilai |
|--------|--------|
| `X-API-KEY` | API key plain dari admin **Mesin IoT** (tipe absensi) |
| `Content-Type` | `application/json` |

Alternatif (tidak disarankan): kirim `api_key` di body — header lebih aman.

## Body JSON

```json
{
  "id_mesin": "MESIN_ABSENSI_01",
  "finger_id": 1,
  "waktu_tap": "2026-05-30T08:03:15+07:00"
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id_mesin` | string | Harus sama dengan record `mesin_iot.id_mesin`, `tipe = absensi` |
| `finger_id` | int | Harus ada di kolom `users.finger_id` |
| `waktu_tap` | string (opsional) | ISO datetime untuk tap offline yang di-sync belakangan. Max 24 jam ke belakang, max 5 menit ke depan dari waktu server. |

## Respons berhasil (HTTP 200)

```json
{
  "success": true,
  "message": "Absensi tersimpan.",
  "data": {
    "id": 42,
    "user_id": 7,
    "nama_lengkap": "...",
    "nitk_nidn": "...",
    "id_mesin": "MESIN_ABSENSI_01",
    "waktu_tap": "2026-05-15 08:15:00"
  }
}
```

## Respons gagal umum

| HTTP | Pesan singkat | Perbaikan di firmware |
|------|----------------|------------------------|
| 401 | API key wajib | Kirim header `X-API-KEY` |
| 403 | Key tidak valid / mesin nonaktif / jadwal | Cek key & jam operasional di admin |
| 404 | finger_id tidak ditemukan | Enroll / sync ID sidik jari dengan database |

## Checklist sebelum upload firmware

- [ ] ESP32 dan PC/server **satu jaringan WiFi** (atau server bisa di-ping dari ESP32)
- [ ] `id_mesin` & API key dari halaman admin **Mesin IoT**
- [ ] Mahasiswa sudah punya `finger_id` di database
- [ ] URL tidak pakai `https` tanpa sertifikat jika firmware belum support TLS (uji `http` dulu)

## Log untuk debug

Salin request/response ke `../../uploads/incoming/` — lihat `../../template-log-upload.json`.
