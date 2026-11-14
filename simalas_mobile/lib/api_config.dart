import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

/// URL API produksi (domain resmi). Override saat build: --dart-define=API_BASE_URL=...
const String kProductionApiBaseUrl = 'https://iotsimalas.my.id/api';

/// Path API di XAMPP lokal (folder public Laravel).
const String kLocalApiPath = '/Simalas/public/api';

/// Build APK lokal (boleh tembak IP):
///   flutter build apk --release
///   flutter build apk --release --dart-define=LOCAL_API_HOST=192.168.1.7
///
/// Build APK cloud/VPS (URL dikunci, setting IP disembunyikan):
///   flutter build apk --release --dart-define=LOCK_API_TO_PRODUCTION=true
const bool kLockApiToProduction = bool.fromEnvironment(
  'LOCK_API_TO_PRODUCTION',
  defaultValue: false,
);

/// Host/IP LAN PC untuk default APK lokal (HP fisik). Contoh: 192.168.1.7
const String kLocalApiHost = String.fromEnvironment(
  'LOCAL_API_HOST',
  defaultValue: '192.168.1.7',
);

/// True = tampilkan pengaturan server / tembak IP (tahap lokal).
bool get allowCustomApiBaseUrl => !kLockApiToProduction;

/// URL dasar API — produksi jika dikunci; selain itu XAMPP / LAN lokal.
String defaultApiBaseUrlForPlatform() {
  const fromEnv = String.fromEnvironment('API_BASE_URL');
  if (fromEnv.isNotEmpty) {
    return _normalize(fromEnv);
  }

  if (kLockApiToProduction) {
    return kProductionApiBaseUrl;
  }

  // Android emulator: 10.0.2.2 = localhost PC host
  if (Platform.isAndroid) {
    if (kReleaseMode && kLocalApiHost.isNotEmpty) {
      // APK release lokal untuk HP fisik → tembak IP LAN laptop
      return apiBaseUrlFromHost(kLocalApiHost);
    }
    return 'http://10.0.2.2$kLocalApiPath';
  }

  // Windows / macOS / Linux / iOS simulator + Flutter desktop
  return 'http://localhost$kLocalApiPath';
}

/// Bangun URL API dari IP/hostname atau URL lengkap.
/// Contoh: `192.168.1.7` → `http://192.168.1.7/Simalas/public/api`
String apiBaseUrlFromHost(String hostOrUrl) {
  var raw = hostOrUrl.trim();
  if (raw.isEmpty) {
    return defaultApiBaseUrlForPlatform();
  }

  if (raw.startsWith('http://') || raw.startsWith('https://')) {
    return _normalize(raw);
  }

  // Hapus path jika user tempel "192.168.1.7/Simalas/..."
  raw = raw.replaceFirst(RegExp(r'^/+'), '');
  if (raw.toLowerCase().contains('/simalas/') ||
      raw.toLowerCase().endsWith('/api')) {
    return _normalize('http://$raw');
  }

  final host = raw.split('/').first.trim();
  return _normalize('http://$host$kLocalApiPath');
}

/// Ambil host/IP dari URL API (untuk field tembak IP).
String hostFromApiBaseUrl(String baseUrl) {
  try {
    final uri = Uri.parse(_normalize(baseUrl));
    if (uri.host.isNotEmpty) return uri.host;
  } catch (_) {}
  return kLocalApiHost;
}

/// URL API aktif (diisi SessionStore) — untuk rewrite media localhost → IP LAN.
String? activeApiBaseUrl;

/// Ubah URL media dari server (sering localhost) supaya HP bisa load via IP API.
String? resolveMediaUrl(String? url, [String? apiBase]) {
  final raw = url?.trim();
  if (raw == null || raw.isEmpty) return null;

  final base = (apiBase ?? activeApiBaseUrl)?.trim();
  if (base == null || base.isEmpty) return raw;

  final origin = publicOriginFromApiBase(base);
  Uri apiOrigin;
  try {
    apiOrigin = Uri.parse(origin);
  } catch (_) {
    return raw;
  }

  try {
    if (!raw.startsWith('http://') && !raw.startsWith('https://')) {
      final path = raw.startsWith('/') ? raw.substring(1) : raw;
      return '$origin/$path';
    }

    final uri = Uri.parse(raw);
    const localHosts = {'localhost', '127.0.0.1', '10.0.2.2'};
    if (!localHosts.contains(uri.host)) {
      return raw;
    }

    return uri
        .replace(
          scheme: apiOrigin.scheme,
          host: apiOrigin.host,
          port: apiOrigin.hasPort ? apiOrigin.port : null,
        )
        .toString();
  } catch (_) {
    return raw;
  }
}

