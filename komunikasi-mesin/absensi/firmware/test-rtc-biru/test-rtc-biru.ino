/*
 * SIMALAS — Uji RTC biru (DS3231 ZS-042) + OLED, satu bus I2C
 *
 * Modul biru BOLEH share SDA/SCL dengan OLED (beda alamat I2C).
 * Masalah dulu = modul mini "For PI", bukan karena barengan OLED.
 *
 * Library: RTClib, Adafruit GFX, Adafruit SH110X
 *
 * Sambungan ke header RTC PCB (sama jalur OLED):
 *   VCC → 3.3V
 *   GND → GND
 *   SDA → GPIO 8
 *   SCL → GPIO 9
 *
 * Serial 115200 — scan harus tampil:
 *   -> 0x3C  (OLED)
 *   -> 0x57  (EEPROM modul, opsional)
 *   -> 0x68  (RTC)
 */

#include <Wire.h>
#include <RTClib.h>
#include <Adafruit_GFX.h>

#define I2C_SDA 8
#define I2C_SCL 9
#define OLED_ADDR 0x3C
#define OLED_DRIVER_SH1106 1

#if OLED_DRIVER_SH1106
#include <Adafruit_SH110X.h>
Adafruit_SH1106G display(128, 64, &Wire, -1);
#else
#include <Adafruit_SSD1306.h>
Adafruit_SSD1306 display(128, 64, &Wire, -1);
#endif

RTC_DS3231 rtc;
static bool g_oledOk = false;
static bool g_rtcOk = false;

static void scanBus() {
  Serial.printf("[I2C] SDA=%d SCL=%d\n", I2C_SDA, I2C_SCL);
  uint8_t n = 0;
  for (uint8_t addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
      Serial.printf("  -> 0x%02X", addr);
      if (addr == 0x3C)
        Serial.print(F(" (OLED)"));
      else if (addr == 0x68)
        Serial.print(F(" (RTC)"));
      else if (addr == 0x57)
        Serial.print(F(" (EEPROM)"));
      Serial.println();
      n++;
    }
  }
  if (n == 0)
    Serial.println(F("  (kosong — cek VCC/GND/SDA/SCL)"));
}

static bool initOled() {
#if OLED_DRIVER_SH1106
  if (!display.begin(OLED_ADDR, true))
    return false;
#else
  if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR))
    return false;
#endif
  display.clearDisplay();
  display.setTextColor(1);
  display.setTextSize(1);
  display.display();
  return true;
}

static bool initRtc() {
  if (!rtc.begin())
    return false;

  if (rtc.lostPower()) {
    Serial.println(F("[RTC] jam kosong — set dari waktu compile sketch"));
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
  }
  return true;
}

static void drawScreen(const DateTime &dt, bool oledOk, bool rtcOk) {
  if (!oledOk)
    return;

  display.clearDisplay();
  display.setCursor(0, 0);
  display.println(F("RTC biru + OLED"));
  display.drawLine(0, 10, 127, 10, 1);

  display.setCursor(0, 14);
  display.printf("OLED: %s\n", oledOk ? "OK 0x3C" : "GAGAL");
  display.printf("RTC : %s\n", rtcOk ? "OK 0x68" : "GAGAL");

  if (rtcOk) {
    display.setTextSize(2);
    display.setCursor(0, 38);
    display.printf("%02d:%02d:%02d", dt.hour(), dt.minute(), dt.second());
    display.setTextSize(1);
    display.setCursor(0, 58);
    display.printf("%04d-%02d-%02d", dt.year(), dt.month(), dt.day());
  } else {
    display.setCursor(0, 40);
    display.println(F("RTC tidak terbaca"));
  }
  display.display();
}

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println(F("\n=== SIMALAS test RTC biru (share bus OLED) ==="));

  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(100000);
  delay(100);

  scanBus();

  g_oledOk = initOled();
  Serial.println(g_oledOk ? F("OLED: OK") : F("OLED: GAGAL"));

  g_rtcOk = initRtc();
  Serial.println(g_rtcOk ? F("RTC: OK (0x68)") : F("RTC: GAGAL — cek VCC 3.3V + SDA/SCL"));

  if (g_rtcOk) {
    DateTime now = rtc.now();
    Serial.printf("[RTC] jam: %04d-%02d-%02d %02d:%02d:%02d\n", now.year(),
                  now.month(), now.day(), now.hour(), now.minute(), now.second());
  }

  drawScreen(g_rtcOk ? rtc.now() : DateTime(2000, 1, 1, 0, 0, 0), g_oledOk, g_rtcOk);
}

void loop() {
  static unsigned long last = 0;
  if (millis() - last < 1000)
    return;
  last = millis();

  if (!g_rtcOk)
    return;

  DateTime dt = rtc.now();
  Serial.printf("[RTC] %04d-%02d-%02d %02d:%02d:%02d\n", dt.year(), dt.month(),
                dt.day(), dt.hour(), dt.minute(), dt.second());

  drawScreen(dt, g_oledOk, g_rtcOk);
}
