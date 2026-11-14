#include "ui_app.h"

#include <ArduinoJson.h>
#include <WiFi.h>
#include <cstring>
#include <cstdio>

#include "app_config.h"
#include "app_globals.h"
#include "audio_player.h"
#include "offline_queue.h"
#include "ui_keypad.h"
#include "ui_oled.h"
#include "ui_time.h"
#include "wifi_portal.h"

#if DEBUG_SERIAL
#include <Arduino.h>
#endif

enum class Screen {
  Home,
  PinEntry,
  Menu,
  NetworkMenu,
  Message,
  EnrollInput,
  EnrollScan,
  DeleteInput,
  DaftarMhs,
  InfoMesin,
  AntrianOffline,
  ClearConfirm,
};

static Screen screen = Screen::Home;
static int menuIndex = 0;
static int networkMenuIndex = 0;
static unsigned long overlayUntil = 0;
static unsigned long messageUntil = 0;
static unsigned long ignoreTapUntil = 0; // cegah tap absen segera setelah keluar menu/enroll
static String digitBuffer;
static String messageTitle;
static String messageLine1;
static String messageLine2;

static int daftarScroll = 0;
static int daftarSelected = 0;
static DynamicJsonDocument daftarDoc(32768);
static bool daftarLoaded = false;

static int enrollSlot = 0;
static int enrollPhase = 0;
static unsigned long enrollPhaseAt = 0;
static int enrollSoundPhase = 0;
static uint8_t enrollNoFingerCount = 0;

// Setelah scan1: biarkan 0007 selesai dulu, baru boleh lanjut ke 0006 (scan2).
static const unsigned ENROLL_LIFT_HOLD_MS = 3200;
static const uint8_t ENROLL_NOFINGER_NEED = 8;

static int antrianScroll = 0;
static bool lastWifiConnected = false;
static uint8_t pinFailCount = 0;

static const int MAIN_MENU_COUNT = 9;

static bool shouldUseOffline() {
  return forceOfflineMode || !wifiOk();
}

static void truncateMsg(const char *src, char *dst, size_t n) {
  if (!dst || n == 0)
    return;
  if (!src) {
    dst[0] = '\0';
    return;
  }
  strncpy(dst, src, n - 1);
  dst[n - 1] = '\0';
}

void uiGoStandby() {
  screen = Screen::Home;
  standbyAbsen = true;
  menuIndex = 0;
  networkMenuIndex = 0;
  digitBuffer = "";
  uiHomeClearOverlay();
  uiShowHome();
}

bool uiIsStandbyScreen() { return screen == Screen::Home; }

static bool msgContains(const char *msg, const char *needle) {
  return msg && needle && strstr(msg, needle) != nullptr;
}

