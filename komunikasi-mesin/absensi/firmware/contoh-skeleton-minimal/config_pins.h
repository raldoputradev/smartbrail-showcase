#pragma once

#include <Arduino.h>

/**
 * PCB SIMALAS v2 — ESP32-S3 N16R8
 *
 * OLED (terminal kiri)     : SDA, SCL, GND, 3.3V  → GPIO 8, 9  (sudah pas di PCB)
 * Fingerprint DY50         : terminal 41, 42
 * Keypad 4x4               : terminal 4,5,6,7 + 15,16,17,18
 * DFPlayer Mini            : GPIO 39 (TX ESP), GPIO 40 (RX ESP) + R 1k ke RX DFPlayer
 *
 * Hindari untuk I/O umum: 0, 3, 43, 44, 45, 46 (boot/USB/UART0).
 */

// ── DY50 fingerprint (UART2) ────────────────────────────────────────────────
// Arah kabel dari sensor ke terminal PCB (NORMAL):
//   DY50 TX → terminal 41 → GPIO 41 = FINGER_RX (ESP terima)
//   DY50 RX → terminal 42 → GPIO 42 = FINGER_TX (ESP kirim)
// Jika OLED "Sensor OFF" padahal sensor menyala: coba FINGER_SWAP_TXRX 1
// (pin kebalik) atau cek GND common + baud 57600.
#define FINGER_SWAP_TXRX 0
#if FINGER_SWAP_TXRX
#define FINGER_RX 42
#define FINGER_TX 41
#else
#define FINGER_RX 41
#define FINGER_TX 42
#endif

// ── OLED 1.3" I2C 128x64 ───────────────────────────────────────────────────
// Driver: 1 = SSD1306 · 2 = SH1106
// Layar putih / “freeze” tapi teks/IP masih kelihatan → biasanya SH1106 dipakai
// dengan driver SSD1306. Modul 1.3" SIMALAS: pakai SH1106.
#define OLED_DRIVER 2
#define OLED_SDA 8
#define OLED_SCL 9
#define OLED_ADDR 0x3C
#define OLED_INVERT 0
#define OLED_EXTERNAL_VCC 0
// OLED_EXTERNAL_VCC hanya untuk OLED_DRIVER 1.

// ── Keypad 4x4 (modul standar C1-C4 / L1-L4) ───────────────────────────────
// Konektor modul (10 pin, hitung dari kiri):
//   pin 1 = NC (abaikan)
//   pin 2 = C1 → terminal PCB GPIO 4
//   pin 3 = C2 → terminal PCB GPIO 5
//   pin 4 = C3 → terminal PCB GPIO 6
//   pin 5 = C4 → terminal PCB GPIO 7
//   pin 6 = L1 → terminal PCB GPIO 15
//   pin 7 = L2 → terminal PCB GPIO 16
//   pin 8 = L3 → terminal PCB GPIO 17
//   pin 9 = L4 → terminal PCB GPIO 18
//   pin 10 = NC (abaikan)
//
// Layout fisik tombol (standar — jangan swap L3/L4 di ui_keypad.cpp):
//   1 2 3 A
//   4 5 6 B
//   7 8 9 C
//   * 0 # D
// Menu: A=buka, 2=naik, 8/4=turun, D=OK, *=batal, C=hapus digit
#define KEYPAD_USE_REMAP 0
#define KEYPAD_SWAP_ROWS_COLS 0
#define KEYPAD_ROWS_REVERSED 0
#define KEYPAD_COLS_REVERSED 0

static const byte KEYPAD_ROW_PINS[] = {15, 16, 17, 18};
static const byte KEYPAD_COL_PINS[] = {4, 5, 6, 7};
#define KEYPAD_ROWS 4
#define KEYPAD_COLS 4

// ── DFPlayer Mini + microSD (/mp3/0001.mp3 … 0011.mp3, FAT32) ─────────────
// ESP TX (GPIO 39) → [R 1k] → DFPlayer RX
// ESP RX (GPIO 40) ← DFPlayer TX
#define AUDIO_ENABLED 1
#define DFPLAYER_RX 40
#define DFPLAYER_TX 39
#define DFPLAYER_VOLUME 30
// Volume DFPlayer library = 0..30 (30 = maksimal perintah).
// Angka >30 di-clamp ke 30. Lebih keras lagi = speaker lebih besar / amp hardware.

// ── RTC DS3231 — dinonaktifkan ─────────────────────────────────────────────
// Waktu dari NTP (WiFi). Setelah pernah sync NTP, jam ESP32 tetap jalan walau WiFi putus.
// Boot tanpa WiFi: jam --:--:--, tap offline ditolak sampai WiFi + NTP sekali.
#define RTC_ENABLED 0
