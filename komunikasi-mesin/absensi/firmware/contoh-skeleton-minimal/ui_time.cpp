#include "ui_time.h"

#include <Arduino.h>
#include <Wire.h>

#include "app_config.h"
#include "config_pins.h"

#if RTC_ENABLED
#include <RTClib.h>

#if RTC_USE_WIRE1
static TwoWire rtcWire = TwoWire(1);
#endif

static RTC_DS3231 rtc;
static bool rtcTried = false;
static bool rtcOk = false;

#if DEBUG_SERIAL
static void rtcScanBus() {
#if RTC_USE_WIRE1
  Serial.printf(F("[RTC] scan Wire1 SDA=%d SCL=%d ...\n"), RTC_SDA, RTC_SCL);
  for (uint8_t addr = 1; addr < 127; addr++) {
    rtcWire.beginTransmission(addr);
    if (rtcWire.endTransmission() == 0)
      Serial.printf(F("  -> 0x%02X\n"), addr);
  }
#else
  Serial.printf(F("[RTC] scan Wire SDA=%d SCL=%d ...\n"), RTC_SDA, RTC_SCL);
  for (uint8_t addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0)
      Serial.printf(F("  -> 0x%02X\n"), addr);
  }
#endif
}
#endif

static void rtcEnsureInit() {
  if (rtcTried)
    return;
  rtcTried = true;

#if RTC_USE_WIRE1
  rtcWire.begin(RTC_SDA, RTC_SCL);
  rtcWire.setClock(100000);
  delay(50);
#if DEBUG_SERIAL
  rtcScanBus();
#endif
  rtcOk = rtc.begin(&rtcWire);
#else
  rtcOk = rtc.begin();
#endif

#if DEBUG_SERIAL
  if (rtcOk)
    Serial.println(F("[RTC] DS3231 OK (0x68)"));
  else
    Serial.println(F("[RTC] tidak terdeteksi — cek VCC 3.3V, D→SDA, C→SCL, ganti modul jika bus mati"));
#endif
}

static bool rtcFetchTm(struct tm *out) {
  if (!out || !RTC_ENABLED)
    return false;

  rtcEnsureInit();
  if (!rtcOk)
    return false;

  DateTime dt = rtc.now();
  if (dt.year() < 2020)
    return false;

  time_t t = static_cast<time_t>(dt.unixtime()) + GMT_OFFSET_SEC;
  gmtime_r(&t, out);
  return true;
}
#endif

static const char *WEEKDAY_ID[] = {"Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"};

void uiTimeInitRtc() {
#if RTC_ENABLED
  rtcEnsureInit();
#endif
}

bool uiTimeIsValid() {
  time_t now = time(nullptr);
  if (now >= 1700000000)
    return true;

#if RTC_ENABLED
  struct tm ti;
  return rtcFetchTm(&ti);
#else
  return false;
#endif
}

bool uiTimeGetTm(struct tm *out) {
  if (!out)
    return false;

  time_t now = time(nullptr);
  if (now >= 1700000000) {
    localtime_r(&now, out);
    return true;
  }

#if RTC_ENABLED
  return rtcFetchTm(out);
#else
  return false;
#endif
}

void uiTimeFormatClock24(char *buf, size_t bufLen) {
  if (!buf || bufLen < 9)
    return;
  buf[0] = '\0';
  struct tm ti;
  if (!uiTimeGetTm(&ti)) {
    snprintf(buf, bufLen, "--:--:--");
    return;
  }
  strftime(buf, bufLen, "%H:%M:%S", &ti);
}

void uiTimeFormatTopDate(char *buf, size_t bufLen) {
  if (!buf || bufLen < 10)
    return;
  buf[0] = '\0';
  struct tm ti;
  if (!uiTimeGetTm(&ti)) {
    snprintf(buf, bufLen, "-- --/--");
    return;
  }
  const char *day = WEEKDAY_ID[ti.tm_wday % 7];
  snprintf(buf, bufLen, "%s %02d/%02d", day, ti.tm_mday, ti.tm_mon + 1);
}

const char *uiTimeGreeting() {
  struct tm ti;
  if (!uiTimeGetTm(&ti))
    return "Sync waktu...";
  int h = ti.tm_hour;
  if (h >= 4 && h < 10)
    return "Selamat pagi";
  if (h >= 10 && h < 15)
    return "Selamat siang";
  if (h >= 15 && h < 19)
    return "Selamat sore";
  return "Selamat malam";
}