void uiOnTapResult(bool ok, int fid, JsonObject d, const char *msg, const char *code,
                   bool offlineSaved) {
  screen = Screen::Home;
  standbyAbsen = true;

  char l1[22];
  char l2[22];
  const char *title;
  uint8_t failTrack = AUDIO_NOT_REGISTERED;
  unsigned long holdMs = RESULT_SCREEN_MS;
  bool playFailAudio = true;

  const bool codeBukanHari =
      (code && strcmp(code, "BUKAN_HARI_WAJIB") == 0) ||
      msgContains(msg, "tidak dijadwalkan") || msgContains(msg, "hanya pada hari") ||
      msgContains(msg, "Hanya hari");
  const bool codeLibur =
      (code && strcmp(code, "HARI_LIBUR") == 0) || msgContains(msg, "Hari libur");
  const bool codeSesiTutup = code && strcmp(code, "SESI_DITUTUP") == 0;
  const bool codeTungguPulang = code && strcmp(code, "BELUM_WAKTU_PULANG") == 0;
  const bool codeTungguMasuk = code && strcmp(code, "BELUM_WAKTU_MASUK") == 0;

  if (offlineSaved) {
    title = "OFFLINE";
    snprintf(l1, sizeof(l1), "Tersimpan #%d", fid);
    size_t q = offlineQueueCount();
    snprintf(l2, sizeof(l2), "antri: %u", (unsigned)q);
  } else if (ok && !d.isNull()) {
    title = "BERHASIL";
    truncateMsg(d["nama_lengkap"] | "?", l1, sizeof(l1));
    const char *jenis = d["jenis"] | "";
    int telat = d["menit_terlambat"].isNull() ? 0 : d["menit_terlambat"].as<int>();
    if (telat > 0)
      snprintf(l2, sizeof(l2), "%s +%dm", jenis, telat);
    else
      truncateMsg(jenis, l2, sizeof(l2));
    holdMs = RESULT_SCREEN_MS; // ~4 detik biar nama terbaca
  } else {
    holdMs = RESULT_SCREEN_FAIL_MS;
    if (codeTungguPulang) {
      title = "TUNGGU";
      truncateMsg("Belum jam pulang", l1, sizeof(l1));
      truncateMsg(msg, l2, sizeof(l2));
      failTrack = AUDIO_WAIT_PULANG; // 0010
    } else if (codeSesiTutup || codeBukanHari || codeLibur) {
      if (codeSesiTutup) {
        title = "SESI TUTUP";
        truncateMsg("Sesi sudah tutup", l1, sizeof(l1));
        truncateMsg("Tap tidak dicatat", l2, sizeof(l2));
      } else if (codeBukanHari) {
        title = "BUKAN HARI";
        truncateMsg("Hanya hari Kamis", l1, sizeof(l1));
        truncateMsg("Absen ditolak", l2, sizeof(l2));
      } else {
        title = "LIBUR";
        truncateMsg("Hari libur", l1, sizeof(l1));
        truncateMsg("Absen ditolak", l2, sizeof(l2));
      }
      failTrack = AUDIO_HARI_LIBUR; // 0011 — jangan 0005
    } else if (codeTungguMasuk) {
      title = "TUNGGU";
      truncateMsg("Belum jam masuk", l1, sizeof(l1));
      truncateMsg(msg, l2, sizeof(l2));
      playFailAudio = false; // OLED saja
    } else if (code && strcmp(code, "SUDAH_SELESAI") == 0) {
      title = "SELESAI";
      truncateMsg("Sudah absen hari ini", l1, sizeof(l1));
      truncateMsg(msg, l2, sizeof(l2));
    } else if (code && strcmp(code, "BELUM_MASUK") == 0) {
      title = "GAGAL";
      truncateMsg("Belum absen masuk", l1, sizeof(l1));
      truncateMsg(msg, l2, sizeof(l2));
    } else {
      title = "GAGAL";
      snprintf(l1, sizeof(l1), "Finger #%d", fid);
      if (code && code[0])
        truncateMsg(code, l2, sizeof(l2));
      else
        truncateMsg(msg, l2, sizeof(l2));
    }
  }

  // OLED dulu (bersih), stop audio absen/enroll lama, baru suara hasil.
  uiHomeSetOverlay(title, l1, l2[0] ? l2 : nullptr);
  overlayUntil = millis() + holdMs;
  uiShowHome();
  delay(150);

  audioStop();
  delay(60);
  if (offlineSaved || (ok && !d.isNull()))
    audioPlay(AUDIO_ABSEN_OK);
  else if (playFailAudio)
    audioPlay(failTrack);

  absenSeq++;

#if DEBUG_SERIAL
  Serial.printf("TAP %s fid=%d code=%s track=%u hold=%lums\n", ok ? "OK" : "FAIL", fid,
                code && code[0] ? code : "-", (unsigned)failTrack, (unsigned long)holdMs);
#endif
}

static void showHomeOverlay(const char *title, const char *l1, const char *l2,
                            unsigned long ms = RESULT_SCREEN_MS) {
  screen = Screen::Home;
  standbyAbsen = true;
  uiHomeSetOverlay(title, l1, l2);
  overlayUntil = millis() + ms;
  uiShowHome();
}

