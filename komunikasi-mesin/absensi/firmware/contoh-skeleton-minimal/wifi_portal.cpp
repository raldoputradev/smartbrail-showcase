#include "wifi_portal.h"

#include <Preferences.h>
#include <WebServer.h>
#include <WiFi.h>

#include "app_config.h"

#if DEBUG_SERIAL
#include <Arduino.h>
#endif

static WebServer server(80);
static Preferences prefs;
static bool apRunning = false;
static bool portalUnlocked = false;
static char apSsidBuf[33] = WIFI_AP_SSID;

bool wifiApActive() { return apRunning; }

const char *wifiApSsid() { return apSsidBuf; }

void wifiLoadCredentials(char *ssid, size_t ssidLen, char *pass, size_t passLen) {
  prefs.begin("wifi", true);
  String s = prefs.getString("ssid", WIFI_SSID);
  String p = prefs.getString("pass", WIFI_PASSWORD);
  prefs.end();
  strncpy(ssid, s.c_str(), ssidLen - 1);
  ssid[ssidLen - 1] = '\0';
  strncpy(pass, p.c_str(), passLen - 1);
  pass[passLen - 1] = '\0';
}

bool wifiSaveCredentials(const char *ssid, const char *pass) {
  if (!ssid || !ssid[0])
    return false;
  prefs.begin("wifi", false);
  prefs.putString("ssid", ssid);
  prefs.putString("pass", pass ? pass : "");
  prefs.end();
  return true;
}

static String htmlEscape(const String &in) {
  String o;
  o.reserve(in.length() + 8);
  for (size_t i = 0; i < in.length(); i++) {
    char c = in[i];
    if (c == '<')
      o += F("&lt;");
    else if (c == '>')
      o += F("&gt;");
    else if (c == '&')
      o += F("&amp;");
    else if (c == '"')
      o += F("&quot;");
    else
      o += c;
  }
  return o;
}

static String pageShell(const String &title, const String &body) {
  String h;
  h.reserve(body.length() + 280);
  h += F("<!DOCTYPE html><html><head><meta charset=utf-8>"
         "<meta name=viewport content=\"width=device-width,initial-scale=1\">"
         "<title>");
  h += title;
  h += F("</title><style>"
         "body{font-family:sans-serif;max-width:420px;margin:24px auto;padding:0 12px}"
         "input,button{width:100%;padding:10px;margin:6px 0;font-size:16px;box-sizing:border-box}"
         "button{background:#0b3d5c;color:#fff;border:0;border-radius:6px}"
         ".box{border:1px solid #ccc;border-radius:8px;padding:16px}"
         "h1{font-size:1.2rem}p{color:#444;font-size:.95rem}"
         "</style></head><body>");
  h += body;
  h += F("</body></html>");
  return h;
}

static void handleRoot() {
  if (!portalUnlocked) {
    String body = F("<div class=box><h1>SIMALAS Setup</h1>"
                    "<p>Masukkan PIN mesin untuk mengatur WiFi lab.</p>"
                    "<form method=POST action=/unlock>"
                    "<input name=pin type=password inputmode=numeric maxlength=8 "
                    "placeholder=\"PIN mesin\" required>"
                    "<button type=submit>Lanjut</button></form></div>");
    server.send(200, "text/html", pageShell("PIN", body));
    return;
  }

  char curSsid[64], curPass[64];
  wifiLoadCredentials(curSsid, sizeof(curSsid), curPass, sizeof(curPass));

  String body = F("<div class=box><h1>WiFi Lab</h1>"
                  "<p>Isi jaringan yang dipakai mesin absensi (bukan SoftAP).</p>"
                  "<form method=POST action=/save>"
                  "<label>SSID</label>"
                  "<input name=ssid required value=\"");
  body += htmlEscape(String(curSsid));
  body += F("\"><label>Password</label>"
            "<input name=pass type=password value=\"");
  body += htmlEscape(String(curPass));
  body += F("\"><button type=submit>Simpan &amp; Hubungkan</button></form>"
            "<p style=margin-top:12px><a href=/lock>Kunci PIN</a></p></div>");
  server.send(200, "text/html", pageShell("WiFi", body));
}

