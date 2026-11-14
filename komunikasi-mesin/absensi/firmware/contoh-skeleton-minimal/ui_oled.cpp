#include "ui_oled.h"

#include <Adafruit_GFX.h>
#include <Wire.h>
#include <cstring>

#include "app_config.h"
#include "app_globals.h"
#include "config_pins.h"
#include "offline_queue.h"
#include "ui_time.h"
#include "wifi_portal.h"

#define SCREEN_W 128
#define SCREEN_H 64
#define OLED_WHITE 1
#define OLED_BLACK 0

#if OLED_DRIVER == 2
#include <Adafruit_SH110X.h>
static Adafruit_SH1106G display(SCREEN_W, SCREEN_H, &Wire, -1);
#else
#include <Adafruit_SSD1306.h>
static Adafruit_SSD1306 display(SCREEN_W, SCREEN_H, &Wire, -1);
#endif

static bool oledOk = false;
static uint8_t oledActiveAddr = 0;
static bool overlayActive = false;
static char overlayTitle[22];
static char overlayLine1[22];
static char overlayLine2[22];
static uint8_t lastI2cFound = 0;
static uint8_t lastI2cAddrs[8];

static bool tryOledBegin(uint8_t addr) {
#if OLED_DRIVER == 2
  return display.begin(addr, true);
#else
#if OLED_EXTERNAL_VCC
  return display.begin(SSD1306_EXTERNALVCC, addr);
#else
  return display.begin(SSD1306_SWITCHCAPVCC, addr);
#endif
#endif
}

static void i2cScanBus() {
#if DEBUG_SERIAL
  Serial.printf(F("[I2C] scan SDA=%d SCL=%d ...\n"), OLED_SDA, OLED_SCL);
#endif
  lastI2cFound = 0;
  for (uint8_t addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
#if DEBUG_SERIAL
      Serial.printf(F("  -> 0x%02X\n"), addr);
#endif
      if (lastI2cFound < 8)
        lastI2cAddrs[lastI2cFound] = addr;
      lastI2cFound++;
    }
  }
#if DEBUG_SERIAL
  if (lastI2cFound == 0)
    Serial.println(F("  (tidak ada perangkat — cek VCC OLED / GND / SDA8 / SCL9)"));
  else
    Serial.println(F("  OLED umum: 0x3C / 0x3D / 0x3F"));
#endif
}

static void oledHardClear() {
  // SH1106 sering menyisakan “ghost” pixel jika hanya clearDisplay().
  display.clearDisplay();
  display.fillScreen(OLED_BLACK);
}

static void oledForceVisible() {
  // Boot singkat — tanpa flash putih penuh.
#if OLED_DRIVER == 2
  display.oled_command(0x81);
  display.oled_command(0xFF);
  display.oled_command(0xA4);
  display.oled_command(0xAF);
#else
  display.ssd1306_command(0x81);
  display.ssd1306_command(0xFF);
  display.ssd1306_command(0xA4);
  display.ssd1306_command(0xAF);
#endif

  oledHardClear();
#if OLED_DRIVER == 2
  display.setTextColor(OLED_WHITE, OLED_BLACK);
#else
  display.setTextColor(SSD1306_WHITE, SSD1306_BLACK);
#endif
  display.setTextSize(2);
  display.setCursor(2, 16);
  display.println(F("SIMALAS"));
  display.setTextSize(1);
  display.setCursor(2, 44);
  display.println(F("OLED siap"));
  display.display();
  delay(350);
}

static void oledApplyMode() {
  display.clearDisplay();
#if OLED_DRIVER == 2
  display.setTextColor(OLED_WHITE, OLED_BLACK);
#else
  display.setTextColor(SSD1306_WHITE, SSD1306_BLACK);
#endif
#if OLED_INVERT
  display.invertDisplay(true);
#else
  display.invertDisplay(false);
#endif
  display.display();
}

static void pushCand(uint8_t *candidates, uint8_t *nCand, uint8_t a) {
  if (a == 0 || *nCand >= 12)
    return;
  for (uint8_t i = 0; i < *nCand; i++) {
    if (candidates[i] == a)
      return;
  }
  candidates[(*nCand)++] = a;
}