static void showMessage(const char *title, const char *l1, const char *l2 = nullptr,
                        unsigned long ms = 2500) {
  screen = Screen::Message;
  standbyAbsen = false;
  messageTitle = title ? title : "";
  messageLine1 = l1 ? l1 : "";
  messageLine2 = l2 ? l2 : "";
  messageUntil = millis() + ms;
  uiShowMessage(title, l1, l2);
}

static void goHomeFromMenu() {
  enrollPhase = 0;
  enrollSoundPhase = 0;
  audioSetEnrollMode(false);
  ignoreTapUntil = millis() + 2500; // jari masih di sensor setelah enroll → jangan 0005
  audioStop();
  uiGoStandby();
}

static void activateOnline() {
  connectWiFi();
  if (wifiOk() && !wifiApActive()) {
    offlineQueueSyncAll(true);
    uiGoStandby();
    delay(500);
    audioStop();
    delay(80);
    audioPlay(AUDIO_NET_OK);
    showHomeOverlay("ONLINE", "WiFi terhubung", "Sync antrian...");
  } else if (wifiApActive()) {
    showHomeOverlay("SETUP AP", wifiApSsid(), "Buka 192.168.4.1", 4000);
  } else {
    showHomeOverlay("ONLINE", "WiFi gagal", "Cek router");
  }
}

static void activateOfflineManual() {
  forceOfflineMode = true;
  showHomeOverlay("OFFLINE", "Mode manual", "Tap = antrian");
}

/** Enroll hanya 0006/0007/0008/0009. */
static void playEnrollAudio(uint8_t track) {
  audioSetEnrollMode(true);
  audioStop();
  delay(80);
  audioPlay(track);
}

static void loadDaftarMhs() {
  daftarLoaded = false;
  daftarDoc.clear();
  if (!wifiOk()) {
    showMessage("Daftar MHS", "WiFi OFF", "Menu jaringan");
    return;
  }
  String url = String(API_BASE) + "/mesin/fingerprint/mahasiswa?id_mesin=" + ID_MESIN +
               "&limit=120";
  if (!httpGetJson(url, daftarDoc)) {
    showMessage("Daftar MHS", "Gagal ambil data", nullptr);
    return;
  }
  daftarLoaded = true;
  daftarScroll = 0;
  daftarSelected = 0;
  screen = Screen::DaftarMhs;
  standbyAbsen = false;
  uiShowDaftarMhs(daftarDoc["data"].as<JsonArray>(), daftarScroll, daftarSelected);
}