/// Origin publik (tanpa /api) dari URL API tersimpan.
String publicOriginFromApiBase(String baseUrl) {
  var origin = _normalize(baseUrl);
  if (origin.endsWith('/api')) {
    origin = origin.substring(0, origin.length - 4);
  }
  return origin;
}

/// Kandidat URL untuk auto-discovery saat startup / gagal koneksi.
List<String> apiBaseUrlCandidates() {
  final primary = defaultApiBaseUrlForPlatform();

  if (kLockApiToProduction) {
    final extras = <String>[
      kProductionApiBaseUrl,
      'https://www.iotsimalas.my.id/api',
    ];
    return [primary, ...extras.where((u) => u != primary)];
  }

  // Mode lokal: prioritaskan XAMPP/LAN — jangan loncat ke cloud.
  final extras = <String>[
    if (kLocalApiHost.isNotEmpty) apiBaseUrlFromHost(kLocalApiHost),
    'http://10.0.2.2$kLocalApiPath',
    'http://localhost$kLocalApiPath',
    'http://127.0.0.1$kLocalApiPath',
    'http://127.0.0.1:8000/api',
    'http://localhost:8000/api',
    'http://10.0.2.2:8000/api',
  ];
  final seen = <String>{};
  final out = <String>[];
  for (final u in [primary, ...extras]) {
    if (seen.add(u)) out.add(u);
  }
  return out;
}

String _normalize(String value) {
  var url = value.trim();
  if (url.endsWith('/')) {
    url = url.substring(0, url.length - 1);
  }
  return url;
}

/// Cek apakah server API merespons (GET /mobile/config).
Future<bool> probeApiBaseUrl(
  String baseUrl, {
  Duration timeout = const Duration(seconds: 4),
}) async {
  final root = _normalize(baseUrl);
  try {
    final uri = Uri.parse('$root/mobile/config');
    final response = await http.get(uri).timeout(timeout);
    if (response.statusCode != 200) {
      return false;
    }
    return response.body.contains('"success"');
  } catch (_) {
    return false;
  }
}

/// Pilih URL pertama yang hidup; null jika semua gagal.
Future<String?> resolveReachableApiBaseUrl() async {
  for (final candidate in apiBaseUrlCandidates()) {
    if (await probeApiBaseUrl(candidate)) {
      return candidate;
    }
  }
  return null;
}

bool isLegacyArtisanOnlyUrl(String? url) {
  if (url == null || url.isEmpty) {
    return false;
  }
  final u = url.toLowerCase();
  return (u.contains(':8000') && u.contains('/api')) ||
      u == 'http://127.0.0.1:8000/api' ||
      u == 'http://localhost:8000/api';
}

bool isLegacyProductionApiUrl(String? url) {
  if (url == null || url.trim().isEmpty) {
    return false;
  }
  final u = url.toLowerCase();
  return u.contains('103.63.24.114') ||
      u.contains('nip.io') ||
      u.startsWith('http://iotsimalas.my.id');
}

bool _isLocalDevApiUrl(String url) {
  final u = url.toLowerCase();
  return u.contains('localhost') ||
      u.contains('127.0.0.1') ||
      u.contains('10.0.2.2') ||
      u.contains('192.168.') ||
      RegExp(r'://10\.\d+\.\d+\.\d+').hasMatch(u) ||
      u.contains('/simalas/public/');
}

/// URL tersimpan yang tidak cocok (IP lama, artisan :8000, lokal di build VPS).
bool shouldReplaceStoredApiUrl(String? url) {
  if (url == null || url.trim().isEmpty) {
    return false;
  }
  if (isLegacyArtisanOnlyUrl(url)) {
    return true;
  }
  if (isLegacyProductionApiUrl(url)) {
    return true;
  }
  // Hanya hapus URL lokal jika APK dikunci ke produksi (cloud/VPS).
  if (kLockApiToProduction && _isLocalDevApiUrl(url)) {
    return true;
  }
  return false;
}
