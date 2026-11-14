#include "app_globals.h"

#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <cstring>
#include <time.h>

#include "app_config.h"
#include "offline_queue.h"
#include "audio_player.h"
#include "ui_app.h"
#include "ui_oled.h"
#include "ui_time.h"
#include "wifi_portal.h"

#if DEBUG_SERIAL
#include <Arduino.h>
#endif

Adafruit_Fingerprint finger = Adafruit_Fingerprint(&Serial2);
bool sensorOk = false;
bool standbyAbsen = true;
bool forceOfflineMode = false;
unsigned long lastTapAt = 0;
unsigned long lastHeartbeatAt = 0;
unsigned long lastSyncAt = 0;
unsigned long lastOfflineSyncAt = 0;
uint32_t absenSeq = 0;
char lastSyncToken[40] = "";
uint8_t fingerModelBuf[FINGER_TEMPLATE_BYTES];
char templateHexOut[FINGER_TEMPLATE_BYTES * 2 + 1];

static bool ntpReady = false;

#define FINGERPRINT_DOWNCHAR 0x09
static const char HEX_DIGITS[] = "0123456789abcdef";

bool wifiOk() { return WiFi.status() == WL_CONNECTED; }

void syncNtpTime() {
  if (!wifiOk())
    return;

  // Beberapa server: lab/kampus sering blok salah satu NTP.
  configTime(GMT_OFFSET_SEC, DAYLIGHT_OFFSET_SEC, "time.google.com", "pool.ntp.org",
             "id.pool.ntp.org");

  for (int i = 0; i < 40; i++) { // ~8 detik
    time_t now = time(nullptr);
    if (now > 1700000000) {
      ntpReady = true;
#if DEBUG_SERIAL
      struct tm ti;
      localtime_r(&now, &ti);
      Serial.printf("[NTP] OK %04d-%02d-%02d %02d:%02d:%02d WIB\n", ti.tm_year + 1900,
                    ti.tm_mon + 1, ti.tm_mday, ti.tm_hour, ti.tm_min, ti.tm_sec);
#endif
      return;
    }
    delay(200);
  }

  ntpReady = false;
#if DEBUG_SERIAL
  Serial.println(F("[NTP] GAGAL — jam OLED tetap --:--:-- (cek DNS/firewall WiFi)"));
#endif
}

bool ntpSynced() { return ntpReady; }

String isoTimeNow() {
  struct tm ti;
  if (!uiTimeGetTm(&ti))
    return String("");

  char buf[32];
  // Format 24 jam dengan offset tetap +07:00 (WIB).
  strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%S", &ti);
  return String(buf) + "+07:00";
}

void connectWiFi() {
  // 0001: putar, WiFi berbarengan, tunggu clip selesai (dari tPlay) — jangan stop paksa.
  unsigned long tPlay = millis();
  audioPlay(AUDIO_NET_CONNECTING);

  char ssid[64];
  char pass[64];
  wifiLoadCredentials(ssid, sizeof(ssid), pass, sizeof(pass));

  wifiApStop();
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, pass);
#if DEBUG_SERIAL
  Serial.printf("WiFi STA SSID=%s ", ssid);
#endif

  bool connectingDone = false;
  for (int i = 0; i < 50 && !wifiOk(); i++) {
    if (audioPollPlayFinished())
      connectingDone = true;
    delay(300);
#if DEBUG_SERIAL
    Serial.print('.');
#endif
  }
#if DEBUG_SERIAL
  Serial.println();
  if (wifiOk())
    Serial.println(WiFi.localIP());
  else
    Serial.println(F("[WiFi gagal]"));
#endif

  // Timer dari mulai 0001. Jika sudah selesai saat WiFi, tetap hormati min durasi.
  if (connectingDone) {
    unsigned long elapsed = millis() - tPlay;
    if (elapsed < AUDIO_NET_CONNECTING_MIN_MS)
      delay(AUDIO_NET_CONNECTING_MIN_MS - elapsed);
  } else {
    audioWaitFrom(tPlay, AUDIO_NET_CONNECTING_MIN_MS, AUDIO_NET_CONNECTING_MAX_MS);
  }

  if (wifiOk()) {
    forceOfflineMode = false;
    syncNtpTime();
    // 0002 diputar nanti setelah OLED home — hanya jika STA benar-benar connect.
  } else {
    forceOfflineMode = true;
    audioStop();
    delay(120);
    audioPlayBlocking(AUDIO_NET_FAIL, AUDIO_NET_FAIL_MIN_MS, AUDIO_NET_FAIL_MAX_MS);
#if DEBUG_SERIAL
    Serial.println(F("[Audio] WiFi gagal → track 0003 (bukan 0002)"));
#endif
#if WIFI_AP_ENABLE
    wifiApStart();
#endif
  }
}