static void enrollTick() {
  if (screen != Screen::EnrollScan || !sensorOk || enrollPhase == 0)
    return;

  standbyAbsen = false;
  audioSetEnrollMode(true);
  ignoreTapUntil = millis() + 30000;

  if (enrollPhase == 1) {
    // Scan 1: tunggu jari. Setelah OK → 0007 saja (jangan langsung 0006).
    if (finger.getImage() == FINGERPRINT_OK) {
      if (finger.image2Tz(1) == FINGERPRINT_OK) {
        enrollPhase = 2;
        enrollPhaseAt = millis();
        enrollSoundPhase = 2;
        enrollNoFingerCount = 0;
        playEnrollAudio(AUDIO_LIFT_FINGER); // WAJIB 0007
        uiShowEnrollScan(1, "Angkat jari...");
#if DEBUG_SERIAL
        Serial.println(F("[Enroll] scan1 OK → 0007 angkat jari (tunggu lift)"));
#endif
      }
    } else if (millis() - enrollPhaseAt > 25000) {
      playEnrollAudio(AUDIO_ENROLL_FAIL); // 0009
      showMessage("Enroll", "Timeout scan 1", nullptr);
      enrollPhase = 0;
      enrollSoundPhase = 0;
      ignoreTapUntil = millis() + 3000;
    }
    return;
  }

  if (enrollPhase == 2) {
    // Jangan loncat ke scan2 sebelum 0007 sempat diputar + jari benar-benar angkat.
    // (NOFINGER palsu setelah image2Tz sering bikin 0006 dobel / 0007 ke-skip.)
    if (millis() - enrollPhaseAt < ENROLL_LIFT_HOLD_MS) {
      enrollNoFingerCount = 0;
      return;
    }
    if (finger.getImage() == FINGERPRINT_NOFINGER) {
      enrollNoFingerCount++;
      if (enrollNoFingerCount >= ENROLL_NOFINGER_NEED) {
        enrollPhase = 3;
        enrollPhaseAt = millis();
        enrollSoundPhase = 0;
        enrollNoFingerCount = 0;
        uiShowEnrollScan(2, "Tempel lagi...");
#if DEBUG_SERIAL
        Serial.println(F("[Enroll] jari angkat OK → scan2 + 0006"));
#endif
      }
    } else {
      enrollNoFingerCount = 0;
    }
    return;
  }

  if (enrollPhase == 3) {
    if (enrollSoundPhase != 3) {
      playEnrollAudio(AUDIO_PLACE_FINGER); // 0006 scan 2
      enrollSoundPhase = 3;
#if DEBUG_SERIAL
      Serial.println(F("[Enroll] play 0006 tempel jari (scan2)"));
#endif
    }
    if (finger.getImage() != FINGERPRINT_OK) {
      if (millis() - enrollPhaseAt > 25000) {
        playEnrollAudio(AUDIO_ENROLL_FAIL);
        showMessage("Enroll", "Timeout scan 2", nullptr);
        enrollPhase = 0;
        enrollSoundPhase = 0;
        ignoreTapUntil = millis() + 3000;
      }
      return;
    }
    if (finger.image2Tz(2) != FINGERPRINT_OK || finger.createModel() != FINGERPRINT_OK) {
      playEnrollAudio(AUDIO_ENROLL_FAIL);
      showMessage("Enroll", "Gagal gabung", nullptr);
      enrollPhase = 0;
      enrollSoundPhase = 0;
      ignoreTapUntil = millis() + 3000;
      return;
    }
    if (finger.storeModel(enrollSlot) != FINGERPRINT_OK) {
      playEnrollAudio(AUDIO_ENROLL_FAIL);
      showMessage("Enroll", "Slot penuh?", nullptr);
      enrollPhase = 0;
      enrollSoundPhase = 0;
      ignoreTapUntil = millis() + 3000;
      return;
    }
    if (!readTemplateFromSlot(enrollSlot, fingerModelBuf)) {
      finger.deleteModel(enrollSlot);
      playEnrollAudio(AUDIO_ENROLL_FAIL);
      showMessage("Enroll", "Gagal baca tpl", nullptr);
      enrollPhase = 0;
      enrollSoundPhase = 0;
      ignoreTapUntil = millis() + 3000;
      return;
    }
    bytesToHex(fingerModelBuf, FINGER_TEMPLATE_BYTES, templateHexOut);
    DynamicJsonDocument req(1536), res(768);
    req["finger_id"] = enrollSlot;
    req["id_mesin"] = ID_MESIN;
    req["template_hex"] = templateHexOut;
    String p;
    serializeJson(req, p);
    if (!httpPostJson(String(API_BASE) + "/mesin/fingerprint/enroll", p, res)) {
      finger.deleteModel(enrollSlot);
      playEnrollAudio(AUDIO_ENROLL_FAIL);
      showMessage("Enroll", "DB gagal", nullptr);
      enrollPhase = 0;
      enrollSoundPhase = 0;
      ignoreTapUntil = millis() + 3000;
      return;
    }
    showMessage("Enroll", "OK!", res["data"]["fingerprint_status"] | "?", 3000);
    playEnrollAudio(AUDIO_ENROLL_OK); // 0008
    enrollPhase = 0;
    enrollSoundPhase = 0;
    ignoreTapUntil = millis() + 4000;
  }
}

