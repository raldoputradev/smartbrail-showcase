#pragma once

#include <Arduino.h>
#include <ArduinoJson.h>

void uiAppInit();
void uiAppTick();
void uiOnTapResult(bool ok, int fid, JsonObject d, const char *msg, const char *code,
                   bool offlineSaved);
bool uiIsStandbyScreen();
void uiGoStandby();