void wifiServicesTick() {
  wifiPortalTick();

  // Setelah simpan kredensial dari portal, SoftAP stop & STA begin —
  // pantau sampai connect lalu sync sekali.
  static bool wasAp = false;
  static bool pendingStaSync = false;
  bool nowAp = wifiApActive();
  if (wasAp && !nowAp)
    pendingStaSync = true;
  wasAp = nowAp;

  if (pendingStaSync && wifiOk() && !wifiApActive()) {
    pendingStaSync = false;
    forceOfflineMode = false;
    syncNtpTime();
    offlineQueueSyncAll(true);
    runFingerprintSync(false);
    kirimHeartbeat(false);
    uiGoStandby();
    delay(500);
    audioStop();
    delay(80);
    audioPlay(AUDIO_NET_OK);
#if DEBUG_SERIAL
    Serial.println(F("[WiFi] STA OK setelah setup SoftAP → track 0002"));
#endif
  }
}

static void applyMesinHeaders(HTTPClient &http) {
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-KEY", API_KEY);
  http.addHeader("X-ID-MESIN", ID_MESIN);
}

#if DEBUG_SERIAL
static void printHttpHint(int code) {
  if (code < 0)
    Serial.println(F(">> Cek API_BASE (https://iotsimalas.my.id/api)"));
}
#endif

static bool httpShouldRetry(int code) {
  if (code < 0)
    return true;
  if (code >= 500)
    return true;
  if (code == 408 || code == 429)
    return true;
  return false;
}

static bool httpBeginUrl(HTTPClient &http, const String &url) {
  if (url.startsWith("https://")) {
    static WiFiClientSecure client;
    client.setInsecure();
    return http.begin(client, url);
  }
  return http.begin(url);
}

bool httpRequestRaw(const String &method, const String &url, const String &body,
                    DynamicJsonDocument &out, int *httpCode) {
  if (!wifiOk())
    return false;

  for (int attempt = 1; attempt <= HTTP_RETRY_MAX; attempt++) {
    HTTPClient http;
    if (!httpBeginUrl(http, url)) {
#if DEBUG_SERIAL
      Serial.println(F("[HTTP] begin gagal"));
#endif
      return false;
    }
    applyMesinHeaders(http);
    http.setTimeout(HTTP_TIMEOUT_MS);

    int c = -1;
    String resp;
    if (method == "GET") {
      c = http.GET();
      resp = http.getString();
    } else if (method == "POST") {
      c = http.POST(body);
      resp = http.getString();
    } else if (method == "DELETE") {
      c = http.sendRequest("DELETE", (uint8_t *)body.c_str(), body.length());
      resp = http.getString();
    }
    http.end();

    if (httpCode)
      *httpCode = c;

    deserializeJson(out, resp);
    if (c == 200 && out["success"] == true)
      return true;

    if (!httpShouldRetry(c) || attempt >= HTTP_RETRY_MAX) {
#if DEBUG_SERIAL
      Serial.printf("[HTTP %d] %s\n", c, resp.c_str());
      printHttpHint(c);
#endif
      return false;
    }

#if DEBUG_SERIAL
    Serial.printf("[HTTP retry %d/%d] code=%d\n", attempt, HTTP_RETRY_MAX, c);
#endif
    delay(HTTP_RETRY_DELAY_MS);
  }
  return false;
}

bool httpPostJson(const String &url, const String &body, DynamicJsonDocument &out,
                  int *httpCode) {
  return httpRequestRaw("POST", url, body, out, httpCode);
}

bool httpGetJson(const String &url, DynamicJsonDocument &out, int *httpCode) {
  return httpRequestRaw("GET", url, String(""), out, httpCode);
}

bool httpDeleteJson(const String &url, const String &body, DynamicJsonDocument &out,
                    int *httpCode) {
  return httpRequestRaw("DELETE", url, body, out, httpCode);
}

void bytesToHex(const uint8_t *src, size_t len, char *dst) {
  for (size_t i = 0; i < len; i++) {
    dst[i * 2] = HEX_DIGITS[(src[i] >> 4) & 0xF];
    dst[i * 2 + 1] = HEX_DIGITS[src[i] & 0xF];
  }
  dst[len * 2] = '\0';
}