static void startEnroll(int slot) {
  if (!sensorOk) {
    showMessage("Enroll", "Sensor OFF", nullptr);
    return;
  }
  if (!wifiOk()) {
    showMessage("Enroll", "Butuh WiFi", nullptr);
    return;
  }

  // Kunci mode enroll SEBELUM HTTP resolve — cegah standby absen putar 0005.
  enrollSlot = slot;
  enrollPhase = 1;
  enrollPhaseAt = millis();
  enrollSoundPhase = 0;
  screen = Screen::EnrollScan;
  standbyAbsen = false;
  audioSetEnrollMode(true);
  ignoreTapUntil = millis() + 60000;
  audioStop();
  delay(80);
  uiShowEnrollScan(1, "Cek data...");

  DynamicJsonDocument chk(1024);
  String url = String(API_BASE) + "/mesin/fingerprint/resolve?q=" + String(slot) +
               "&id_mesin=" + ID_MESIN;
  if (!httpGetJson(url, chk)) {
    enrollPhase = 0;
    enrollSoundPhase = 0;
    audioSetEnrollMode(false);
    ignoreTapUntil = millis() + 3000;
    showMessage("Enroll", "Resolve gagal", "Cek finger_id");
    return;
  }
  const char *st = chk["data"]["status"];
  if (st && strcmp(st, "Sudah Enroll") == 0) {
    enrollPhase = 0;
    enrollSoundPhase = 0;
    audioSetEnrollMode(false);
    ignoreTapUntil = millis() + 3000;
    showMessage("Enroll", "Sudah enroll", nullptr);
    return;
  }

  enrollPhaseAt = millis();
  enrollSoundPhase = 1;
  enrollNoFingerCount = 0;
  playEnrollAudio(AUDIO_PLACE_FINGER); // 0006 — silakan tempelkan jari
  uiShowEnrollScan(1, "Tempel jari...");
}

static void confirmDelete(int slot) {
  if (!sensorOk) {
    showMessage("Hapus", "Sensor OFF", nullptr);
    return;
  }
  if (finger.deleteModel(slot) != FINGERPRINT_OK)
    showMessage("Hapus", "Slot tidak ada", nullptr);
  else {
    DynamicJsonDocument req(96), res(768);
    req["id_mesin"] = ID_MESIN;
    String body;
    serializeJson(req, body);
    String url = String(API_BASE) + "/mesin/fingerprint/" + String(slot);
    httpDeleteJson(url, body, res);
    showMessage("Hapus", "Slot dihapus", nullptr);
  }
}

static void menuActivate(int idx) {
  switch (idx) {
  case 0:
    goHomeFromMenu();
    break;
  case 1:
    audioStop();
    audioSetEnrollMode(true); // blokir 0005 sejak buka form enroll
    screen = Screen::EnrollInput;
    standbyAbsen = false;
    digitBuffer = "";
    uiShowEnrollInput(digitBuffer, "Ketik finger_id");
    break;
  case 2:
    screen = Screen::DeleteInput;
    standbyAbsen = false;
    digitBuffer = "";
    uiShowInputDigits("HAPUS SLOT", digitBuffer, "finger_id");
    break;
  case 3:
    loadDaftarMhs();
    break;
  case 4:
    screen = Screen::AntrianOffline;
    standbyAbsen = false;
    uiShowAntrian(offlineQueueCount(), antrianScroll);
    break;
  case 5:
    screen = Screen::NetworkMenu;
    networkMenuIndex = 0;
    uiShowNetworkMenu(networkMenuIndex);
    break;
  case 6:
    screen = Screen::InfoMesin;
    standbyAbsen = false;
    uiShowInfo();
    break;
  case 7:
    runFingerprintSync(true);
    showMessage("Sync", "Template selesai", nullptr);
    break;
  case 8:
    screen = Screen::ClearConfirm;
    standbyAbsen = false;
    uiShowMessage("KOSONGKAN?", "Tekan D ya", "* batal");
    break;
  default:
    break;
  }
}