static void handleUnlock() {
  if (!server.hasArg("pin")) {
    server.sendHeader("Location", "/");
    server.send(302);
    return;
  }
  String pin = server.arg("pin");
  if (pin.equals(WIFI_SETUP_PIN)) {
    portalUnlocked = true;
#if DEBUG_SERIAL
    Serial.println(F("[AP] PIN OK — portal terbuka"));
#endif
    server.sendHeader("Location", "/");
    server.send(302);
    return;
  }
  String body = F("<div class=box><h1>PIN salah</h1>"
                  "<p>Silakan ulangi.</p><p><a href=/>Kembali</a></p></div>");
  server.send(403, "text/html", pageShell("PIN salah", body));
}

static void handleLock() {
  portalUnlocked = false;
  server.sendHeader("Location", "/");
  server.send(302);
}

static void handleSave() {
  if (!portalUnlocked) {
    server.sendHeader("Location", "/");
    server.send(302);
    return;
  }
  if (!server.hasArg("ssid")) {
    server.send(400, "text/plain", "SSID wajib");
    return;
  }
  String ssid = server.arg("ssid");
  String pass = server.hasArg("pass") ? server.arg("pass") : "";
  ssid.trim();
  if (ssid.length() == 0 || ssid.length() > 32) {
    server.send(400, "text/plain", "SSID tidak valid");
    return;
  }
  wifiSaveCredentials(ssid.c_str(), pass.c_str());

  String body = F("<div class=box><h1>Tersimpan</h1>"
                  "<p>Mesin akan mencoba hubungkan ke WiFi lab. "
                  "Tunggu 5–10 detik, lalu cek OLED (Online).</p>"
                  "<p>Anda boleh putus dari SoftAP.</p></div>");
  server.send(200, "text/html", pageShell("OK", body));

#if DEBUG_SERIAL
  Serial.printf("[AP] kredensial baru SSID=%s — reconnect...\n", ssid.c_str());
#endif

  delay(800);
  wifiApStop();
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid.c_str(), pass.c_str());
}

void wifiApStart() {
  if (apRunning)
    return;

  portalUnlocked = false;
  strncpy(apSsidBuf, WIFI_AP_SSID, sizeof(apSsidBuf) - 1);
  apSsidBuf[sizeof(apSsidBuf) - 1] = '\0';

  WiFi.mode(WIFI_AP);
  // Password SoftAP minimal 8 karakter (WPA2).
  bool ok = WiFi.softAP(apSsidBuf, WIFI_AP_PASS);
  if (!ok) {
#if DEBUG_SERIAL
    Serial.println(F("[AP] softAP gagal"));
#endif
    return;
  }

  IPAddress ip = WiFi.softAPIP();
  server.on("/", HTTP_GET, handleRoot);
  server.on("/unlock", HTTP_POST, handleUnlock);
  server.on("/lock", HTTP_GET, handleLock);
  server.on("/save", HTTP_POST, handleSave);
  server.onNotFound([]() {
    server.sendHeader("Location", "/");
    server.send(302);
  });
  server.begin();
  apRunning = true;

#if DEBUG_SERIAL
  Serial.printf("[AP] SSID=%s PASS=%s IP=%s PIN=%s\n", apSsidBuf, WIFI_AP_PASS,
                ip.toString().c_str(), WIFI_SETUP_PIN);
#endif
}

void wifiApStop() {
  if (!apRunning)
    return;
  server.stop();
  WiFi.softAPdisconnect(true);
  apRunning = false;
  portalUnlocked = false;
#if DEBUG_SERIAL
  Serial.println(F("[AP] dihentikan"));
#endif
}

void wifiPortalTick() {
  if (apRunning)
    server.handleClient();
}
