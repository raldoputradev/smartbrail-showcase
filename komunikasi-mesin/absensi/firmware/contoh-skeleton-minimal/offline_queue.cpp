#include "offline_queue.h"

#include <ArduinoJson.h>
#include <LittleFS.h>

#include "app_config.h"
#include "app_globals.h"

static const char *QUEUE_PATH = "/tap_queue.json";
static bool fsReady = false;

static bool ensureFs() {
  if (fsReady)
    return true;
  if (!LittleFS.begin(true))
    return false;
  fsReady = true;
  return true;
}

void offlineQueueInit() {
  ensureFs();
}

size_t offlineQueueCount() {
  if (!ensureFs() || !LittleFS.exists(QUEUE_PATH))
    return 0;

  File f = LittleFS.open(QUEUE_PATH, "r");
  if (!f)
    return 0;

  DynamicJsonDocument doc(8192);
  if (deserializeJson(doc, f))
    return 0;
  f.close();

  JsonArray arr = doc.as<JsonArray>();
  return arr.isNull() ? 0 : arr.size();
}

static bool saveQueue(JsonArray arr) {
  File f = LittleFS.open(QUEUE_PATH, "w");
  if (!f)
    return false;
  serializeJson(arr, f);
  f.close();
  return true;
}

bool offlineQueuePush(int fingerId, const char *waktuTapIso) {
  if (!ensureFs() || fingerId < 1)
    return false;

  DynamicJsonDocument doc(8192);
  JsonArray arr;

  if (LittleFS.exists(QUEUE_PATH)) {
    File f = LittleFS.open(QUEUE_PATH, "r");
    if (f) {
      deserializeJson(doc, f);
      f.close();
    }
  }
  arr = doc.to<JsonArray>();
  if (arr.size() >= OFFLINE_QUEUE_MAX)
    return false;

  JsonObject item = arr.createNestedObject();
  item["finger_id"] = fingerId;
  item["waktu_tap"] = waktuTapIso ? waktuTapIso : isoTimeNow();
  item["tries"] = 0;

  return saveQueue(arr);
}

bool offlineQueueSyncOne(bool &removed) {
  removed = false;
  if (!ensureFs() || !wifiOk() || !LittleFS.exists(QUEUE_PATH))
    return false;

  File f = LittleFS.open(QUEUE_PATH, "r");
  if (!f)
    return false;

  DynamicJsonDocument doc(8192);
  if (deserializeJson(doc, f)) {
    f.close();
    return false;
  }
  f.close();

  JsonArray arr = doc.as<JsonArray>();
  if (arr.isNull() || arr.size() == 0)
    return true;

  JsonObject first = arr[0];
  int fid = first["finger_id"] | 0;
  const char *waktu = first["waktu_tap"] | "";
  int tries = first["tries"] | 0;

  if (fid < 1) {
    arr.remove(0);
    saveQueue(arr);
    removed = true;
    return true;
  }

  if (kirimTap(fid, waktu, false)) {
    arr.remove(0);
    saveQueue(arr);
    removed = true;
    return true;
  }

  tries++;
  first["tries"] = tries;
  if (tries >= OFFLINE_RETRY_MAX) {
    arr.remove(0);
#if DEBUG_SERIAL
    Serial.printf("[Offline] drop finger %d setelah %d gagal\n", fid, tries);
#endif
  }
  saveQueue(arr);
  return false;
}

void offlineQueueSyncAll(bool verbose) {
  if (!wifiOk())
    return;

  int synced = 0;
  bool removed = false;
  while (offlineQueueCount() > 0) {
    if (!offlineQueueSyncOne(removed))
      break;
    if (removed)
      synced++;
    else
      break;
  }

#if DEBUG_SERIAL
  if (verbose && synced > 0)
    Serial.printf("[Offline] %d tap terkirim\n", synced);
#endif
}
