#pragma once

#include <Arduino.h>
#include <Keypad.h>

void uiKeypadInit();
char uiKeypadPoll();

bool uiKeyIsMenu(char k);
bool uiKeyIsUp(char k);
bool uiKeyIsDown(char k);
bool uiKeyIsOk(char k);
bool uiKeyIsBack(char k);
bool uiKeyIsHome(char k);
