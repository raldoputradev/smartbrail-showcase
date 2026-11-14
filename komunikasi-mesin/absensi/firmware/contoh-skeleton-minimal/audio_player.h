#pragma once

#include <Arduino.h>

/**
 * microSD FAT32 → folder /mp3/
 *
 *   0001 — Sedang menghubungkan ke jaringan.
 *   0002 — Koneksi berhasil. Mesin absensi siap digunakan.
 *   0003 — Gagal terhubung ke jaringan.
 *   0004 — Absensi berhasil, terima kasih.
 *   0005 — Identitas / sidik jari tidak terdaftar.  ← HANYA absen home
 *   0006 — Silakan tempelkan jari Anda.             ← enroll
 *   0007 — Angkat jari Anda, lalu tempelkan kembali.← enroll
 *   0008 — Registrasi sidik jari berhasil.          ← enroll
 *   0009 — Registrasi gagal. Silakan ulangi.        ← enroll
 *   0010 — Belum waktunya absen pulang.
 *   0011 — Maaf, absensi tidak dapat dilakukan.
 *
 * Mode enroll: audioSetEnrollMode(true) → 0004/0005 DIBLOKIR di audioPlay().
 */
enum AudioTrack : uint8_t {
  AUDIO_NET_CONNECTING = 1,
  AUDIO_NET_OK = 2,
  AUDIO_NET_FAIL = 3,
  AUDIO_ABSEN_OK = 4,
  AUDIO_NOT_REGISTERED = 5,
  AUDIO_PLACE_FINGER = 6,
  AUDIO_LIFT_FINGER = 7,
  AUDIO_ENROLL_OK = 8,
  AUDIO_ENROLL_FAIL = 9,
  AUDIO_WAIT_PULANG = 10,
  AUDIO_HARI_LIBUR = 11,
};

#define AUDIO_TRACK_MAX 11

#define AUDIO_NET_CONNECTING_MIN_MS 4500
#define AUDIO_NET_CONNECTING_MAX_MS 10000
#define AUDIO_NET_FAIL_MIN_MS 3500
#define AUDIO_NET_FAIL_MAX_MS 7000

bool audioInit();
bool audioReady();
void audioStop();

/** true = mode enroll: tolak 0004/0005 (absen). */
void audioSetEnrollMode(bool on);
bool audioIsEnrollMode();

void audioPlay(uint8_t track);
void audioPlayThenWait(uint8_t track, unsigned waitMs);
void audioWaitFinished(unsigned minMs, unsigned maxMs);
void audioWaitFrom(unsigned long startedAt, unsigned minMs, unsigned maxMs);
bool audioPollPlayFinished();
void audioPlayBlocking(uint8_t track, unsigned minMs, unsigned maxMs);
