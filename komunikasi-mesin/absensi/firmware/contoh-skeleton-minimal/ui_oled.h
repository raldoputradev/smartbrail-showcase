#pragma once

#include <Arduino.h>
#include <ArduinoJson.h>

bool uiOledInit();
bool uiOledIsOk();
void uiOledPrintBootSummary();
void uiOledTick();
void uiOledSetNeedsRedraw();

void uiShowBoot();
void uiShowHome();
void uiHomeSetOverlay(const char *title, const char *line1, const char *line2 = nullptr);
void uiHomeClearOverlay();

void uiShowMenu(int selected, int menuCount);
void uiShowNetworkMenu(int selected);
void uiShowMessage(const char *title, const char *line1, const char *line2 = nullptr);
void uiShowEnrollInput(const String &digits, const char *hint);
void uiShowEnrollScan(int step, const char *status);
void uiShowInfo();
void uiShowDaftarMhs(JsonArray arr, int scrollOffset, int selected);
void uiShowAntrian(size_t count, int scrollOffset);
void uiShowInputDigits(const char *title, const String &digits, const char *hint);

/** @deprecated gunakan uiShowHome + overlay */
void uiShowStandby();
