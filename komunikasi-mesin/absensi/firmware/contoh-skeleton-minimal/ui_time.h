#pragma once

#include <stddef.h>
#include <time.h>

/** No-op (RTC dinonaktifkan). Waktu dari NTP saat WiFi connect. */
void uiTimeInitRtc();

/** Waktu valid (NTP / jam software ESP32 setelah sync). */
bool uiTimeIsValid();

bool uiTimeGetTm(struct tm *out);

/** Format 24 jam: HH:MM:SS */
void uiTimeFormatClock24(char *buf, size_t bufLen);

/** Bar atas: Sen 25/06 */
void uiTimeFormatTopDate(char *buf, size_t bufLen);

/** Sapaan berdasarkan jam lokal. */
const char *uiTimeGreeting();
