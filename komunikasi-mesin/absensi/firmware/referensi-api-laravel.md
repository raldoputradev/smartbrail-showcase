# API Laravel — mesin absensi + fingerprint

Base: `http://IP_PC/Simalas/public/api`  
Semua request butuh header:

```
X-API-KEY: <api_key plain dari admin Mesin IoT>
X-ID-MESIN: MESIN_ABSENSI_01
```

Atau `id_mesin` di query/body JSON.

## 1. Heartbeat

```
GET|POST /mesin/heartbeat
```

```json
{ "id_mesin": "MESIN_ABSENSI_01" }
```

## 2. Absen

```
POST /mesin/tap
```

```json
{ "id_mesin": "MESIN_ABSENSI_01", "finger_id": 5 }
```

Wajib mahasiswa **sudah enroll** di DB. Jika belum:

```json
{
  "success": false,
  "message": "Sidik jari belum enroll. Scan enroll di mesin terlebih dahulu (menu e).",
  "code": "BELUM_ENROLL"
}
```

Jadwal absensi: menu admin **Atur Absensi**.

## 3. Cari mahasiswa (sebelum enroll)

```
GET /mesin/fingerprint/resolve?q=5&id_mesin=MESIN_ABSENSI_01
```

`q` = **finger_id numerik** (1–150), bukan NIM.

## 4. Daftar mahasiswa

```
GET /mesin/fingerprint/mahasiswa?id_mesin=MESIN_ABSENSI_01&limit=100
```

## 5. Konfirmasi enroll (setelah simpan di sensor)

```
POST /mesin/fingerprint/enroll
```

```json
{
  "id_mesin": "MESIN_ABSENSI_01",
  "finger_id": 5,
  "template_hex": "<1024 karakter hex = 512 byte template DY50>"
}
```

Alternatif: `template_base64` atau `template`. Wajib blob 512 byte — disimpan terenkripsi di `users.finger_template` (backup + sync ke mesin lain).

## 5b. Sync template ke sensor (boot + periodik firmware)

```
GET /mesin/fingerprint/sync?id_mesin=MESIN_ABSENSI_01&limit=5&after_finger_id=0
GET /mesin/fingerprint/sync-status?id_mesin=MESIN_ABSENSI_01
```

Query opsional: `since` (ISO8601 delta), `after_finger_id` (paginasi batch untuk ESP32).

Respons `fingerprints[]` berisi `template_hex` per `finger_id`. Firmware: jika slot kosong di sensor → `downloadModel` + `storeModel`.

## 6. Batalkan enroll 1 slot

```
DELETE /mesin/fingerprint/5
```

Body opsional:

```json
{ "id_mesin": "MESIN_ABSENSI_01" }
```

→ Null-kan `finger_template` di DB. Hapus template di sensor via firmware perintah `d`.

## Hapus sidik jari

| Lapisan | Cara |
|---------|------|
| Sensor 1 slot | Serial `d` → finger_id |
| Sensor semua | Serial `4` → ketik `KOSONGKAN` |
| Database | Admin → Fingerprint → reset enroll, atau `DELETE` API di atas |

## Cek hasil

| Tempat | Yang dicek |
|--------|------------|
| Admin | Fingerprint enroll, log absensi, monitoring mesin online |
| App mahasiswa | Profil: Finger ID, status "Sudah Enroll", riwayat absensi |
| Dosen | Tidak absen sidik jari |