bool uiOledInit() {
  delay(200);
  Wire.begin(OLED_SDA, OLED_SCL);
  Wire.setClock(100000);
  delay(150);

  i2cScanBus();

  // Kandidat: config + alamat umum + hasil scan (log kamu: 0x3F).
  uint8_t candidates[12];
  uint8_t nCand = 0;
  pushCand(candidates, &nCand, OLED_ADDR);
  pushCand(candidates, &nCand, 0x3C);
  pushCand(candidates, &nCand, 0x3D);
  pushCand(candidates, &nCand, 0x3F);
  for (uint8_t i = 0; i < lastI2cFound; i++)
    pushCand(candidates, &nCand, lastI2cAddrs[i]);

  oledOk = false;
  oledActiveAddr = 0;
  for (int attempt = 1; attempt <= 3 && !oledOk; attempt++) {
    for (uint8_t i = 0; i < nCand && !oledOk; i++) {
      if (tryOledBegin(candidates[i])) {
        oledOk = true;
        oledActiveAddr = candidates[i];
      }
    }
#if DEBUG_SERIAL
    if (!oledOk)
      Serial.printf(F("[OLED] percobaan %d gagal\n"), attempt);
#endif
    if (!oledOk)
      delay(200);
  }

  if (oledOk) {
    oledForceVisible();
    oledApplyMode();
    uiHomeClearOverlay();
#if DEBUG_SERIAL
    Serial.printf(F("OLED OK @ 0x%02X driver="), oledActiveAddr);
#if OLED_DRIVER == 2
    Serial.println(F("SH1106"));
#else
    Serial.println(F("SSD1306"));
#endif
    Serial.println(F(">> Cek fisik: teks SIMALAS di latar hitam (bukan putih penuh)"));
#endif
  }
#if DEBUG_SERIAL
  else {
    Serial.println(F("[OLED] GAGAL init — coba OLED_DRIVER 1 di config_pins.h"));
  }
#endif
  return oledOk;
}

bool uiOledIsOk() { return oledOk; }

void uiOledPrintBootSummary() {
#if DEBUG_SERIAL
  Serial.println(F("--- ringkas OLED ---"));
  Serial.printf(F("SDA=%d SCL=%d found=%u\n"), OLED_SDA, OLED_SCL, lastI2cFound);
  for (uint8_t i = 0; i < lastI2cFound && i < 8; i++)
    Serial.printf(F("  0x%02X\n"), lastI2cAddrs[i]);
  if (oledOk)
    Serial.printf(F("OLED: AKTIF @ 0x%02X\n"), oledActiveAddr);
  else if (lastI2cFound == 0)
    Serial.println(F("OLED: BUS KOSONG — VCC/GND/kabel longgar (bukan software RTC)"));
  else
    Serial.println(F("OLED: ada perangkat I2C tapi init gagal — coba OLED_DRIVER 1"));
  Serial.println(F("-------------------"));
#endif
}

void uiOledSetNeedsRedraw() {}

void uiOledTick() {}

void uiHomeClearOverlay() {
  overlayActive = false;
  overlayTitle[0] = overlayLine1[0] = overlayLine2[0] = '\0';
}

void uiHomeSetOverlay(const char *title, const char *line1, const char *line2) {
  overlayActive = true;
  strncpy(overlayTitle, title ? title : "", sizeof(overlayTitle) - 1);
  strncpy(overlayLine1, line1 ? line1 : "", sizeof(overlayLine1) - 1);
  strncpy(overlayLine2, line2 ? line2 : "", sizeof(overlayLine2) - 1);
  overlayTitle[sizeof(overlayTitle) - 1] = '\0';
  overlayLine1[sizeof(overlayLine1) - 1] = '\0';
  overlayLine2[sizeof(overlayLine2) - 1] = '\0';
}

static const char *networkStatusLabel() {
  if (wifiApActive())
    return "AP setup";
  if (forceOfflineMode)
    return "Off/Man";
  if (!wifiOk())
    return "Offline";
  return "Online";
}

static void drawHomeTopBar() {
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 0);
  display.print(networkStatusLabel());
  char dateBuf[14];
  uiTimeFormatTopDate(dateBuf, sizeof(dateBuf));
  if (!dateBuf[0])
    snprintf(dateBuf, sizeof(dateBuf), "-- --/--");
  int dw = (int)strlen(dateBuf) * 6;
  int x = SCREEN_W - dw - 2;
  if (x < 50)
    x = 50;
  display.setCursor(x, 0);
  display.print(dateBuf);
  display.drawLine(0, 9, SCREEN_W - 1, 9, OLED_WHITE);
}

