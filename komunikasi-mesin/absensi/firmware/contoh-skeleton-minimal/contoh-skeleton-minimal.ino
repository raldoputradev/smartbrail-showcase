/*
 * SIMALAS — Mesin ABSENSI (PCB v2)
 * ESP32-S3 + DY50 + OLED I2C + Keypad 4x4 + DFPlayer (siap)
 *
 * UI utama: OLED + keypad (A=menu, 2/4/8/6=nav, D=OK, C=hapus digit PIN, *=batal)
 * Serial Monitor: debug opsional (DEBUG_SERIAL)
 *
 * Library Arduino IDE:
 *   Adafruit Fingerprint 2.1.x, Adafruit SSD1306, Adafruit GFX,
 *   Keypad (Mark Stanley), ArduinoJson, LittleFS (ESP32 core),
 *   DFRobotDFPlayerMini
 *
 * SoftAP setup: jika WiFi lab gagal → broadcast WIFI_AP_SSID, buka
 * http://192.168.4.1 + PIN (WIFI_SETUP_PIN) untuk set SSID lab.
 */

#include <Adafruit_Fingerprint.h>
#include <Arduino.h>
#include <WiFi.h>

#include "app_config.h"
#include "app_globals.h"
#include "audio_player.h"
#include "config_pins.h"
#include "offline_queue.h"
#include "ui_app.h"
#include "ui_keypad.h"
#include "ui_oled.h"
#include "ui_time.h"
#include "wifi_portal.h"

void setup() {
#if DEBUG_SERIAL
  Serial.begin(115200);
  delay(400);
  while (Serial.available())
    Serial.read();
  Serial.println(F("\n*** SIMALAS Absensi (OLED) ***"));
#endif

  uiOledInit();
  uiKeypadInit();
  offlineQueueInit();

  uiAppInit();
  uiShowBoot();

  audioInit();

  // Fingerprint DULU sebelum WiFi (WiFi + DFPlayer tarik arus besar).
  // Kalau adaptor saja: init finger setelah WiFi sering gagal (Sensor OFF).
  delay(300);
  Serial2.begin(57600, SERIAL_8N1, FINGER_RX, FINGER_TX);
  delay(600);
  sensorOk = finger.verifyPassword();
  if (!sensorOk) {
    delay(400);
    sensorOk = finger.verifyPassword();
  }
#if DEBUG_SERIAL
  Serial.printf("DY50 pre-wifi: %s (RX=%d TX=%d)\n", sensorOk ? "OK" : "OFF", FINGER_RX,
                FINGER_TX);
#endif

  connectWiFi();

  // Cek ulang sesudah WiFi (kadang sensor butuh power settle di PCB adaptor).
  delay(400);
  if (!finger.verifyPassword()) {
    delay(500);
    sensorOk = finger.verifyPassword();
  } else {
    sensorOk = true;
  }
#if DEBUG_SERIAL
  Serial.printf("DY50 post-wifi: %s\n", sensorOk ? "OK" : "OFF");
  if (!sensorOk)
    Serial.println(F(">> Sensor OFF di adaptor: cek VCC 5V+GND finger dari rail HLK, "
                     "bukan hanya dari USB"));
#endif

  if (wifiOk()) {
    kirimHeartbeat(true);
    runFingerprintSync(true);
    offlineQueueSyncAll(true);
  }

  uiGoStandby();
  // OLED home tampil dulu, baru suara "mesin siap" — HANYA jika STA connect (bukan SoftAP).
  delay(600);
  if (wifiOk() && !wifiApActive()) {
    audioStop();
    delay(80);
    audioPlay(AUDIO_NET_OK);
#if DEBUG_SERIAL
    Serial.println(F("[Audio] WiFi OK → track 0002"));
#endif
  } else {
#if DEBUG_SERIAL
    Serial.println(F("[Audio] skip 0002 (WiFi tidak connect / mode AP)"));
#endif
  }

#if DEBUG_SERIAL
  Serial.println(F(">> Home — jam + keypad aktif"));
  uiOledPrintBootSummary();
  if (!uiOledIsOk())
    Serial.println(F(">> OLED TIDAK AKTIF — cek VCC/GND/SDA/SCL (GPIO 8/9)"));
  else
    Serial.println(F(">> OLED aktif"));
#endif
}

void loop() {
  uiAppTick();
  delay(10);
}
