import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:permission_handler/permission_handler.dart';

import 'firebase_options.dart';

const _androidChannel = AndroidNotificationChannel(
  'simalas_default',
  'SIMALAS Notifikasi',
  description: 'Pengumuman dan pembaruan aplikasi SIMALAS',
  importance: Importance.high,
);

Future<void> _initLocalNotifications(
  FlutterLocalNotificationsPlugin plugin,
) async {
  const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
  await plugin.initialize(const InitializationSettings(android: androidInit));
  await plugin
      .resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin
      >()
      ?.createNotificationChannel(_androidChannel);
}

Future<void> _showPushNotification(
  FlutterLocalNotificationsPlugin plugin,
  RemoteMessage message,
) async {
  final notification = message.notification;
  final title =
      notification?.title ?? message.data['title']?.toString() ?? 'SIMALAS';
  final body = notification?.body ?? message.data['body']?.toString() ?? '';

  await plugin.show(
    message.hashCode,
    title,
    body,
    const NotificationDetails(
      android: AndroidNotificationDetails(
        'simalas_default',
        'SIMALAS Notifikasi',
        channelDescription: 'Pengumuman dan pembaruan aplikasi SIMALAS',
        importance: Importance.high,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
      ),
    ),
  );
}

/// Handler background FCM — wajib top-level.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  // Jika payload sudah bertipe "notification", Android biasanya sudah tampilkan
  // notifikasi sistem sendiri saat app background/terminated.
  // Hindari menampilkan ulang via local notification agar tidak dobel.
  if (message.notification != null) {
    return;
  }
  final plugin = FlutterLocalNotificationsPlugin();
  await _initLocalNotifications(plugin);
  await _showPushNotification(plugin, message);
}

/// Layanan push notifikasi FCM.
class PushService {
  PushService._();

  static final PushService instance = PushService._();

  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();

  bool _initialized = false;
  bool _available = false;
  bool _tokenRefreshHooked = false;
  Future<bool> Function(String token)? _onTokenReady;

  bool get isAvailable => _available;

  Future<bool> initialize() async {
    if (_initialized) return _available;
    _initialized = true;

    // Push FCM hanya untuk Android (uji Windows desktop tidak butuh Firebase native).
    if (!Platform.isAndroid) {
      _available = false;
      return _available;
    }

    try {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
      await _initLocalNotifications(_local);

      await FirebaseMessaging.instance
          .setForegroundNotificationPresentationOptions(
            alert: true,
            badge: true,
            sound: true,
          );

      FirebaseMessaging.onMessage.listen((message) {
        // Foreground Android tidak otomatis menampilkan notifikasi sistem.
        // Tampilkan local notification sekali agar mirip behavior WhatsApp.
        if (Platform.isAndroid) {
          _showPushNotification(_local, message);
        }
      });
      FirebaseMessaging.onMessageOpenedApp.listen((_) {});

      _available = true;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('PushService init gagal: $e');
      }
      _available = false;
    }

    return _available;
  }

  Future<bool> _ensureNotificationPermission() async {
    if (!Platform.isAndroid) {
      final settings = await FirebaseMessaging.instance.requestPermission();
      return settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional;
    }

    final status = await Permission.notification.status;
    if (status.isGranted) return true;
    return (await Permission.notification.request()).isGranted;
  }

  /// Ambil token FCM setelah izin notifikasi. Null jika gagal.
  Future<String?> obtainFcmToken() async {
    if (!_initialized) {
      await initialize();
    }
    if (!_available) return null;

    for (var attempt = 0; attempt < 4; attempt++) {
      if (!await _ensureNotificationPermission()) {
        await Future<void>.delayed(Duration(seconds: 1 + attempt));
        continue;
      }

      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        return token;
      }
      await Future<void>.delayed(Duration(seconds: 1 + attempt));
    }
    return null;
  }

  /// Daftarkan token ke server lewat callback (pakai ApiClient yang sama).
  Future<bool> registerWithServer(
    Future<bool> Function(String token) sendToServer,
  ) async {
    _onTokenReady = sendToServer;

    final token = await obtainFcmToken();
    if (token == null) return false;

    final ok = await sendToServer(token);
    if (ok) {
      _hookTokenRefresh();
    }
    return ok;
  }

  void _hookTokenRefresh() {
    if (_tokenRefreshHooked || _onTokenReady == null) return;
    _tokenRefreshHooked = true;
    FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
      await _onTokenReady?.call(newToken);
    });
  }
}
