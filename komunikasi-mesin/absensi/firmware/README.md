# Firmware absensi (Arduino IDE)

Tempat **menempel & mengoreksi** program ESP32/Arduino untuk mesin absensi.

## File utama

| File | Fungsi |
|------|--------|
| **`contoh-skeleton-minimal/contoh-skeleton-minimal.ino`** | Sketch utama — upload ini ke ESP32 |
| `contoh-skeleton-minimal/config_pins.h` | GPIO semua periferal — **sesuaikan jumper** |
| `contoh-skeleton-minimal/app_config.h` | WiFi, API_BASE, ID_MESIN, API_KEY |
| `referensi-api-server.md` | URL, header, body JSON |

## Library Arduino IDE

| Library | Versi |
|---------|--------|
| Adafruit Fingerprint Sensor Library | **2.1.x** |
| Adafruit SSD1306 | latest (hanya jika OLED_DRIVER 1) |
| **Adafruit SH110X** | latest (**wajib** untuk OLED 1.3" / SSD1315) |
| Adafruit GFX Library | latest |
| Keypad (Mark Stanley) | latest |
| ArduinoJson | 6.x |
| LittleFS | built-in ESP32 core |

## Wiring prototipe (default `config_pins.h`)

| Periferal | Sambungan |
|-----------|-----------|
| **DY50** | VCC 3V3, GND, sensor TX → GPIO **41** (FINGER_RX), sensor RX → GPIO **42** (FINGER_TX), baud **57600** |
| **OLED 1.3" I2C** | SDA → GPIO **8**, SCL → GPIO **9** — driver **SH1106** (`OLED_DRIVER 2` di `config_pins.h`) |
| **Keypad 4x4** | Baris L1–L4 → GPIO **14,13,12,21** · Kolom C1–C4 → GPIO **18,17,16,15** |

Pin keypad modul (dari kiri): pin2–5 = kolom, pin6–9 = baris (NC di pin 1 & 10).

Ubah semua GPIO di [`config_pins.h`](contoh-skeleton-minimal/config_pins.h) jika jumper berbeda.

## Kontrol UI (OLED + keypad)

### Layar Home (jam + absen)
| Tombol | Fungsi |
|--------|--------|
| **#** | Buka **Menu** |
| **0** | Hapus overlay hasil |
| Jari | Absen |

### Menu
| Tombol | Fungsi |
|--------|--------|
| **A** / **2** | Naik |
| **B** / **8** | Turun |
| **#** / **D** | Pilih |
| **C** | Kembali Home |
| **\*** | Batal |

Layar Home: jam 24 jam + sapaan (pagi/siang/sore/malam). Hasil tap mengganti area jam ~4 detik.

Mode jaringan: Menu → **Mode Jaringan** → Online / Offline manual / Sync antrian.

## Mode offline

- WiFi mati **atau** menu **Mode Offline ON** → tap disimpan di flash (LittleFS), max 50 antrian.
- Waktu tap: **NTP saat WiFi connect** (jam ESP32 lanjut walau WiFi putus setelah pernah sync).
- Boot tanpa WiFi sama sekali: jam `--:--:--`, tap offline ditolak sampai WiFi + NTP sekali.
- Saat WiFi kembali: antrian otomatis `POST /mesin/tap` dengan field `waktu_tap`.
- Enroll sidik jari **tetap butuh WiFi**.

## Checklist uji prototipe case

1. Boot → OLED: jam + sapaan + status Online/Offline
2. **#** → menu, **A/B** scroll, **C** kembali Home
3. Standby → tap jari → hasil di OLED (~4 detik)
4. Matikan WiFi → tap → `Tersimpan offline` + counter `OFF:N`
5. Nyalakan WiFi → antrian berkurang, log muncul di web
6. Menu **Antrian Offline** → **#** sync manual
7. DY50 OFF → cek RX/TX (41/42) dan baud 57600

## API server

```
POST {API_BASE}/mesin/tap
Header: X-API-KEY, Content-Type: application/json
Body:   { "id_mesin": "...", "finger_id": 1, "waktu_tap": "2026-05-30T08:03:15+07:00" }
```

Field `waktu_tap` opsional (untuk sync offline).

Kode PHP: `app/Http/Controllers/Api/MesinAbsensiController.php`