static void networkMenuActivate(int idx) {
  switch (idx) {
  case 0:
    activateOnline();
    break;
  case 1:
    activateOfflineManual();
    break;
  case 2:
    offlineQueueSyncAll(true);
    showHomeOverlay("SYNC", "Antrian diproses", nullptr);
    break;
  case 3:
    screen = Screen::Menu;
    uiShowMenu(menuIndex, MAIN_MENU_COUNT);
    break;
  default:
    break;
  }
}

static void menuMoveUp() {
  if (screen == Screen::Menu) {
    menuIndex = (menuIndex + MAIN_MENU_COUNT - 1) % MAIN_MENU_COUNT;
    uiShowMenu(menuIndex, MAIN_MENU_COUNT);
  } else if (screen == Screen::NetworkMenu) {
    networkMenuIndex = (networkMenuIndex + 3) % 4;
    uiShowNetworkMenu(networkMenuIndex);
  } else if (screen == Screen::DaftarMhs && daftarLoaded) {
    JsonArray arr = daftarDoc["data"].as<JsonArray>();
    int n = arr.size();
    if (n > 0) {
      daftarSelected = (daftarSelected + n - 1) % n;
      if (daftarSelected < daftarScroll)
        daftarScroll = daftarSelected;
      uiShowDaftarMhs(arr, daftarScroll, daftarSelected);
    }
  }
}

static void menuMoveDown() {
  if (screen == Screen::Menu) {
    menuIndex = (menuIndex + 1) % MAIN_MENU_COUNT;
    uiShowMenu(menuIndex, MAIN_MENU_COUNT);
  } else if (screen == Screen::NetworkMenu) {
    networkMenuIndex = (networkMenuIndex + 1) % 4;
    uiShowNetworkMenu(networkMenuIndex);
  } else if (screen == Screen::DaftarMhs && daftarLoaded) {
    JsonArray arr = daftarDoc["data"].as<JsonArray>();
    int n = arr.size();
    if (n > 0) {
      daftarSelected = (daftarSelected + 1) % n;
      if (daftarSelected >= daftarScroll + 4)
        daftarScroll = daftarSelected - 3;
      uiShowDaftarMhs(arr, daftarScroll, daftarSelected);
    }
  }
}

static void menuConfirm() {
  if (screen == Screen::Menu)
    menuActivate(menuIndex);
  else if (screen == Screen::NetworkMenu)
    networkMenuActivate(networkMenuIndex);
  else if (screen == Screen::EnrollInput) {
    int slot = digitBuffer.toInt();
    if (slot >= 1 && slot <= FINGER_SLOT_MAX)
      startEnroll(slot);
    else
      showMessage("Enroll", "ID tidak valid", nullptr);
  } else if (screen == Screen::DeleteInput) {
    int slot = digitBuffer.toInt();
    if (slot >= 1 && slot <= FINGER_SLOT_MAX)
      confirmDelete(slot);
    else
      showMessage("Hapus", "ID tidak valid", nullptr);
  } else if (screen == Screen::AntrianOffline) {
    offlineQueueSyncAll(true);
    uiShowAntrian(offlineQueueCount(), antrianScroll);
  } else if (screen == Screen::ClearConfirm) {
    if (sensorOk && finger.emptyDatabase() == FINGERPRINT_OK)
      showMessage("Sensor", "Dikosongkan", nullptr);
    else
      showMessage("Sensor", "Gagal", nullptr);
  } else if (screen == Screen::DaftarMhs && daftarLoaded) {
    JsonArray arr = daftarDoc["data"].as<JsonArray>();
    if (arr.isNull() || daftarSelected < 0 || daftarSelected >= (int)arr.size()) {
      showMessage("Enroll", "Pilih mahasiswa", nullptr);
      return;
    }
    JsonObject o = arr[daftarSelected];
    int fid = o["finger_id"].isNull() ? 0 : o["finger_id"].as<int>();
    if (fid < 1 || fid > FINGER_SLOT_MAX) {
      showMessage("Enroll", "finger_id kosong", nullptr);
      return;
    }
    startEnroll(fid);
  }
}

