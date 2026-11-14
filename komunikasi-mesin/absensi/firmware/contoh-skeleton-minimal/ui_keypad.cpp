#include "ui_keypad.h"

#include <Keypad.h>

#include "app_config.h"
#include "config_pins.h"

// Layout standar modul 4x4 (PCB SIMALAS v2 — L1..L4 = baris atas→bawah):
//   1 2 3 A
//   4 5 6 B
//   7 8 9 C
//   * 0 # D
// Jangan tukar L3/L4: kalau ditukar → * terbaca 7, 0→8, #→9, D→C (keluhan user).
static char keymap[KEYPAD_ROWS][KEYPAD_COLS] = {
    {'1', '2', '3', 'A'},
    {'4', '5', '6', 'B'},
    {'7', '8', '9', 'C'},
    {'*', '0', '#', 'D'},
};

static byte rowPins[KEYPAD_ROWS];
static byte colPins[KEYPAD_COLS];
static Keypad *keypad = nullptr;

static void fillPinArrays() {
  byte rows[KEYPAD_ROWS];
  byte cols[KEYPAD_COLS];

  for (int i = 0; i < KEYPAD_ROWS; i++)
    rows[i] = KEYPAD_ROW_PINS[i];
  for (int i = 0; i < KEYPAD_COLS; i++)
    cols[i] = KEYPAD_COL_PINS[i];

#if KEYPAD_ROWS_REVERSED
  for (int i = 0; i < KEYPAD_ROWS; i++)
    rows[i] = KEYPAD_ROW_PINS[KEYPAD_ROWS - 1 - i];
#endif
#if KEYPAD_COLS_REVERSED
  for (int i = 0; i < KEYPAD_COLS; i++)
    cols[i] = KEYPAD_COL_PINS[KEYPAD_COLS - 1 - i];
#endif

#if KEYPAD_SWAP_ROWS_COLS
  for (int i = 0; i < KEYPAD_ROWS; i++)
    rowPins[i] = cols[i];
  for (int i = 0; i < KEYPAD_COLS; i++)
    colPins[i] = rows[i];
#else
  for (int i = 0; i < KEYPAD_ROWS; i++)
    rowPins[i] = rows[i];
  for (int i = 0; i < KEYPAD_COLS; i++)
    colPins[i] = cols[i];
#endif
}

#if KEYPAD_USE_REMAP
// Hasil kalibrasi Serial: raw library → tombol fisik modul user
static char keypadRemap(char raw) {
  switch (raw) {
    case 'D':
      return '1';
    case 'C':
      return '2';
    case 'B':
      return '3';
    case '#':
      return '4';
    case '9':
      return '5';
    case '5':
      return '9';
    case '6':
      return '6';
    case '0':
      return '7';
    case '7':
      return '0';
    case '2':
      return 'C';
    case '3':
      return 'B';
    case '4':
      return '#';
    case '1':
      return 'D';
    case 'A':
      return 'A';
    case '*':
      return '*';
    default:
      return raw;
  }
}
#endif

void uiKeypadInit() {
  fillPinArrays();
  static Keypad kpd = Keypad(makeKeymap(keymap), rowPins, colPins, KEYPAD_ROWS, KEYPAD_COLS);
  kpd.setDebounceTime(25);
  kpd.setHoldTime(25);
  keypad = &kpd;
}

char uiKeypadPoll() {
  if (!keypad)
    return NO_KEY;
  char k = keypad->getKey();
  if (k == NO_KEY)
    return NO_KEY;
#if KEYPAD_USE_REMAP
  return keypadRemap(k);
#else
  return k;
#endif
}

bool uiKeyIsMenu(char k) {
  // A saja — tombol # di banyak modul PCB tidak terbaca stabil
  return k == 'A';
}

bool uiKeyIsUp(char k) {
  // 2 = naik (pola keypad telepon). A juga naik di menu.
  return k == '2' || k == 'A';
}

bool uiKeyIsDown(char k) {
  // 8 = turun; 4 juga turun (bukan naik). 6/B cadangan.
  return k == '8' || k == '4' || k == '6' || k == 'B';
}

bool uiKeyIsOk(char k) {
  // D utama; #/5 cadangan
  return k == 'D' || k == '#' || k == '5';
}

bool uiKeyIsBack(char k) {
  // * = batal/back. C dipakai hapus digit di layar PIN (ditangani terpisah).
  return k == '*';
}

bool uiKeyIsHome(char k) {
  return k == '*';
}
