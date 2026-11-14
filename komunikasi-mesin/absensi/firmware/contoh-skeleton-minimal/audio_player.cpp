#include "audio_player.h"

#include <DFRobotDFPlayerMini.h>
#include <HardwareSerial.h>

#include "app_config.h"
#include "config_pins.h"

#if DEBUG_SERIAL
#include <Arduino.h>
#endif

static HardwareSerial audioSerial(1);
static DFRobotDFPlayerMini player;
static bool audioOk = false;
static bool enrollMode = false;

bool audioReady() { return audioOk; }

void audioSetEnrollMode(bool on) {
  enrollMode = on;
#if DEBUG_SERIAL
  Serial.printf("[Audio] enrollMode=%d\n", (int)on);
#endif
}

bool audioIsEnrollMode() { return enrollMode; }

bool audioInit() {
#if !AUDIO_ENABLED
  audioOk = false;
  return false;
#endif

  delay(800);
  audioSerial.begin(9600, SERIAL_8N1, DFPLAYER_RX, DFPLAYER_TX);
  delay(300);

  if (!player.begin(audioSerial)) {
#if DEBUG_SERIAL
    Serial.println(F("DFPlayer: gagal init"));
#endif
    audioOk = false;
    return false;
  }

  player.volume(30);
  player.EQ(DFPLAYER_EQ_NORMAL);
  delay(150);
  player.volume(constrain(DFPLAYER_VOLUME, 0, 30));
  audioOk = true;
  enrollMode = false;

#if DEBUG_SERIAL
  Serial.println(F("DFPlayer: OK (/mp3 0001-0011)"));
#endif
  return true;
}

void audioStop() {
  if (!audioOk)
    return;
  player.stop();
  delay(100);
}

void audioPlay(uint8_t track) {
  if (!audioOk || track < 1 || track > AUDIO_TRACK_MAX)
    return;

  // Saat enroll: jangan pernah putar suara absen (0004/0005).
  if (enrollMode && (track == AUDIO_NOT_REGISTERED || track == AUDIO_ABSEN_OK ||
                     track == AUDIO_WAIT_PULANG || track == AUDIO_HARI_LIBUR)) {
#if DEBUG_SERIAL
    Serial.printf("[Audio] BLOKIR track %u (enrollMode) — bukan suara enroll\n",
                  (unsigned)track);
#endif
    return;
  }

  player.volume(constrain(DFPLAYER_VOLUME, 0, 30));
  delay(50);
  // Wajib folder /mp3/0001.mp3 … 0011.mp3 (nama file = nomor track).
  player.playMp3Folder(track);
#if DEBUG_SERIAL
  Serial.printf("[Audio] play /mp3/%04u.mp3 enrollMode=%d\n", (unsigned)track,
                (int)enrollMode);
#endif
}

void audioPlayThenWait(uint8_t track, unsigned waitMs) {
  audioPlay(track);
  if (waitMs > 0)
    delay(waitMs);
}

bool audioPollPlayFinished() {
  if (!audioOk)
    return false;
  bool finished = false;
  while (player.available()) {
    if (player.readType() == DFPlayerPlayFinished)
      finished = true;
  }
  return finished;
}

void audioWaitFinished(unsigned minMs, unsigned maxMs) {
  audioWaitFrom(millis(), minMs, maxMs);
}

void audioWaitFrom(unsigned long startedAt, unsigned minMs, unsigned maxMs) {
  if (!audioOk) {
    unsigned long until = startedAt + minMs;
    while ((long)(millis() - until) < 0)
      delay(30);
    return;
  }
  if (maxMs < minMs)
    maxMs = minMs;

  bool finished = false;
  for (;;) {
    if (audioPollPlayFinished())
      finished = true;

    unsigned long elapsed = millis() - startedAt;
    if (finished && elapsed >= minMs) {
#if DEBUG_SERIAL
      Serial.println(F("[Audio] play finished"));
#endif
      delay(80);
      return;
    }
    if (elapsed >= maxMs) {
#if DEBUG_SERIAL
      Serial.println(F("[Audio] wait timeout"));
#endif
      return;
    }
    delay(40);
  }
}

void audioPlayBlocking(uint8_t track, unsigned minMs, unsigned maxMs) {
  unsigned long t0 = millis();
  audioPlay(track);
  audioWaitFrom(t0, minMs, maxMs);
}