static void openPinEntryFromHome() {
  uiHomeClearOverlay();
  overlayUntil = 0;
  digitBuffer = "";
  pinFailCount = 0;
  screen = Screen::PinEntry;
  standbyAbsen = false;
  audioStop();
  uiShowInputDigits("PIN MENU", "", "D OK C hapus *batal");
}

static void refreshPinEntryScreen(const char *hint) {
  String masked;
  for (unsigned i = 0; i < digitBuffer.length(); i++)
    masked += '*';
  uiShowInputDigits("PIN MENU", masked, hint ? hint : "D OK C hapus *batal");
}

static void openMainMenuUnlocked() {
  pinFailCount = 0;
  digitBuffer = "";
  screen = Screen::Menu;
  standbyAbsen = false;
  menuIndex = 0;
  // SH1106: clear 2x biar ghost dari layar PIN hilang.
  uiShowMenu(menuIndex, MAIN_MENU_COUNT);
  delay(30);
  uiShowMenu(menuIndex, MAIN_MENU_COUNT);
}

static bool pinMatches() {
  return digitBuffer.equals(String(MENU_PIN));
}

static void submitPinEntry() {
  if (digitBuffer.length() == 0) {
    uiShowInputDigits("PIN MENU", "", "ketik PIN dulu");
    return;
  }
  if (pinMatches()) {
    openMainMenuUnlocked();
    return;
  }

  pinFailCount++;
  digitBuffer = "";
  if (pinFailCount >= PIN_MAX_TRIES) {
    pinFailCount = 0;
    showMessage("PIN", "Terlalu banyak", "Coba lagi nanti", 2500);
    return;
  }
  char hint[22];
  snprintf(hint, sizeof(hint), "salah %u/%u", (unsigned)pinFailCount,
           (unsigned)PIN_MAX_TRIES);
  uiShowInputDigits("PIN SALAH", "", hint);
}

static void handleDigitKey(char k) {
  if (k < '0' || k > '9')
    return;
  if (screen == Screen::PinEntry) {
    if (digitBuffer.length() < PIN_MAX_LEN)
      digitBuffer += k;
    refreshPinEntryScreen("D OK C hapus *batal");
    return;
  }
  if (digitBuffer.length() < 5)
    digitBuffer += k;
  if (screen == Screen::EnrollInput)
    uiShowEnrollInput(digitBuffer, "Ketik finger_id");
  else if (screen == Screen::DeleteInput)
    uiShowInputDigits("HAPUS SLOT", digitBuffer, "finger_id");
}

static void handleNavKey(char k) {
  if (screen == Screen::Home) {
    // Menu: A saja (hindari # yang sering tidak terbaca di keypad PCB)
    if (k == 'A') {
      openPinEntryFromHome();
    } else if (k == '0') {
      uiHomeClearOverlay();
      overlayUntil = 0;
      uiShowHome();
    }
    return;
  }

  if (screen == Screen::PinEntry) {
    // C = hapus digit, * = batal, D/# = OK (JANGAN pakai '5' — bentrok digit PIN)
    if (k == 'C') {
      if (digitBuffer.length() > 0)
        digitBuffer.remove(digitBuffer.length() - 1);
      refreshPinEntryScreen("D OK C hapus *batal");
    } else if (k == '*') {
      pinFailCount = 0;
      goHomeFromMenu();
    } else if (k == 'D' || k == '#') {
      submitPinEntry();
    }
    return;
  }

  if (uiKeyIsUp(k))
    menuMoveUp();
  else if (uiKeyIsDown(k))
    menuMoveDown();
  else if (uiKeyIsOk(k))
    menuConfirm();
  else if (uiKeyIsHome(k) || uiKeyIsBack(k)) {
    if (screen == Screen::EnrollScan) {
      enrollPhase = 0;
      enrollSoundPhase = 0;
      goHomeFromMenu();
    } else if (screen == Screen::NetworkMenu) {
      screen = Screen::Menu;
      uiShowMenu(menuIndex, MAIN_MENU_COUNT);
    } else {
      goHomeFromMenu();
    }
  }
}

