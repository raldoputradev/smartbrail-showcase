<?php

namespace App\Support;

use App\Models\MesinIot;

/**
 * Generator sketch Arduino IDE (ESP32) per mesin.
 *
 * - tipe 'absensi'  → template UPDATE untuk firmware yang sudah beroperasi:
 *   konfigurasi ulang BASE_URL/X-API-KEY + tambah loop heartbeat.
 * - tipe lain (pintu/dll) → sketch LENGKAP mesin akses ruangan:
 *   heartbeat + RFID → access/check → relay + access/event.
 *   Blok PIN keypad & fingerprint UART disediakan sebagai stub siap aktifkan.
 *
 * API key TIDAK pernah diisi otomatis (server hanya menyimpan hash) —
 * selalu placeholder untuk ditempel manual dari kartu setup.
 */
class MesinSketchBuilder
{
    public static function make(MesinIot $mesin, string $apiBase): string
    {
        $base = rtrim($apiBase, '/');
        $id = $mesin->id_mesin;

        return $mesin->tipe === 'absensi'
            ? self::absensi($base, $id, (string) $mesin->nama_mesin)
            : self::pintu($base, $id, (string) ($mesin->nama_mesin ?: $id), $mesin->ruangan?->nama);
    }

    private static function header(string $judul, string $idMesin, string $extra = ''): string
    {
        return <<<INO
        /*
         * SmartBrail — {$judul}
         * ID Mesin : {$idMesin}
         * Auth     : header  X-API-KEY: <api key dari kartu setup mesin>
         *{$extra}
         * PENTING  : ruangan/izin diputuskan SERVER — mesin hanya mengirim
         *            identitas (rfid/pin/fingerprint), bukan hak akses.
         */

        INO;
    }