void uiShowHome() {
  if (!oledOk)
    return;

  // Overlay kosong “nyangkut” → layar cuma strip (tanpa jam).
  if (overlayActive && !overlayTitle[0] && !overlayLine1[0] && !overlayLine2[0])
    uiHomeClearOverlay();

  oledHardClear();
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  drawHomeTopBar();

  if (overlayActive) {
    // Hasil tap: area tengah penuh, tanpa jam (hindari nabrak / ghost SH1106).
    display.fillRect(0, 10, SCREEN_W, 44, OLED_BLACK);
    display.setTextSize(1);
    display.setTextColor(OLED_WHITE, OLED_BLACK);
    int y = 16;
    if (overlayTitle[0]) {
      display.setCursor(2, y);
      display.print(overlayTitle);
      y += 12;
    }
    if (overlayLine1[0]) {
      display.setCursor(2, y);
      display.print(overlayLine1);
      y += 11;
    }
    if (overlayLine2[0]) {
      display.setCursor(2, y);
      display.print(overlayLine2);
    }
  } else {
    char clockBuf[12];
    uiTimeFormatClock24(clockBuf, sizeof(clockBuf));
    if (!clockBuf[0])
      snprintf(clockBuf, sizeof(clockBuf), "--:--:--");

    display.setTextSize(2);
    display.setTextColor(OLED_WHITE, OLED_BLACK);
    display.setCursor(2, 14);
    display.print(clockBuf);

    display.setTextSize(1);
    display.setCursor(2, 36);
    display.print(uiTimeGreeting());
    display.setCursor(2, 46);
    if (wifiApActive()) {
      display.print(F("WiFi:"));
      display.print(wifiApSsid());
    } else if (!sensorOk)
      display.print(F("Sensor OFF"));
    else if (!uiTimeIsValid())
      display.print(F("Tunggu NTP..."));
    else
      display.print(F("Tempel jari absen"));
  }

  display.drawLine(0, 54, SCREEN_W - 1, 54, OLED_WHITE);
  display.fillRect(0, 55, SCREEN_W, 9, OLED_BLACK);
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 56);
  if (wifiApActive()) {
    display.print(F("192.168.4.1"));
  } else if (overlayActive) {
    display.print(F("Hasil absen"));
  } else {
    display.print(F("A Menu"));
  }
  if (!overlayActive) {
    size_t q = offlineQueueCount();
    if (q > 0) {
      display.setCursor(72, 56);
      display.print(F("antri:"));
      display.print((unsigned)q);
    } else if (!sensorOk) {
      display.setCursor(80, 56);
      display.print(F("S:--"));
    }
  }

  display.display();
}

void uiShowStandby() { uiShowHome(); }

void uiShowBoot() {
  if (!oledOk)
    return;
  oledHardClear();
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 16);
  display.println(F("SIMALAS"));
  display.println(F("Mesin Absensi"));
  display.println(F("Memulai..."));
  display.display();
}

static void drawMenuList(const char *header, const char *const *labels, int menuCount,
                         int selected, int visible) {
  oledHardClear();
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.fillRect(0, 0, SCREEN_W, SCREEN_H, OLED_BLACK);
  display.setCursor(2, 0);
  display.print(header);
  display.drawLine(0, 9, SCREEN_W - 1, 9, OLED_WHITE);

  int start = selected;
  if (start > menuCount - visible)
    start = menuCount - visible;
  if (start < 0)
    start = 0;

  for (int i = 0; i < visible; i++) {
    int idx = start + i;
    if (idx >= menuCount)
      break;
    int y = 12 + i * 10;
    display.fillRect(0, y, SCREEN_W, 10, OLED_BLACK);
    display.setCursor(2, y);
    if (idx == selected)
      display.print(F(">"));
    else
      display.print(F(" "));
    display.print(labels[idx]);
  }
  display.fillRect(0, 54, SCREEN_W, 10, OLED_BLACK);
  display.drawLine(0, 53, SCREEN_W - 1, 53, OLED_WHITE);
  display.setCursor(2, 56);
  display.print(F("2^ 4/8v D OK *Back"));
  display.display();
}

void uiShowMenu(int selected, int menuCount) {
  if (!oledOk)
    return;
  static const char *labels[] = {
      "Kembali ke Absen",
      "Enroll Sidik Jari",
      "Hapus Slot",
      "Daftar Mahasiswa",
      "Antrian Offline",
      "Mode Jaringan",
      "Info Mesin",
      "Sync Template",
      "Kosongkan Sensor",
  };
  int count = menuCount > 0 ? menuCount : 9;
  drawMenuList("MENU UTAMA", labels, count, selected, 4);
}

