# Absensi — komunikasi mesin

## Endpoint

`POST /api/mesin/tap`

**Controller:** `App\Http\Controllers\Api\MesinAbsensiController::tap`

## Request

| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| `id_mesin` | string | ya | ID unik mesin tipe `absensi` |
| `finger_id` | integer | ya | ID sidik jari di sensor / database user |

**Header wajib:** `X-API-KEY`

## Respons umum

| HTTP | `success` | Arti |
|------|-----------|------|
| 200 | true | Log absensi tersimpan |
| 401 | false | API key tidak dikirim |
| 403 | false | Mesin nonaktif / key salah / di luar jadwal |
| 404 | false | `finger_id` tidak ada di database |

## Firmware Arduino

Folder **`firmware/`** — upload sketch **`contoh-skeleton-minimal/contoh-skeleton-minimal.ino`** ke Arduino IDE.

- `firmware/referensi-api-server.md` — kontrak API lengkap
- `firmware/contoh-skeleton-minimal/` — sketch utama (OLED, keypad, offline, NTP)

## File contoh API

- `contoh-request.json` — body yang dikirim ESP32
- `contoh-response-berhasil.json`
- `contoh-response-gagal-jadwal.json`
- `tes-endpoint.http` — tes dari editor

## Side effect server

- `mesin_iot.last_seen_at` di-update (heartbeat)
- Baris baru di tabel `log_absensi`