static void prosesStandbyTap() {
  // 0005 hanya absen home — jangan ganggu enroll / menu.
  if (!standbyAbsen || !sensorOk || screen != Screen::Home)
    return;
  if (enrollPhase != 0 || audioIsEnrollMode())
    return;
  if (millis() < ignoreTapUntil)
    return;
  if (millis() - lastTapAt < TAP_COOLDOWN_MS)
    return;
  if (overlayUntil > millis())
    return;

  if (finger.getImage() != FINGERPRINT_OK)
    return;
  if (finger.image2Tz() != FINGERPRINT_OK)
    return;

  lastTapAt = millis();

  if (finger.fingerSearch() != FINGERPRINT_OK) {
    showHomeOverlay("TIDAK DIKENALI", "Jari tidak", "terdaftar", RESULT_SCREEN_FAIL_MS);
    delay(150);
    audioStop();
    delay(40);
    audioPlay(AUDIO_NOT_REGISTERED); // 0005
    return;
  }

  int fid = finger.fingerID;

  if (shouldUseOffline()) {
    if (!uiTimeIsValid()) {
      showHomeOverlay("OFFLINE", "Butuh waktu", "Hubung WiFi dulu", 2500);
      return;
    }
    processTapOffline(fid);
  } else {
    kirimTap(fid, nullptr, true);
  }
}

void uiAppInit() {
  uiGoStandby();
  uiShowBoot();
  delay(500);
  uiShowHome();
}

void uiAppTick() {
  wifiServicesTick();

  bool wifiNow = wifiOk();
  if (wifiNow && !lastWifiConnected) {
    syncNtpTime();
    offlineQueueSyncAll(false);
    if (forceOfflineMode) {
      // WiFi kembali setelah offline otomatis: tetap bisa sync, mode manual tetap.
    }
  }
  if (!wifiNow && lastWifiConnected && !forceOfflineMode) {
    forceOfflineMode = true;
  }
  lastWifiConnected = wifiNow;

  char k = NO_KEY;
  for (int i = 0; i < 3; i++) {
    char c = uiKeypadPoll();
    if (c != NO_KEY)
      k = c;
  }
  if (k != NO_KEY) {
#if DEBUG_SERIAL
    Serial.printf("[Key] '%c' (%d) scr=%d\n", k, (int)k, (int)screen);
#endif
    if ((k >= '0' && k <= '9') &&
        (screen == Screen::EnrollInput || screen == Screen::DeleteInput ||
         screen == Screen::PinEntry)) {
      handleDigitKey(k);
    } else {
      handleNavKey(k);
    }
  }

  if (screen == Screen::Home && overlayUntil > 0 && millis() >= overlayUntil) {
    uiHomeClearOverlay();
    overlayUntil = 0;
    uiShowHome();
  }

  if (screen == Screen::Message && millis() >= messageUntil) {
    goHomeFromMenu();
  }

  enrollTick();

  if (screen == Screen::Home) {
    if (millis() - lastHeartbeatAt >= HEARTBEAT_MS)
      kirimHeartbeat(false);
    if (millis() - lastSyncAt >= SYNC_INTERVAL_MS)
      runFingerprintSync(false);
    if (millis() - lastOfflineSyncAt >= OFFLINE_SYNC_INTERVAL_MS) {
      lastOfflineSyncAt = millis();
      if (wifiNow)
        offlineQueueSyncAll(false);
      if (wifiNow && !ntpSynced())
        syncNtpTime();
    }
    prosesStandbyTap();

    static unsigned long lastClockDraw = 0;
    // Jangan redraw jam saat overlay hasil tap aktif (hindari nabrak UI).
    if (overlayUntil == 0 && millis() - lastClockDraw >= 1000) {
      lastClockDraw = millis();
      uiShowHome();
    }
  }
}