    private static function wifiBlock(string $base, string $idMesin): string
    {
        return <<<INO
        #include <WiFi.h>
        #include <HTTPClient.h>

        #define WIFI_SSID     "NAMA_WIFI_ANDA"
        #define WIFI_PASS     "PASSWORD_WIFI"
        #define SERVER_BASE   "{$base}"
        #define MESIN_API_KEY "TEMPEL_API_KEY_DARI_PANEL"
        #define ID_MESIN      "{$idMesin}"

        const unsigned long HEARTBEAT_MS = 60000UL; // status Online butuh heartbeat < 5 mnt
        unsigned long lastBeat = 0;

        void wifiConnect() {
          if (WiFi.status() == WL_CONNECTED) return;
          WiFi.mode(WIFI_STA);
          WiFi.begin(WIFI_SSID, WIFI_PASS);
          Serial.print("WiFi");
          while (WiFi.status() != WL_CONNECTED) { delay(400); Serial.print('.'); }
          Serial.println(" OK");
        }

        // POST JSON dengan header X-API-KEY; kembalikan body respons ("" jika gagal)
        String postJson(const char* path, const String& body) {
          wifiConnect();
          HTTPClient http;
          http.begin(String(SERVER_BASE) + path);
          http.addHeader("Content-Type", "application/json");
          http.addHeader("X-API-KEY", MESIN_API_KEY);
          int code = http.POST(body);
          String resp = (code > 0) ? http.getString() : "";
          http.end();
          Serial.printf("POST %s -> %d\\n", path, code);
          return resp;
        }

        void sendHeartbeat() {
          postJson("/heartbeat", String("{\\"id_mesin\\":\\"") + ID_MESIN + "\\"}");
        }

        INO;
    }

    /** Sketch UPDATE mesin absensi yang sudah beroperasi. */
    private static function absensi(string $base, string $id, string $nama): string
    {
        return self::header('Mesin Absensi — UPDATE firmware lama', $id,
                "\n         * Mesin   : {$nama}\n         * Catatan : fungsi tap & enroll fingerprint TIDAK berubah."
                . "\n         *           Yang ditambah hanya heartbeat berkala agar status"
                . "\n         *           'Online' di dashboard hidup, plus konfigurasi ulang"
                . "\n         *           SERVER_BASE + MESIN_API_KEY di bawah.")
            . self::wifiBlock($base, $id)
            . <<<INO

        /* ── TAP ABSENSI (sama seperti firmware lama) ───────────────────
         * POST {base}/tap
         * body : {"finger_id": <slot fingerprint>, "id_mesin": "{id}",
         *         "event_id": "<unik-per-tap>", "waktu_tap": "YYYY-MM-DD HH:MM:SS"}
         * Respons server yang menentukan masuk/pulang/terlambat — mesin
         * cukup menampilkan 'message' dari JSON respons.
         *
         * Endpoint absensi lain yang tetap dipakai:
         *   GET  /fingerprint/sync       daftar template yang harus dimuat
         *   GET  /fingerprint/resolve    finger_id -> identitas
         *   POST /fingerprint/enroll     konfirmasi hasil enroll
         *   DEL  /fingerprint/{id}       hapus slot
         * ─────────────────────────────────────────────────────────────── */

        void kirimTap(long fingerId) {
          String body = String("{\\"finger_id\\":") + fingerId
                      + ",\\"id_mesin\\":\\"" + ID_MESIN + "\\"}";
          String resp = postJson("/tap", body);
          Serial.println(resp); // tampilkan field 'message' ke LCD/OLED mesin
        }

        void setup() {
          Serial.begin(115200);
          wifiConnect();
          sendHeartbeat();
          // TODO: init modul fingerprint seperti firmware lama
        }

        void loop() {
          if (millis() - lastBeat >= HEARTBEAT_MS) { lastBeat = millis(); sendHeartbeat(); }
          // TODO: scan fingerprint -> kirimTap(id)
        }

        INO;
    }

    /** Sketch LENGKAP mesin pintu / akses ruangan (baru). */
    private static function pintu(string $base, string $id, string $nama, ?string $ruangan): string
    {
        $ruanganTxt = $ruangan ? " · ruangan {$ruangan}" : ' · BELUM terikat ruangan (ikat dulu di menu Ruangan)';
        $sketch = self::header('Mesin Pintu — akses ruangan', $id,
                "\n         * Mesin   : {$nama}{$ruanganTxt}\n         * Board   : ESP32 Dev Module + RFID RC522 + relay kunci"
                . "\n         * Library : MFRC522 (by GithubCommunity) via Library Manager")
            . self::wifiBlock($base, $id)
            . <<<INO
        #include <SPI.h>
        #include <MFRC522.h>

        /* ── Pin hardware (sesuaikan wiring) ── */
        #define PIN_RELAY    26   // relay kunci solenoid (aktif HIGH)
        #define PIN_BUZZER   27
        #define PIN_LED_OK    2
        #define PIN_LED_ERR  15
        #define RFID_SS       5
        #define RFID_RST     22
        #define BUKA_MS    3000   // durasi relay membuka pintu

        MFRC522 rfid(RFID_SS, RFID_RST);

        INO;

        $sketch .= <<<'INO'

        // UID kartu -> string HEX kapital ("A1B2C3D4") — dikirim apa adanya,
        // server yang mencocokkan ke users.rfid_uid / kredensial petugas.
        String uidRfid() {
          String s = "";
          for (byte i = 0; i < rfid.uid.size; i++) {
            if (rfid.uid.uidByte[i] < 0x10) s += "0";
            s += String(rfid.uid.uidByte[i], HEX);
          }
          s.toUpperCase();
          return s;
        }

        // Tanya server: identitas ini boleh buka ruangan ini SEKARANG?
        bool cekAkses(const char* tipe, const String& nilai) {
          String body = String("{\"tipe\":\"") + tipe + "\",\"nilai\":\"" + nilai + "\"}";
          String resp = postJson("/ruangan/access/check", body);
          Serial.println(resp);
          return resp.indexOf("\"diizinkan\":true") >= 0;
        }

        void laporEvent(const char* jenis, const char* hasil) {
          String body = String("{\"jenis\":\"") + jenis + "\",\"hasil\":\"" + hasil + "\"}";
          postJson("/ruangan/access/event", body);
        }

        void bukaPintu() {
          digitalWrite(PIN_LED_OK, HIGH); digitalWrite(PIN_BUZZER, HIGH);
          delay(120); digitalWrite(PIN_BUZZER, LOW);
          digitalWrite(PIN_RELAY, HIGH);
          delay(BUKA_MS);
          digitalWrite(PIN_RELAY, LOW); digitalWrite(PIN_LED_OK, LOW);
          laporEvent("buka", "terbuka");   // bukti fisik pintu dibuka
        }

        void tolakAkses() {
          digitalWrite(PIN_LED_ERR, HIGH);
          for (int i = 0; i < 3; i++) { digitalWrite(PIN_BUZZER, HIGH); delay(120); digitalWrite(PIN_BUZZER, LOW); delay(120); }
          digitalWrite(PIN_LED_ERR, LOW);
          // server sudah mencatat hasil 'ditolak' dari access/check;
          // kirim event 'tolak' hanya bila perangkat melakukan penolakan sendiri
        }

        void setup() {
          Serial.begin(115200);
          pinMode(PIN_RELAY, OUTPUT);   digitalWrite(PIN_RELAY, LOW);
          pinMode(PIN_BUZZER, OUTPUT);  digitalWrite(PIN_BUZZER, LOW);
          pinMode(PIN_LED_OK, OUTPUT);  pinMode(PIN_LED_ERR, OUTPUT);
          wifiConnect();
          SPI.begin();
          rfid.PCD_Init();
          sendHeartbeat();
          Serial.println("Mesin pintu siap — tempel kartu.");
        }

        void loop() {
          // heartbeat berkala — wajib agar status 'Online' di dashboard
          if (millis() - lastBeat >= HEARTBEAT_MS) { lastBeat = millis(); sendHeartbeat(); }

          // ── Jalur RFID ──
          if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
            String uid = uidRfid();
            Serial.println("Kartu: " + uid);
            if (cekAkses("rfid", uid)) bukaPintu(); else tolakAkses();
            rfid.PICC_HaltA(); rfid.PCD_StopCrypto1();
            delay(800); // jeda anti double-tap
          }

          /* ── Jalur PIN keypad (opsional — aktifkan bila keypad terpasang) ──
           * Baca 6 digit dari keypad 4x4, lalu:
           *   if (cekAkses("pin", pinStr)) bukaPintu(); else tolakAkses();
           * PIN mahasiswa = PIN per-izin (lihat profilnya); PIN staf permanen.
           * ─────────────────────────────────────────────────────────────── */

          /* ── Jalur fingerprint UART AS608/R307 (opsional) ──
           * Dapatkan ID slot dari sensor, lalu:
           *   if (cekAkses("fingerprint", String(fingerId))) bukaPintu(); else tolakAkses();
           * Slot finger_id diatur admin di menu Fingerprint per pengguna.
           * ─────────────────────────────────────────────────────────────── */
        }

        INO;

        return $sketch;
    }
}
