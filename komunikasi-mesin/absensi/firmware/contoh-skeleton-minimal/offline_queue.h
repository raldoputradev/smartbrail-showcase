#pragma once

#include <Arduino.h>

void offlineQueueInit();
size_t offlineQueueCount();
bool offlineQueuePush(int fingerId, const char *waktuTapIso);
bool offlineQueueSyncOne(bool &removed);
void offlineQueueSyncAll(bool verbose = false);