void uiShowNetworkMenu(int selected) {
  if (!oledOk)
    return;
  static const char *labels[] = {
      "Online (WiFi)",
      "Offline manual",
      "Sync antrian",
      "Kembali",
  };
  drawMenuList("JARINGAN", labels, 4, selected, 4);
}

void uiShowMessage(const char *title, const char *line1, const char *line2) {
  if (!oledOk)
    return;
  oledHardClear();
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 10);
  display.println(title ? title : "");
  if (line1)
    display.println(line1);
  if (line2)
    display.println(line2);
  display.setCursor(2, 56);
  display.print(F("* Back"));
  display.display();
}

void uiShowEnrollInput(const String &digits, const char *hint) {
  if (!oledOk)
    return;
  oledHardClear();
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 10);
  display.println(F("ENROLL"));
  display.print(F("finger_id: "));
  display.println(digits.length() ? digits : F("_"));
  if (hint)
    display.println(hint);
  display.setCursor(2, 56);
  display.print(F("D OK C hapus *batal"));
  display.display();
}

void uiShowEnrollScan(int step, const char *status) {
  if (!oledOk)
    return;
  oledHardClear();
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 10);
  display.print(F("Scan "));
  display.println(step);
  if (status)
    display.println(status);
  display.setCursor(2, 56);
  display.print(F("* batal"));
  display.display();
}

void uiShowInputDigits(const char *title, const String &digits, const char *hint) {
  if (!oledOk)
    return;
  oledHardClear();
  display.setTextSize(1);
  display.setTextColor(OLED_WHITE, OLED_BLACK);
  display.setCursor(2, 0);
  display.print(title ? title : "");
  display.drawLine(0, 9, SCREEN_W - 1, 9, OLED_WHITE);

  // PIN → "PIN:", lainnya → "ID:"
  const bool isPin = title && (strstr(title, "PIN") != nullptr);
  display.setCursor(2, 18);
  display.print(isPin ? F("PIN: ") : F("ID: "));
  if (digits.length())
    display.print(digits);
  else
    display.print(F("_"));

  if (hint) {
    display.setCursor(2, 34);
    display.print(hint);
  }
  display.setCursor(2, 56);
  display.print(F("D OK C hapus *batal"));
  display.display();
}

void uiShowInfo() {
  if (!oledOk)
    return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 10);
  display.println(F("INFO MESIN"));
  display.print(F("ID:"));
  String id = String(ID_MESIN);
  if (id.length() > 16)
    id = id.substring(0, 16);
  display.println(id);
  display.print(F("Net:"));
  display.println(networkStatusLabel());
  if (wifiOk())
    display.println(WiFi.localIP());
  display.print(F("Tpl:"));
  display.println(sensorOk ? (int)finger.templateCount : 0);
  display.print(F("Antri:"));
  display.println((unsigned)offlineQueueCount());
  display.setCursor(0, 56);
  display.print(F("* Back"));
  display.display();
}

void uiShowDaftarMhs(JsonArray arr, int scrollOffset, int selected) {
  if (!oledOk)
    return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 0);
  display.println(F("DAFTAR MHS"));
  display.drawLine(0, 9, SCREEN_W - 1, 9, OLED_WHITE);

  int count = arr.isNull() ? 0 : (int)arr.size();
  const int visible = 4;
  for (int i = 0; i < visible; i++) {
    int idx = scrollOffset + i;
    if (idx >= count)
      break;
    JsonObject o = arr[idx];
    display.setCursor(0, 12 + i * 11);
    if (idx == selected)
      display.print(F(">"));
    else
      display.print(F(" "));

    int fid = o["finger_id"].isNull() ? 0 : o["finger_id"].as<int>();
    display.print(F("#"));
    if (fid > 0)
      display.print(fid);
    else
      display.print(F("-"));
    display.print(F(" "));

    String nama = o["nama_lengkap"] | "?";
    if (nama.length() > 12)
      nama = nama.substring(0, 12);
    display.println(nama);
  }
  if (count == 0)
    display.println(F(" (kosong)"));
  display.setCursor(0, 56);
  display.print(F("2/4^ 8/Bv D OK *Back"));
  display.display();
}

void uiShowAntrian(size_t count, int scrollOffset) {
  if (!oledOk)
    return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 10);
  display.print(F("ANTRIAN ("));
  display.print((unsigned)count);
  display.println(F(")"));
  if (count == 0) {
    display.println(F("Kosong"));
  } else {
    display.println(F("D sync sekarang"));
    display.print(F("Tap D"));
    display.println(scrollOffset + 1);
  }
  display.setCursor(0, 56);
  display.print(F("* Back"));
  display.display();
}
