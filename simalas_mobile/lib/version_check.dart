import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_config.dart';

class MobileUpdateInfo {
  const MobileUpdateInfo({
    required this.versionName,
    required this.versionCode,
    required this.minVersionCode,
    required this.downloadUrl,
    required this.changelog,
    required this.forceUpdate,
  });

  final String versionName;
  final int versionCode;
  final int minVersionCode;
  final String downloadUrl;
  final String changelog;
  final bool forceUpdate;

  factory MobileUpdateInfo.fromJson(Map<String, dynamic> json) {
    return MobileUpdateInfo(
      versionName: json['version_name']?.toString() ?? '1.0.0',
      versionCode: int.tryParse(json['version_code']?.toString() ?? '') ?? 1,
      minVersionCode: int.tryParse(json['min_version_code']?.toString() ?? '') ?? 1,
      downloadUrl: json['download_url']?.toString() ?? '',
      changelog: json['changelog']?.toString() ?? '',
      forceUpdate: json['force_update'] == true,
    );
  }
}

Future<MobileUpdateInfo?> fetchUpdateInfo(String apiBaseUrl) async {
  try {
    final root = apiBaseUrl.endsWith('/api')
        ? apiBaseUrl
        : '$apiBaseUrl/api';
    final uri = Uri.parse('$root/mobile/config');
    final response = await http.get(uri).timeout(const Duration(seconds: 8));
    if (response.statusCode != 200) return null;
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (decoded['success'] != true) return null;
    final data = decoded['data'] as Map<String, dynamic>?;
    final update = data?['update'] as Map<String, dynamic>?;
    if (update == null) return null;
    return MobileUpdateInfo.fromJson(update);
  } catch (_) {
    return null;
  }
}

Future<bool> shouldPromptUpdate(MobileUpdateInfo info) async {
  final pkg = await PackageInfo.fromPlatform();
  final currentCode = int.tryParse(pkg.buildNumber) ?? 0;
  return info.versionCode > currentCode;
}

Future<void> showUpdateDialog(
  BuildContext context, {
  required MobileUpdateInfo info,
  bool force = false,
}) async {
  if (!context.mounted) return;
  await showDialog<void>(
    context: context,
    barrierDismissible: !force,
    builder: (ctx) {
      return PopScope(
        canPop: !force,
        child: AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Row(
            children: [
              Icon(Icons.system_update_alt_rounded, color: Theme.of(ctx).colorScheme.primary),
              const SizedBox(width: 10),
              const Expanded(child: Text('Update Tersedia')),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Versi ${info.versionName} sudah tersedia.',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                if (info.changelog.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    info.changelog,
                    style: TextStyle(
                      color: Theme.of(ctx).colorScheme.onSurface.withValues(alpha: 0.75),
                      height: 1.45,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                const Text(
                  'Unduh APK terbaru dari website SIMALAS lalu instal manual.',
                  style: TextStyle(fontSize: 13),
                ),
              ],
            ),
          ),
          actions: [
            if (!force)
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Nanti'),
              ),
            FilledButton.icon(
              onPressed: () async {
                final url = info.downloadUrl.isNotEmpty
                    ? info.downloadUrl
                    : '${publicOriginFromApiBase(kProductionApiBaseUrl)}/download';
                final uri = Uri.parse(url);
                var launched = false;
                try {
                  launched = await launchUrl(
                    uri,
                    mode: LaunchMode.externalApplication,
                  );
                  if (!launched) {
                    launched = await launchUrl(
                      uri,
                      mode: LaunchMode.platformDefault,
                    );
                  }
                } catch (_) {
                  launched = false;
                }
                if (!launched && ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Browser tidak dapat dibuka. Kunjungi iotsimalas.my.id/download.',
                      ),
                    ),
                  );
                }
                if (launched && !force && ctx.mounted) Navigator.pop(ctx);
              },
              icon: const Icon(Icons.download_rounded, size: 18),
              label: const Text('Unduh Sekarang'),
            ),
          ],
        ),
      );
    },
  );
}

Future<void> checkAndPromptUpdate(BuildContext context, String apiBaseUrl) async {
  final info = await fetchUpdateInfo(apiBaseUrl);
  if (info == null || !context.mounted) return;
  final needsUpdate = await shouldPromptUpdate(info);
  if (!needsUpdate || !context.mounted) return;
  await showUpdateDialog(
    context,
    info: info,
    force: info.forceUpdate,
  );
}
