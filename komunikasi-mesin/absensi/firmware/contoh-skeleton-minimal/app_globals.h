#pragma once

#include <Adafruit_Fingerprint.h>
#include <ArduinoJson.h>
#include <WiFi.h>

#include "app_config.h"

extern Adafruit_Fingerprint finger;
extern bool sensorOk;
extern bool standbyAbsen;
extern bool forceOfflineMode;
extern unsigned long lastTapAt;
extern unsigned long lastHeartbeatAt;
extern unsigned long lastSyncAt;
extern unsigned long lastOfflineSyncAt;
extern uint32_t absenSeq;
extern char lastSyncToken[40];
extern uint8_t fingerModelBuf[FINGER_TEMPLATE_BYTES];
extern char templateHexOut[FINGER_TEMPLATE_BYTES * 2 + 1];

bool wifiOk();
void connectWiFi();
void syncNtpTime();
void wifiServicesTick();

bool httpGetJson(const String &url, DynamicJsonDocument &out, int *httpCode = nullptr);
bool httpPostJson(const String &url, const String &body, DynamicJsonDocument &out,
                  int *httpCode = nullptr);
bool httpDeleteJson(const String &url, const String &body, DynamicJsonDocument &out,
                    int *httpCode = nullptr);

void kirimHeartbeat(bool verbose = false);
void runFingerprintSync(bool verbose);
bool kirimTap(int fid, const char *waktuTapIso = nullptr, bool showUi = true);
bool processTapOffline(int fid);

bool sensorSlotHasTemplate(int slot);
bool readTemplateFromSlot(int slot, uint8_t *buf);
bool writeTemplateToSlot(int slot, const uint8_t *buf);
void bytesToHex(const uint8_t *src, size_t len, char *dst);
bool hexToBytes(const char *hex, uint8_t *dst, size_t expectedLen);

String isoTimeNow();
bool ntpSynced();
