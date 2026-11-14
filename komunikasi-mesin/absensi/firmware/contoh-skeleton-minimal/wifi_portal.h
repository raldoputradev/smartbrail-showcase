#pragma once

#include <Arduino.h>

/** Mode SoftAP aktif (mesin memancarkan WiFi sendiri). */
bool wifiApActive();

/** SSID SoftAP (untuk tampilan OLED). */
const char *wifiApSsid();

/**
 * SoftAP + portal web (192.168.4.1).
 * 1) Hubungkan HP ke WiFi SoftAP (password WIFI_AP_PASS)
 * 2) Buka http://192.168.4.1
 * 3) Masukkan PIN (WIFI_SETUP_PIN) dulu
 * 4) Isi SSID + password WiFi lab → disimpan, mesin reconnect
 */
void wifiApStart();
void wifiApStop();
void wifiPortalTick();

/** Baca kredensial lab dari NVS (fallback ke WIFI_SSID/PASSWORD di app_config). */
void wifiLoadCredentials(char *ssid, size_t ssidLen, char *pass, size_t passLen);
bool wifiSaveCredentials(const char *ssid, const char *pass);