bool hexToBytes(const char *hex, uint8_t *dst, size_t expectedLen) {
  if (!hex)
    return false;
  size_t hexLen = strlen(hex);
  if (hexLen != expectedLen * 2)
    return false;
  auto nibble = [](char c) -> int {
    if (c >= '0' && c <= '9')
      return c - '0';
    if (c >= 'a' && c <= 'f')
      return c - 'a' + 10;
    if (c >= 'A' && c <= 'F')
      return c - 'A' + 10;
    return -1;
  };
  for (size_t i = 0; i < expectedLen; i++) {
    int hi = nibble(hex[i * 2]);
    int lo = nibble(hex[i * 2 + 1]);
    if (hi < 0 || lo < 0)
      return false;
    dst[i] = (uint8_t)((hi << 4) | lo);
  }
  return true;
}

bool sensorSlotHasTemplate(int slot) {
  return finger.loadModel(slot) == FINGERPRINT_OK;
}

static bool readUploadBytesFromSensor(uint8_t *fingerTemplate) {
  uint8_t bytesReceived[534];
  memset(bytesReceived, 0xff, 534);
  uint32_t starttime = millis();
  int i = 0;
  while (i < 534 && (millis() - starttime) < 20000) {
    if (Serial2.available())
      bytesReceived[i++] = Serial2.read();
  }
  if (i < 534)
    return false;
  int uindx = 9;
  int index = 0;
  memcpy(fingerTemplate + index, bytesReceived + uindx, 256);
  uindx += 256;
  uindx += 2;
  uindx += 9;
  index += 256;
  memcpy(fingerTemplate + index, bytesReceived + uindx, 256);
  return true;
}

bool readTemplateFromSlot(int slot, uint8_t *buf) {
  if (finger.loadModel(slot) != FINGERPRINT_OK)
    return false;
  if (finger.getModel() != FINGERPRINT_OK)
    return false;
  return readUploadBytesFromSensor(buf);
}

static bool fingerWaitAck(Adafruit_Fingerprint &fp, uint16_t timeoutMs) {
  uint8_t replyBuf[64];
  memset(replyBuf, 0, sizeof(replyBuf));
  Adafruit_Fingerprint_Packet reply(FINGERPRINT_ACKPACKET, 1, replyBuf);
  if (fp.getStructuredPacket(&reply, timeoutMs) != FINGERPRINT_OK)
    return false;
  return reply.type == FINGERPRINT_ACKPACKET && reply.data[0] == FINGERPRINT_OK;
}

static bool fingerCmdAck(Adafruit_Fingerprint &fp, const uint8_t *data, uint8_t len) {
  uint8_t payload[8];
  if (len > sizeof(payload))
    return false;
  memcpy(payload, data, len);
  Adafruit_Fingerprint_Packet cmd(FINGERPRINT_COMMANDPACKET, len, payload);
  fp.writeStructuredPacket(cmd);
  return fingerWaitAck(fp, 3000);
}

bool writeTemplateToSlot(int slot, const uint8_t *buf) {
  uint8_t downCmd[] = {FINGERPRINT_DOWNCHAR, 0x01};
  if (!fingerCmdAck(finger, downCmd, sizeof(downCmd)))
    return false;
  const int chunk = 64;
  for (int off = 0; off < FINGER_TEMPLATE_BYTES; off += chunk) {
    Adafruit_Fingerprint_Packet dataPkt(FINGERPRINT_DATAPACKET, chunk,
                                        (uint8_t *)(buf + off));
    finger.writeStructuredPacket(dataPkt);
    delay(3);
  }
  uint8_t endBuf[64] = {0};
  Adafruit_Fingerprint_Packet endPkt(FINGERPRINT_ENDDATAPACKET, 0, endBuf);
  finger.writeStructuredPacket(endPkt);
  if (!fingerWaitAck(finger, 5000))
    return false;
  return finger.storeModel(slot) == FINGERPRINT_OK;
}

void runFingerprintSync(bool verbose) {
  if (!wifiOk() || !sensorOk)
    return;

  int after = 0;
  int totalPushed = 0;
  int totalSkipped = 0;

  for (int page = 0; page < 40; page++) {
    String url = String(API_BASE) + "/mesin/fingerprint/sync?id_mesin=" +
                 String(ID_MESIN) + "&limit=" + String(SYNC_BATCH_LIMIT) +
                 "&after_finger_id=" + String(after);
    if (lastSyncToken[0]) {
      url += "&since=";
      url += lastSyncToken;
    }

    DynamicJsonDocument doc(28672);
    if (!httpGetJson(url, doc))
      break;

    JsonObject data = doc["data"];
    if (data.isNull())
      break;

    const char *token = data["sync_token"];
    if (token && token[0]) {
      strncpy(lastSyncToken, token, sizeof(lastSyncToken) - 1);
      lastSyncToken[sizeof(lastSyncToken) - 1] = '\0';
    }

    JsonArray fps = data["fingerprints"].as<JsonArray>();
    if (fps.isNull() || fps.size() == 0)
      break;

    int maxId = after;
    for (JsonObject fp : fps) {
      int slot = fp["finger_id"] | 0;
      const char *hex = fp["template_hex"];
      if (slot < 1 || slot > FINGER_SLOT_MAX)
        continue;
      if (slot > maxId)
        maxId = slot;
      if (sensorSlotHasTemplate(slot)) {
        totalSkipped++;
        continue;
      }
      if (!hex || !hexToBytes(hex, fingerModelBuf, FINGER_TEMPLATE_BYTES))
        continue;
      if (writeTemplateToSlot(slot, fingerModelBuf)) {
        totalPushed++;
#if DEBUG_SERIAL
        if (verbose)
          Serial.printf("[Sync] slot %d ditulis\n", slot);
#endif
      }
    }
    after = maxId;
    if (fps.size() < SYNC_BATCH_LIMIT)
      break;
  }

  lastSyncAt = millis();
#if DEBUG_SERIAL
  if (totalPushed > 0)
    Serial.printf("[Sync] +%d template\n", totalPushed);
  else if (verbose)
    Serial.printf("[Sync] mutakhir skip=%d\n", totalSkipped);
#endif
}

void kirimHeartbeat(bool verbose) {
  if (!wifiOk())
    return;
  DynamicJsonDocument req(96), res(512);
  req["id_mesin"] = ID_MESIN;
  String payload;
  serializeJson(req, payload);
  int code = 0;
  if (httpPostJson(String(API_BASE) + "/mesin/heartbeat", payload, res, &code)) {
    lastHeartbeatAt = millis();
#if DEBUG_SERIAL
    if (verbose)
      Serial.println(F("[Heartbeat] OK"));
#endif
    return;
  }
#if DEBUG_SERIAL
  if (verbose)
    Serial.printf("[Heartbeat] HTTP %d\n", code);
#endif
}

// Forward declare for tap result UI — set by ui_app
extern void uiOnTapResult(bool ok, int fid, JsonObject d, const char *msg, const char *code,
                           bool offlineSaved);

bool kirimTap(int fid, const char *waktuTapIso, bool showUi) {
  if (fid < 1 || fid > FINGER_SLOT_MAX)
    return false;

  DynamicJsonDocument req(256), res(2048);
  req["id_mesin"] = ID_MESIN;
  req["finger_id"] = fid;
  if (waktuTapIso && strlen(waktuTapIso))
    req["waktu_tap"] = waktuTapIso;

  String payload;
  serializeJson(req, payload);

  int code = 0;
  char msgBuf[120];
  char codeBuf[40];
  msgBuf[0] = '\0';
  codeBuf[0] = '\0';

  if (!httpPostJson(String(API_BASE) + "/mesin/tap", payload, res, &code)) {
    if (showUi) {
      strncpy(msgBuf, res["message"] | "Tap gagal", sizeof(msgBuf) - 1);
      msgBuf[sizeof(msgBuf) - 1] = '\0';
      strncpy(codeBuf, res["code"] | "", sizeof(codeBuf) - 1);
      codeBuf[sizeof(codeBuf) - 1] = '\0';
      uiOnTapResult(false, fid, JsonObject(), msgBuf, codeBuf, false);
    }
    return false;
  }

  if (showUi) {
    JsonObject d = res["data"];
    strncpy(msgBuf, res["message"] | "OK", sizeof(msgBuf) - 1);
    msgBuf[sizeof(msgBuf) - 1] = '\0';
    strncpy(codeBuf, res["code"] | "", sizeof(codeBuf) - 1);
    codeBuf[sizeof(codeBuf) - 1] = '\0';
    uiOnTapResult(true, fid, d, msgBuf, codeBuf, false);
  }
  return true;
}

bool processTapOffline(int fid) {
  String ts = isoTimeNow();
  if (ts.length() == 0) {
    uiOnTapResult(false, fid, JsonObject(), "Connect WiFi dulu utk jam",
                  "NO_NTP", false);
    return false;
  }
  if (!offlineQueuePush(fid, ts.c_str())) {
    uiOnTapResult(false, fid, JsonObject(), "Antrian penuh", "QUEUE_FULL", false);
    return false;
  }
  uiOnTapResult(true, fid, JsonObject(), "Tersimpan offline", "", true);
  uiOledSetNeedsRedraw();
  return true;
}
