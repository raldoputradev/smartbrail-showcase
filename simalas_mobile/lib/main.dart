import 'dart:async';
import 'dart:convert';
import 'dart:ui';

import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';
import 'package:lottie/lottie.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:showcaseview/showcaseview.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_config.dart';
import 'email_validation.dart';
import 'push_service.dart';
import 'theme_store.dart';
import 'version_check.dart';
import 'widgets/mobile_recaptcha.dart';
import 'widgets/otp_pin_input.dart';

part 'widgets/blur_loading.dart';
part 'widgets/auth_landing_widgets.dart';
part 'widgets/mhs_home_widgets.dart';
part 'widgets/mhs_student_access.dart';
part 'screens/attendance_page.dart';
part 'screens/mhs_team_page.dart';
part 'screens/mhs_history_page.dart';
part 'screens/onboarding_page.dart';

/// Nilai placeholder dropdown form — tidak boleh dikirim ke server.
const kFormPilih = '__pilih__';

bool isFormPilih(String? value) =>
    value == null || value.isEmpty || value == kFormPilih;

/// Daftar semester sesuai ganjil (ganjil) atau genap (genap), hingga [maxSemester].
List<String> semestersForType(String? type, {int maxSemester = 14}) {
  if (type == 'ganjil') {
    return List.generate((maxSemester + 1) ~/ 2, (i) => '${i * 2 + 1}');
  }
  if (type == 'genap') {
    return List.generate(maxSemester ~/ 2, (i) => '${(i + 1) * 2}');
  }
  return const [];
}

bool semesterMatchesType(int semester, String type) {
  if (type == 'ganjil') return semester.isOdd;
  if (type == 'genap') return semester.isEven;
  return false;
}

/// URL default per platform (XAMPP: …/Simalas/public/api). Override: --dart-define=API_BASE_URL=...
String get defaultApiBaseUrl => defaultApiBaseUrlForPlatform();

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id_ID');
  Intl.defaultLocale = 'id_ID';
  await PushService.instance.initialize();
  runApp(const SimalasApp());
}

class SimalasApp extends StatefulWidget {
  const SimalasApp({super.key});

  @override
  State<SimalasApp> createState() => _SimalasAppState();
}

class _SimalasAppState extends State<SimalasApp> {
  final SessionStore _session = SessionStore();
  final ThemeStore _theme = ThemeStore();

  @override
  void initState() {
    super.initState();
    _theme.load().then((_) {
      ThemeStore.light = _theme.isLight;
      if (mounted) setState(() {});
    });
    _theme.addListener(_onThemeChanged);
  }

  @override
  void dispose() {
    _theme.removeListener(_onThemeChanged);
    super.dispose();
  }

  void _onThemeChanged() {
    ThemeStore.light = _theme.isLight;
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Smart Lab',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: _theme.themeMode,
      home: AppGate(session: _session, themeStore: _theme),
    );
  }
}

class AppGate extends StatefulWidget {
  const AppGate({super.key, required this.session, required this.themeStore});

  final SessionStore session;
  final ThemeStore themeStore;

  @override
  State<AppGate> createState() => _AppGateState();
}

class _AppGateState extends State<AppGate> {
  bool _sessionReady = false;
  bool _splashDone = false;
  bool _showAuth = false;
  int _initialAuthTab = 0;
  bool _pendingLoginWelcome = false;
  bool _handlingSessionExpired = false;
  String? _sessionExpiredMessage;
  late ApiClient _api;
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    _restore();
  }

  ApiClient _createApi({String? token}) {
    return ApiClient(
      widget.session.baseUrl,
      token: token ?? widget.session.token,
      onSessionExpired: _handleSessionExpired,
    );
  }

  Future<void> _restore() async {
    await widget.session.load();
    final expiredMsg = widget.session.consumeExpiredMessage();
    _api = _createApi();
    if (!mounted) return;
    setState(() {
      _user = widget.session.user;
      if (expiredMsg != null) {
        _sessionExpiredMessage = expiredMsg;
        _showAuth = true;
        _initialAuthTab = 0;
      }
      _sessionReady = true;
    });
    if (mounted) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        checkAndPromptUpdate(context, widget.session.baseUrl);
      });
    }
  }

  void _onSplashFinished() {
    if (!mounted || _splashDone) return;
    setState(() {
      _splashDone = true;
      // Setelah splash: landing (intro di area hero) atau home.
      if (_user == null || widget.session.token == null) {
        _showAuth = false;
        _initialAuthTab = 0;
      }
    });
  }

  bool get _booting => !_sessionReady || !_splashDone;

  Future<void> _onAuthenticated({
    required String token,
    required String baseUrl,
    required Map<String, dynamic> user,
  }) async {
    await widget.session.save(baseUrl: baseUrl, token: token, user: user);
    final api = ApiClient(_normalizeApiBaseUrl(baseUrl), token: token);
    await PushService.instance.registerWithServer((fcmToken) async {
      try {
        await api.post(
          '/mobile/me/push-token',
          body: {'token': fcmToken, 'platform': 'android'},
          auth: true,
        );
        return true;
      } catch (_) {
        return false;
      }
    });
    setState(() {
      _sessionExpiredMessage = null;
      _api = _createApi(token: token);
      _user = user;
      _pendingLoginWelcome = true;
    });
  }

  Future<void> _handleSessionExpired() async {
    if (_handlingSessionExpired || _user == null) return;
    _handlingSessionExpired = true;
    try {
      await _logoutSessionExpired();
    } finally {
      _handlingSessionExpired = false;
    }
  }

  Future<void> _logoutSessionExpired() async {
    try {
      await _api.post('/mobile/logout', auth: true);
    } catch (_) {
      // Local logout should still work when the server is unreachable.
    }
    await widget.session.clear();
    if (!mounted) return;
    setState(() {
      _api = _createApi();
      _user = null;
      _showAuth = true;
      _initialAuthTab = 0;
      _sessionExpiredMessage = SessionStore.sessionExpiredMessage;
    });
  }

  Future<void> _logout() async {
    try {
      await _api.post('/mobile/logout', auth: true);
    } catch (_) {
      // Local logout should still work when the server is unreachable.
    }
    await widget.session.clear();
    setState(() {
      _api = _createApi();
      _user = null;
      _showAuth = false;
      _sessionExpiredMessage = null;
    });
  }

  void _updateUser(Map<String, dynamic> user) {
    widget.session.updateUser(user);
    setState(() => _user = user);
  }

  @override
  Widget build(BuildContext context) {
    // Splash brand → landing (hero intro di atas Selamat datang) / home.
    if (_booting) {
      return SplashScreen(onFinished: _onSplashFinished);
    }

    if (_user == null || widget.session.token == null) {
      if (!_showAuth) {
        return MobileLandingPage(
          apiBaseUrl: widget.session.baseUrl,
          onLogin: () => setState(() {
            _initialAuthTab = 0;
            _showAuth = true;
          }),
          onRegister: () => setState(() {
            _initialAuthTab = 1;
            _showAuth = true;
          }),
        );
      }

      return AuthScreen(
        initialBaseUrl: widget.session.baseUrl,
        initialTab: _initialAuthTab,
        sessionExpiredMessage: _sessionExpiredMessage,
        onBack: () => setState(() {
          _showAuth = false;
          _sessionExpiredMessage = null;
        }),
        onAuthenticated: _onAuthenticated,
      );
    }

    return HomeShell(
      api: _api,
      user: _user!,
      session: widget.session,
      themeStore: widget.themeStore,
      onLogout: _logout,
      onSessionExpired: _logoutSessionExpired,
      onUserChanged: _updateUser,
      showLoginWelcome: _pendingLoginWelcome,
      onLoginWelcomeShown: () {
        if (_pendingLoginWelcome) {
          setState(() => _pendingLoginWelcome = false);
        }
      },
    );
  }
}

String _normalizeApiBaseUrl(String value) {
  var url = value.trim();
  if (url.endsWith('/')) url = url.substring(0, url.length - 1);
  return url;
}

String _friendlyApiMessage(String? message, int statusCode, String rawBody) {
  final combined = '${message ?? ''} $rawBody';
  if (combined.contains('SQLSTATE[HY000]') ||
      combined.contains('actively refused') ||
      combined.contains('Database MySQL tidak aktif')) {
    if (kReleaseMode) {
      return 'Server SIMALAS sedang tidak tersedia. Coba lagi beberapa saat.';
    }
    return 'Database MySQL tidak aktif. Buka XAMPP → Start MySQL (hijau), pastikan Apache juga jalan, lalu buka ulang aplikasi.';
  }
  if (kReleaseMode &&
      (combined.contains('ClientException') ||
          combined.contains('SocketException') ||
          combined.contains('Failed host lookup'))) {
    return 'Tidak dapat terhubung ke server SIMALAS. Periksa koneksi internet atau coba lagi nanti.';
  }
  if (message != null && message.trim().isNotEmpty) {
    return message;
  }
  return kReleaseMode
      ? 'Server SIMALAS tidak merespons. Mungkin sedang maintenance.'
      : 'Terjadi kesalahan koneksi (HTTP $statusCode).';
}

class ApiClient {
  ApiClient(String baseUrl, {this.token, this.onSessionExpired})
    : baseUrl = _normalizeApiBaseUrl(baseUrl);

  final String baseUrl;
  final String? token;
  final VoidCallback? onSessionExpired;

  bool _isSessionExpiredResponse(
    int statusCode,
    Map<String, dynamic> decoded,
    bool auth,
  ) {
    if (!auth || statusCode != 401) return false;
    if (decoded['code'] == 'SESSION_IDLE_EXPIRED') return true;
    final msg = decoded['message']?.toString().toLowerCase() ?? '';
    return msg.contains('sesi berakhir');
  }

  void _notifySessionExpiredIfNeeded(
    int statusCode,
    Map<String, dynamic> decoded,
    bool auth,
  ) {
    if (_isSessionExpiredResponse(statusCode, decoded, auth)) {
      onSessionExpired?.call();
    }
  }

  Future<Map<String, dynamic>> get(String path, {bool auth = false}) {
    return _send('GET', path, auth: auth);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool auth = false,
    Duration? timeout,
  }) {
    return _send('POST', path, body: body, auth: auth, timeout: timeout);
  }

  Future<Map<String, dynamic>> multipart(
    String path, {
    required String fieldName,
    required String filePath,
    bool auth = false,
  }) async {
    final request = http.MultipartRequest('POST', Uri.parse('$baseUrl$path'));
    request.headers.addAll({
      'Accept': 'application/json',
      if (auth && token != null) 'Authorization': 'Bearer $token',
    });
    request.files.add(await http.MultipartFile.fromPath(fieldName, filePath));

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 400 || decoded['success'] == false) {
      _notifySessionExpiredIfNeeded(response.statusCode, decoded, auth);
      throw ApiException(
        _friendlyApiMessage(
          decoded['message']?.toString(),
          response.statusCode,
          response.body,
        ),
        statusCode: response.statusCode,
        code: decoded['code']?.toString(),
        errors: decoded['errors'],
      );
    }

    return decoded;
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool auth = false,
    Duration? timeout,
  }) async {
    final uri = Uri.parse('$baseUrl$path');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (auth && token != null) 'Authorization': 'Bearer $token',
    };

    http.Response response;
    try {
      final requestTimeout = timeout ?? const Duration(seconds: 30);
      response = method == 'GET'
          ? await http.get(uri, headers: headers).timeout(requestTimeout)
          : await http
                .post(uri, headers: headers, body: jsonEncode(body ?? {}))
                .timeout(requestTimeout);
    } on http.ClientException catch (e) {
      throw ApiException(
        kReleaseMode
            ? 'Tidak dapat terhubung ke server SIMALAS. Periksa internet atau coba lagi nanti.'
            : 'Tidak bisa terhubung ke $baseUrl. Pastikan Apache/XAMPP aktif atau jalankan: php artisan serve --host=0.0.0.0. Disarankan XAMPP: ${defaultApiBaseUrlForPlatform()}. ($e)',
      );
    } on TimeoutException {
      throw ApiException(
        kReleaseMode
            ? 'Server SIMALAS tidak merespons. Mungkin sedang down atau koneksi lambat.'
            : 'Server tidak merespons dalam 30 detik. Pastikan Apache & MySQL XAMPP sudah Start, URL API benar, lalu tarik layar untuk muat ulang.',
      );
    }

    Map<String, dynamic> decoded;
    if (response.body.isEmpty) {
      decoded = <String, dynamic>{};
    } else {
      try {
        final parsed = jsonDecode(response.body);
        if (parsed is! Map<String, dynamic>) {
          throw ApiException(
            'Respons server tidak valid (HTTP ${response.statusCode}).',
            statusCode: response.statusCode,
          );
        }
        decoded = parsed;
      } on FormatException {
        final friendly = _friendlyApiMessage(
          null,
          response.statusCode,
          response.body,
        );
        if (friendly.contains('MySQL tidak aktif')) {
          throw ApiException(friendly, statusCode: response.statusCode);
        }
        final preview = response.body.length > 120
            ? '${response.body.substring(0, 120)}…'
            : response.body;
        throw ApiException(
          'Server mengembalikan HTML/bukan JSON (HTTP ${response.statusCode}). Periksa URL API. $preview',
          statusCode: response.statusCode,
        );
      }
    }

    if (response.statusCode >= 400 || decoded['success'] == false) {
      _notifySessionExpiredIfNeeded(response.statusCode, decoded, auth);
      throw ApiException(
        _friendlyApiMessage(
          decoded['message']?.toString(),
          response.statusCode,
          response.body,
        ),
        statusCode: response.statusCode,
        code: decoded['code']?.toString(),
        errors: decoded['errors'],
      );
    }

    return decoded;
  }
}

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.code, this.errors});

  final String message;
  final int? statusCode;
  final String? code;
  final Object? errors;

  @override
  String toString() {
    final details = _validationDetails();
    if (details.isEmpty) return message;
    return '$message\n$details';
  }

  String _validationDetails() {
    final source = errors;
    if (source is! Map) return '';

    final messages = <String>[];
    for (final entry in source.entries) {
      final value = entry.value;
      if (value is List && value.isNotEmpty) {
        messages.add(value.first.toString());
      } else if (value != null) {
        messages.add(value.toString());
      }
    }
    return messages.take(3).join('\n');
  }
}

class SessionStore {
  static const idleTimeout = Duration(minutes: 10);
  static const sessionExpiredMessage =
      'Sesi berakhir karena tidak ada aktivitas. Silakan login ulang.';
  static const _lastActivityKey = 'mobile_last_activity_ms';

  String baseUrl = defaultApiBaseUrl;
  String? token;
  Map<String, dynamic>? user;
  String? _pendingExpiredMessage;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    var saved = prefs.getString('api_base_url');
    final fallback = defaultApiBaseUrl;

    if (saved == null || saved.trim().isEmpty) {
      baseUrl = fallback;
    } else {
      saved = _normalizeApiBaseUrl(saved);
      // URL lama artisan :8000 — langsung ganti (jangan tunggu probe yang lambat/gagal)
      if (shouldReplaceStoredApiUrl(saved)) {
        saved = fallback;
      }
      if (!await probeApiBaseUrl(saved)) {
        final resolved = await resolveReachableApiBaseUrl();
        baseUrl = resolved ?? fallback;
        await prefs.setString('api_base_url', baseUrl);
      } else {
        baseUrl = saved;
      }
    }
    activeApiBaseUrl = baseUrl;

    token = prefs.getString('mobile_token');
    final rawUser = prefs.getString('mobile_user');
    if (rawUser != null) user = jsonDecode(rawUser) as Map<String, dynamic>;

    if (token != null && await isIdleExpired()) {
      _pendingExpiredMessage = sessionExpiredMessage;
      await clear();
    }
  }

  String? consumeExpiredMessage() {
    final msg = _pendingExpiredMessage;
    _pendingExpiredMessage = null;
    return msg;
  }

  Future<void> touchActivity() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_lastActivityKey, DateTime.now().millisecondsSinceEpoch);
  }

  Future<bool> isIdleExpired() async {
    final prefs = await SharedPreferences.getInstance();
    final ms = prefs.getInt(_lastActivityKey);
    if (ms == null) return false;
    return DateTime.now().millisecondsSinceEpoch - ms >
        idleTimeout.inMilliseconds;
  }

  Future<void> save({
    required String baseUrl,
    required String token,
    required Map<String, dynamic> user,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    this.baseUrl = baseUrl;
    this.token = token;
    this.user = user;
    activeApiBaseUrl = baseUrl;
    await prefs.setString('api_base_url', baseUrl);
    await prefs.setString('mobile_token', token);
    await prefs.setString('mobile_user', jsonEncode(user));
    await touchActivity();
  }

  Future<void> updateUser(Map<String, dynamic> user) async {
    final prefs = await SharedPreferences.getInstance();
    this.user = user;
    await prefs.setString('mobile_user', jsonEncode(user));
    await touchActivity();
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    token = null;
    user = null;
    await prefs.remove('mobile_token');
    await prefs.remove('mobile_user');
    await prefs.remove(_lastActivityKey);
  }
}

class AuthScreen extends StatefulWidget {
  const AuthScreen({
    super.key,
    required this.initialBaseUrl,
    this.initialTab = 0,
    this.sessionExpiredMessage,
    this.onBack,
    required this.onAuthenticated,
  });

  final String initialBaseUrl;
  final int initialTab;
  final String? sessionExpiredMessage;
  final VoidCallback? onBack;
  final Future<void> Function({
    required String token,
    required String baseUrl,
    required Map<String, dynamic> user,
  })
  onAuthenticated;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  late final TextEditingController _baseUrlController;
  late final TextEditingController _serverIpController;

  final _loginIdController = TextEditingController();
  final _loginPasswordController = TextEditingController();
  final _nameController = TextEditingController();
  final _nimController = TextEditingController();
  final _emailController = TextEditingController();
  final _registerPasswordController = TextEditingController();
  final _registerConfirmPasswordController = TextEditingController();
  final _otpController = TextEditingController();
  final _resumeEmailController = TextEditingController();
  final _resetEmailController = TextEditingController();
  final _resetOtpController = TextEditingController();
  final _resetPasswordController = TextEditingController();
  final _resetConfirmPasswordController = TextEditingController();

  bool _loading = false;
  bool _otpMode = false;
  bool _otpResumeMode = false;
  bool _forgotMode = false;
  bool _resetOtpSent = false;
  bool _resetOtpVerified = false;
  bool _showServerSettings = allowCustomApiBaseUrl;
  bool _serverChecking = false;
  bool? _serverReachable;
  List<String> _kelasOptions = const [
    'Robotika A',
    'Robotika B',
    'Robotika C',
    'Mekatronika A',
    'Mekatronika B',
    'Mekatronika C',
    'Rekayasa Elektro A',
    'Rekayasa Elektro B',
    'Rekayasa Elektro C',
  ];
  Map<String, List<String>> _kelasByProdi = {};
  List<Map<String, dynamic>> _prodiOptions = [];
  List<String> _pblTeamOptions = const [
    'AIOT',
    'Robotika',
    'Vision',
    'Embedded',
  ];
  List<Map<String, dynamic>> _pblProjects = [];
  String? _selectedProdiId;
  String _selectedKelas = kFormPilih;
  String _selectedTeam = '';
  String _selectedAngkatan = kFormPilih;
  String _selectedSemester = kFormPilih;
  String _selectedSemesterType = kFormPilih;
  int _registerStep = 0;
  bool _recaptchaEnabled = false;
  String? _recaptchaSiteKey;
  String? _recaptchaPageUrl;
  String? _recaptchaToken;
  String? _resetRecaptchaToken;
  bool _robotAck = false;
  bool _resetRobotAck = false;
  bool _termsAccepted = false;
  String? _captchaToken;
  String _captchaAnswer = '';
  final GlobalKey<MobileImageCaptchaFieldState> _registerCaptchaKey =
      GlobalKey<MobileImageCaptchaFieldState>();
  final GlobalKey<MobileImageCaptchaFieldState> _resetCaptchaKey =
      GlobalKey<MobileImageCaptchaFieldState>();
  String? _resetCaptchaToken;
  String _resetCaptchaAnswer = '';
  String? _pendingEmail;
  String? _debugOtp;
  String? _resetDebugOtp;

  static const _kPendingRegEmailKey = 'pending_registration_email';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.index = widget.initialTab.clamp(0, 1);
    _tabController.addListener(_onAuthTabChanged);
    var initialUrl = widget.initialBaseUrl;
    if (shouldReplaceStoredApiUrl(initialUrl)) {
      initialUrl = defaultApiBaseUrlForPlatform();
    }
    _baseUrlController = TextEditingController(text: initialUrl);
    _serverIpController = TextEditingController(
      text: hostFromApiBaseUrl(initialUrl),
    );
    _ensureReachableApiUrl();
    _restorePendingRegistration();
    _registerPasswordController.addListener(_onRegisterPasswordFieldsChanged);
    _registerConfirmPasswordController.addListener(
      _onRegisterPasswordFieldsChanged,
    );
  }

  void _onRegisterPasswordFieldsChanged() {
    if (mounted &&
        !_forgotMode &&
        _tabController.index == 1 &&
        !_otpMode &&
        !_otpResumeMode &&
        _registerStep == 0) {
      setState(() {});
    }
  }

  Future<void> _persistPendingRegistration(String email) async {
    final trimmed = email.trim();
    if (trimmed.isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kPendingRegEmailKey, trimmed);
  }

  Future<void> _clearPendingRegistration() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kPendingRegEmailKey);
  }

  Future<void> _restorePendingRegistration() async {
    final prefs = await SharedPreferences.getInstance();
    final email = prefs.getString(_kPendingRegEmailKey)?.trim();
    if (!mounted || email == null || email.isEmpty) return;
    _resumeEmailController.text = email;
  }

  /// Saat buka layar auth: pastikan URL API hidup & muat konfigurasi server.
  Future<void> _ensureReachableApiUrl() async {
    if (mounted) {
      setState(() {
        _serverChecking = true;
        _serverReachable = null;
      });
    }

    var url = _baseUrlController.text.trim();
    if (shouldReplaceStoredApiUrl(url)) {
      url = defaultApiBaseUrlForPlatform();
      _baseUrlController.text = url;
    }

    var ok = await probeApiBaseUrl(url);
    if (ok) {
      await _persistApiBaseUrl(url);
      await _loadMobileConfig();
    } else {
      final resolved = await resolveReachableApiBaseUrl();
      if (resolved != null && mounted) {
        _baseUrlController.text = resolved;
        await _persistApiBaseUrl(resolved);
        ok = true;
        await _loadMobileConfig();
      }
    }

    if (mounted) {
      setState(() {
        _serverChecking = false;
        _serverReachable = ok;
        if (!ok && allowCustomApiBaseUrl) {
          _showServerSettings = true;
        }
        _serverIpController.text = hostFromApiBaseUrl(
          _baseUrlController.text.trim(),
        );
      });
    }
  }

  Future<void> _persistApiBaseUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('api_base_url', url.trim());
    activeApiBaseUrl = url.trim();
  }

  Future<void> _testServerConnection() async {
    setState(() {
      _serverChecking = true;
      _serverReachable = null;
    });
    final ok = await probeApiBaseUrl(_baseUrlController.text.trim());
    if (mounted) {
      setState(() {
        _serverChecking = false;
        _serverReachable = ok;
        if (!ok && allowCustomApiBaseUrl) {
          _showServerSettings = true;
        }
      });
      if (!ok) {
        _showMessage(
          allowCustomApiBaseUrl
              ? 'Server tidak terjangkau. Tembak IP Wi-Fi laptop (contoh $kLocalApiHost), pastikan XAMPP/Apache aktif, HP & PC satu Wi-Fi.'
              : 'Server SIMALAS tidak terjangkau. Periksa koneksi internet.',
          isError: true,
        );
      }
    }
  }

  Future<void> _applyServerIp() async {
    final built = apiBaseUrlFromHost(_serverIpController.text);
    _baseUrlController.text = built;
    _serverIpController.text = hostFromApiBaseUrl(built);
    await _persistApiBaseUrl(built);
    await _testServerConnection();
    if (_serverReachable == true) {
      await _loadMobileConfig();
    }
  }

  Future<void> _applyXamppUrl() async {
    final url = defaultApiBaseUrlForPlatform();
    _baseUrlController.text = url;
    _serverIpController.text = hostFromApiBaseUrl(url);
    await _persistApiBaseUrl(url);
    await _testServerConnection();
    await _loadMobileConfig();
  }

  Future<void> _applyProductionUrl() async {
    _baseUrlController.text = kProductionApiBaseUrl;
    _serverIpController.text = hostFromApiBaseUrl(kProductionApiBaseUrl);
    await _persistApiBaseUrl(kProductionApiBaseUrl);
    await _testServerConnection();
    await _loadMobileConfig();
  }

  Future<void> _loadMobileConfig() async {
    try {
      final response = await ApiClient(
        _baseUrlController.text,
      ).get('/mobile/config');
      final data = response['data'] as Map<String, dynamic>? ?? {};
      final app = data['app'] as Map<String, dynamic>? ?? {};
      final serverApi = app['api_base_url']?.toString().trim();
      final recaptcha = data['recaptcha'] as Map<String, dynamic>? ?? {};
      final kelas = List<String>.from(data['kelas'] as List? ?? []);
      final prodi = <Map<String, dynamic>>[];
      for (final item in data['program_studi'] as List? ?? []) {
        if (item is Map) prodi.add(Map<String, dynamic>.from(item));
      }
      final pbl = List<String>.from(data['pbl_tim'] as List? ?? []);
      final pblProjects = <Map<String, dynamic>>[];
      for (final item in data['pbl_projects'] as List? ?? []) {
        if (item is Map) pblProjects.add(Map<String, dynamic>.from(item));
      }
      final byProdiRaw = data['kelas_by_prodi'] as Map? ?? {};
      final byProdi = <String, List<String>>{};
      for (final entry in byProdiRaw.entries) {
        byProdi['${entry.key}'] = List<String>.from(entry.value as List? ?? []);
      }
      if (mounted) {
        setState(() {
          if (serverApi != null &&
              serverApi.isNotEmpty &&
              (kLockApiToProduction ||
                  shouldReplaceStoredApiUrl(_baseUrlController.text))) {
            _baseUrlController.text = serverApi;
            _serverIpController.text = hostFromApiBaseUrl(serverApi);
          }
          _recaptchaEnabled = recaptcha['enabled'] == true;
          _recaptchaSiteKey = recaptcha['site_key']?.toString();
          _recaptchaPageUrl =
              app['recaptcha_page']?.toString() ??
              (serverApi != null && serverApi.isNotEmpty
                  ? '${publicOriginFromApiBase(serverApi)}/mobile/recaptcha'
                  : null);
          if (kelas.isNotEmpty) _kelasOptions = kelas;
          if (byProdi.isNotEmpty) _kelasByProdi = byProdi;
          if (prodi.isNotEmpty) {
            _prodiOptions = prodi;
            if (_selectedProdiId != null &&
                _selectedProdiId!.isNotEmpty &&
                !prodi.any((p) => '${p['id']}' == _selectedProdiId)) {
              _selectedProdiId = null;
            }
            if (!isFormPilih(_selectedKelas)) {
              final filtered = _kelasForProdiId(_selectedProdiId);
              if (!filtered.contains(_selectedKelas)) {
                _selectedKelas = kFormPilih;
              }
            }
          }
          if (pblProjects.isNotEmpty) {
            _pblProjects = pblProjects;
            _pblTeamOptions = pblProjects
                .map((p) => p['nama']?.toString() ?? '')
                .where((n) => n.isNotEmpty)
                .toList();
          } else if (pbl.isNotEmpty) {
            _pblTeamOptions = pbl;
            _pblProjects = pbl
                .map((nama) => {'nama': nama, 'label': nama, 'short_kode': ''})
                .toList();
          }
          if (_selectedTeam.isNotEmpty &&
              _pblTeamOptions.isNotEmpty &&
              !_pblTeamOptions.contains(_selectedTeam)) {
            _selectedTeam = '';
          }
        });
        if (serverApi != null &&
            serverApi.isNotEmpty &&
            (kLockApiToProduction ||
                shouldReplaceStoredApiUrl(_baseUrlController.text))) {
          await _persistApiBaseUrl(serverApi);
        }
      }
    } catch (_) {
      // Config opsional; form tetap bisa dipakai.
    }
  }

  List<String> _kelasForProdiId(String? prodiId) {
    if (prodiId != null && _kelasByProdi.containsKey(prodiId)) {
      final list = _kelasByProdi[prodiId]!;
      if (list.isNotEmpty) return list;
    }
    if (prodiId == null || prodiId.isEmpty) return _kelasOptions;

    String? nama;
    String? kode;
    for (final p in _prodiOptions) {
      if ('${p['id']}' == prodiId) {
        nama = p['nama']?.toString().toLowerCase();
        kode = p['kode']?.toString().toLowerCase();
        break;
      }
    }
    if (nama == null && kode == null) return _kelasOptions;

    return _kelasOptions.where((kelas) {
      final k = kelas.toLowerCase();
      if ((kode?.contains('mekatronika') ?? false) ||
          (nama?.contains('mekatronika') ?? false)) {
        return k.contains('mekatronika');
      }
      if (kode == 'tre' ||
          (nama?.contains('rekayasa') ?? false) ||
          (nama?.contains('elektro') ?? false)) {
        return k.contains('rekayasa') || k.contains('elektro');
      }
      if ((kode?.contains('robotika') ?? false) ||
          (nama?.contains('robotika') ?? false)) {
        return k.contains('robotika');
      }
      return true;
    }).toList();
  }

  String? _inferProdiIdForKelas(String kelas) {
    final k = kelas.toLowerCase();
    for (final p in _prodiOptions) {
      final nama = p['nama']?.toString().toLowerCase() ?? '';
      final kode = p['kode']?.toString().toLowerCase() ?? '';
      if (k.contains('mekatronika') &&
          (nama.contains('mekatronika') || kode.contains('mekatronika'))) {
        return '${p['id']}';
      }
      if (k.contains('robotika') &&
          (nama.contains('robotika') || kode.contains('robotika'))) {
        return '${p['id']}';
      }
      if ((k.contains('rekayasa') ||
              k.contains('elektro') ||
              k.contains('tre')) &&
          (nama.contains('rekayasa') ||
              nama.contains('elektro') ||
              kode == 'tre')) {
        return '${p['id']}';
      }
    }
    return _prodiOptions.isNotEmpty ? '${_prodiOptions.first['id']}' : null;
  }

  String? _validateRegisterStep(int step) {
    switch (step) {
      case 0:
        if (_nameController.text.trim().isEmpty) {
          return 'Nama lengkap wajib diisi.';
        }
        if (!_isValidPersonName(_nameController.text.trim())) {
          return 'Nama hanya boleh huruf (boleh spasi, titik, apostrof, atau tanda hubung).';
        }
        final nim = _nimController.text.trim();
        if (nim.isEmpty) return 'NIM wajib diisi.';
        if (!RegExp(r'^[0-9]+$').hasMatch(nim)) {
          return 'NIM hanya boleh angka.';
        }
        final emailCheck = validateStudentEmail(_emailController.text);
        if (!emailCheck.isValid) {
          return emailCheck.message ?? 'Email tidak valid.';
        }
        final passwordError = _strongPasswordError(
          _registerPasswordController.text,
        );
        if (passwordError != null) {
          return passwordError;
        }
        if (_registerConfirmPasswordController.text.isEmpty) {
          return 'Konfirmasi password wajib diisi.';
        }
        if (_registerConfirmPasswordController.text !=
            _registerPasswordController.text) {
          return 'Konfirmasi password tidak sama.';
        }
        return null;
      case 1:
        if (isFormPilih(_selectedProdiId)) {
          return 'Program studi wajib dipilih.';
        }
        if (isFormPilih(_selectedKelas)) return 'Kelas wajib dipilih.';
        if (isFormPilih(_selectedAngkatan)) return 'Angkatan wajib dipilih.';
        if (_selectedTeam.isEmpty) return 'Tim PBL wajib dipilih.';
        if (isFormPilih(_selectedSemesterType)) {
          return 'Ganjil/genap wajib dipilih.';
        }
        if (isFormPilih(_selectedSemester)) return 'Semester wajib dipilih.';
        final sem = int.tryParse(_selectedSemester);
        if (sem != null && !semesterMatchesType(sem, _selectedSemesterType)) {
          return 'Semester tidak sesuai dengan pilihan ganjil/genap.';
        }
        return null;
      case 2:
        if (!isMobileVerificationComplete(
          recaptchaEnabled: _recaptchaEnabled,
          recaptchaSiteKey: _recaptchaSiteKey,
          recaptchaPageUrl: _recaptchaPageUrl,
          recaptchaToken: _recaptchaToken,
          robotAck: _robotAck,
          captchaToken: _captchaToken,
          captchaAnswer: _captchaAnswer,
        )) {
          return 'Selesaikan verifikasi keamanan terlebih dahulu.';
        }
        if (!_termsAccepted) {
          return 'Baca dan setujui Syarat & Ketentuan terlebih dahulu.';
        }
        return null;
      default:
        return null;
    }
  }

  void _registerNextStep() {
    final err = _validateRegisterStep(_registerStep);
    if (err != null) {
      _showMessage(err, isError: true);
      return;
    }
    if (_registerStep < 2) {
      setState(() => _registerStep++);
      if (_registerStep == 2) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _promptTermsAfterVerification();
        });
      }
      return;
    }
    _register();
  }

  bool get _registerVerificationOk =>
      !_recaptchaEnabled ||
      isMobileVerificationComplete(
        recaptchaEnabled: _recaptchaEnabled,
        recaptchaSiteKey: _recaptchaSiteKey,
        recaptchaPageUrl: _recaptchaPageUrl,
        recaptchaToken: _recaptchaToken,
        robotAck: _robotAck,
        captchaToken: _captchaToken,
        captchaAnswer: _captchaAnswer,
      );

  Future<void> _promptTermsAfterVerification({bool force = false}) async {
    if (!mounted || _registerStep != 2) return;
    if (_termsAccepted && !force) return;
    if (!_registerVerificationOk) return;

    final ok = await showTermsAgreeDialog(context);
    if (!mounted) return;
    if (ok) {
      setState(() => _termsAccepted = true);
    } else if (!force) {
      setState(() => _termsAccepted = false);
    }
  }

  Future<void> _onRegisterRobotAckChanged(bool value) async {
    setState(() {
      _robotAck = value;
      if (!value) _termsAccepted = false;
    });
    if (value) await _promptTermsAfterVerification();
  }

  Future<void> _onRegisterCaptchaChanged(String? token, String answer) async {
    final digits = answer.replaceAll(RegExp(r'\D'), '');
    final wasOk =
        (_captchaToken?.isNotEmpty ?? false) &&
        _captchaAnswer.replaceAll(RegExp(r'\D'), '').length == 6;
    final nowOk = (token?.isNotEmpty ?? false) && digits.length == 6;
    setState(() {
      _captchaToken = token;
      _captchaAnswer = answer;
      if (!nowOk) _termsAccepted = false;
    });
    if (!wasOk && nowOk) {
      await _promptTermsAfterVerification();
    }
  }

  void _registerPrevStep() {
    if (_registerStep > 0) setState(() => _registerStep--);
  }

  void _onAuthTabChanged() {
    if (!_tabController.indexIsChanging && mounted) {
      setState(() {});
    }
  }

  @override
  void dispose() {
    _tabController.removeListener(_onAuthTabChanged);
    _registerPasswordController.removeListener(
      _onRegisterPasswordFieldsChanged,
    );
    _registerConfirmPasswordController.removeListener(
      _onRegisterPasswordFieldsChanged,
    );
    _tabController.dispose();
    _baseUrlController.dispose();
    _serverIpController.dispose();
    _loginIdController.dispose();
    _loginPasswordController.dispose();
    _nameController.dispose();
    _nimController.dispose();
    _emailController.dispose();
    _registerPasswordController.dispose();
    _registerConfirmPasswordController.dispose();
    _otpController.dispose();
    _resumeEmailController.dispose();
    _resetEmailController.dispose();
    _resetOtpController.dispose();
    _resetPasswordController.dispose();
    _resetConfirmPasswordController.dispose();
    super.dispose();
  }

  ApiClient get _api => ApiClient(_baseUrlController.text);

  Future<void> _login() async {
    final nim = _loginIdController.text.trim();
    if (nim.isEmpty) {
      _showMessage('NIM wajib diisi.', isError: true);
      return;
    }
    if (_serverReachable == false) {
      _showMessage(
        allowCustomApiBaseUrl
            ? 'Server SIMALAS tidak terjangkau. Buka ikon gear, tembak IP laptop (contoh $kLocalApiHost), lalu Tes koneksi.'
            : 'Server SIMALAS tidak terjangkau. Periksa koneksi internet lalu ketuk Coba lagi.',
        isError: true,
      );
      if (allowCustomApiBaseUrl) {
        setState(() => _showServerSettings = true);
      }
      return;
    }
    await _run(() async {
      try {
        await _loginWithCurrentUrl(nim);
      } on ApiException catch (e) {
        if (!e.message.contains('Tidak bisa terhubung') &&
            !e.message.contains('Tidak dapat terhubung')) {
          rethrow;
        }
        final resolved = await resolveReachableApiBaseUrl();
        if (resolved == null || resolved == _baseUrlController.text.trim()) {
          rethrow;
        }
        _baseUrlController.text = resolved;
        await _persistApiBaseUrl(resolved);
        if (mounted) {
          _showMessage(
            'URL API diperbaiki otomatis ke $resolved. Mencoba login lagi…',
            isError: false,
          );
        }
        await _loginWithCurrentUrl(nim);
      }
    });
  }

  Future<void> _loginWithCurrentUrl(String nim) async {
    final response = await _api.post(
      '/mobile/login',
      body: {'nitk_nidn': nim, 'password': _loginPasswordController.text},
    );
    final data = response['data'] as Map<String, dynamic>;
    final user = Map<String, dynamic>.from(
      data['user'] as Map<String, dynamic>,
    );
    user['email_verification_required'] =
        data['email_verification_required'] ??
        user['email_verification_required'];
    user['needs_email'] = data['needs_email'] ?? user['needs_email'];
    await widget.onAuthenticated(
      token: data['token'] as String,
      baseUrl: _baseUrlController.text.trim(),
      user: user,
    );
  }

  Future<void> _register() async {
    final err = _validateRegisterStep(2);
    if (err != null) {
      _showMessage(err, isError: true);
      return;
    }
    final nim = _nimController.text.trim();
    final useWebCaptcha = shouldShowRecaptchaWebView(
      recaptchaEnabled: _recaptchaEnabled,
      recaptchaSiteKey: _recaptchaSiteKey,
      recaptchaPageUrl: _recaptchaPageUrl,
    );

    await _run(() async {
      final body = <String, dynamic>{
        'nama_lengkap': _nameController.text.trim(),
        'nitk_nidn': nim,
        'email': _emailController.text.trim(),
        'password': _registerPasswordController.text,
        'password_confirmation': _registerConfirmPasswordController.text,
        'program_studi_id': int.parse(_selectedProdiId!),
        'kelas': _selectedKelas,
        'tim': _selectedTeam,
        'angkatan': int.tryParse(_selectedAngkatan),
        'semester': int.tryParse(_selectedSemester),
        'semester_type': _selectedSemesterType,
      };
      if (useWebCaptcha && _recaptchaToken != null) {
        body['g_recaptcha_response'] = _recaptchaToken;
      } else if (_captchaToken != null &&
          _captchaToken!.isNotEmpty &&
          _captchaAnswer.replaceAll(RegExp(r'\D'), '').length == 6) {
        body['captcha_token'] = _captchaToken;
        body['captcha_answer'] = _captchaAnswer.replaceAll(RegExp(r'\D'), '');
      } else if (!useWebCaptcha && _robotAck) {
        body['mobile_robot_ack'] = true;
      }

      final response = await _api.post(
        '/mobile/register',
        body: body,
        timeout: const Duration(seconds: 45),
      );
      final data = response['data'] as Map<String, dynamic>;
      final sentEmail =
          data['email']?.toString() ?? _emailController.text.trim();
      final debugOtp = data['otp_debug']?.toString();
      if (!mounted) return;
      setState(() {
        _pendingEmail = sentEmail;
        _debugOtp = debugOtp;
        _otpMode = true;
        _otpResumeMode = false;
        _tabController.index = 1;
      });
      await _persistPendingRegistration(sentEmail);
      // Langsung ke form isi OTP — tanpa dialog "Verifikasi sekarang / Daftar akun lain".
      if (!mounted) return;
      final debugHint = (debugOtp != null && debugOtp.isNotEmpty)
          ? ' Mode lokal: OTP testing $debugOtp.'
          : '';
      _showMessage(
        'Kode OTP dikirim ke $sentEmail. Masukkan 6 digit di bawah.$debugHint',
      );
    });
  }

  /// Keluar dari layar OTP tanpa membatalkan registrasi di server (email tetap bisa diverifikasi nanti).
  void _leaveOtpForRegisterAnother() {
    setState(() {
      _otpMode = false;
      _pendingEmail = null;
      _debugOtp = null;
      _tabController.index = 1;
      _resetRegisterFormForAnother();
    });
    _showMessage(
      'Form daftar dikosongkan. OTP akun sebelumnya tetap dikirim ke email masing-masing.',
    );
  }

  void _resetRegisterFormForAnother() {
    _nameController.clear();
    _nimController.clear();
    _emailController.clear();
    _registerPasswordController.clear();
    _registerConfirmPasswordController.clear();
    _otpController.clear();
    _pendingEmail = null;
    _debugOtp = null;
    _registerStep = 0;
    _robotAck = false;
    _termsAccepted = false;
    _captchaToken = null;
    _selectedProdiId = null;
    _selectedKelas = kFormPilih;
    _selectedTeam = '';
    _selectedAngkatan = kFormPilih;
    _selectedSemester = kFormPilih;
    _selectedSemesterType = kFormPilih;
    _recaptchaToken = null;
    _robotAck = false;
    _captchaToken = null;
    _captchaAnswer = '';
  }

  void _openOtpResume() {
    setState(() {
      _otpResumeMode = true;
      _otpMode = false;
      _tabController.index = 1;
      if (_resumeEmailController.text.trim().isEmpty &&
          _emailController.text.trim().isNotEmpty) {
        _resumeEmailController.text = _emailController.text.trim();
      }
      if (_resumeEmailController.text.trim().isEmpty &&
          (_pendingEmail?.trim().isNotEmpty ?? false)) {
        _resumeEmailController.text = _pendingEmail!.trim();
      }
    });
  }

  void _closeOtpResume() {
    setState(() => _otpResumeMode = false);
  }

  Future<void> _resumeRegistration({bool resend = false}) async {
    final email = _resumeEmailController.text.trim();
    if (email.isEmpty) {
      _showMessage(
        'Masukkan alamat email yang digunakan saat pendaftaran.',
        isError: true,
      );
      return;
    }

    await _run(() async {
      final response = await _api.post(
        '/mobile/register/resume',
        body: {'email': email, if (resend) 'resend': true},
        timeout: const Duration(seconds: 45),
      );
      final data = response['data'] as Map<String, dynamic>? ?? {};
      if (!mounted) return;
      setState(() {
        _pendingEmail = data['email']?.toString() ?? email;
        _debugOtp = data['otp_debug']?.toString();
        _otpResumeMode = false;
        _otpMode = true;
      });
      await _persistPendingRegistration(_pendingEmail!);
      _showMessage(
        response['message']?.toString() ??
            'Silakan masukkan kode OTP dari email Anda.',
      );
    });
  }

  Future<void> _verifyOtp() async {
    final otp = _otpController.text.trim();
    if (otp.length != 6 || !RegExp(r'^\d{6}$').hasMatch(otp)) {
      _showMessage('Kode OTP harus berupa 6 digit angka.', isError: true);
      return;
    }

    await _run(() async {
      final response = await _api.post(
        '/mobile/register/verify',
        body: {
          'email': _pendingEmail ?? _emailController.text.trim(),
          'otp_code': _otpController.text.trim(),
        },
      );
      final data = response['data'];
      final fingerId = data is Map<String, dynamic>
          ? data['finger_id']?.toString()
          : null;
      final message =
          response['message']?.toString() ??
          'Email berhasil diverifikasi. Akun menunggu persetujuan admin.';

      if (!mounted) return;
      setState(() {
        _otpMode = false;
        _resetRegisterFormForAnother();
      });
      await _clearPendingRegistration();

      await showMhsRegisterSuccessDialog(
        context,
        message: message,
        fingerId: fingerId,
        onFinished: () {
          if (!mounted) return;
          setState(() => _tabController.index = 0);
        },
      );
    });
  }

  Future<void> _resendOtp() async {
    await _run(() async {
      final response = await _api.post(
        '/mobile/register/resend',
        body: {'email': _pendingEmail ?? _emailController.text.trim()},
        timeout: const Duration(seconds: 45),
      );
      final data = response['data'];
      if (data is Map<String, dynamic>) {
        setState(() {
          _debugOtp = data['otp_debug']?.toString();
        });
      }
      if (mounted) {
        _showMessage(response['message']?.toString() ?? 'OTP terkirim.');
      }
    });
  }

  Future<void> _cancelRegistration() async {
    final email = _pendingEmail ?? _emailController.text.trim();
    if (email.isEmpty) {
      setState(() {
        _otpMode = false;
        _debugOtp = null;
      });
      return;
    }

    await _run(() async {
      final response = await _api.post(
        '/mobile/register/cancel',
        body: {'email': email},
      );
      setState(() {
        _otpMode = false;
        _pendingEmail = null;
        _debugOtp = null;
        _otpController.clear();
        _tabController.index = 1;
      });
      await _clearPendingRegistration();
      if (mounted) {
        _showMessage(
          response['message']?.toString() ?? 'Registrasi dibatalkan.',
        );
      }
    });
  }

  Future<void> _requestPasswordReset() async {
    final useWebCaptcha = shouldShowRecaptchaWebView(
      recaptchaEnabled: _recaptchaEnabled,
      recaptchaSiteKey: _recaptchaSiteKey,
      recaptchaPageUrl: _recaptchaPageUrl,
    );
    final verificationOk = isMobileVerificationComplete(
      recaptchaEnabled: _recaptchaEnabled,
      recaptchaSiteKey: _recaptchaSiteKey,
      recaptchaPageUrl: _recaptchaPageUrl,
      recaptchaToken: _resetRecaptchaToken,
      robotAck: _resetRobotAck,
      captchaToken: _resetCaptchaToken,
      captchaAnswer: _resetCaptchaAnswer,
    );
    if (_recaptchaEnabled && !verificationOk) {
      _showMessage(
        'Selesaikan verifikasi CAPTCHA terlebih dahulu.',
        isError: true,
      );
      return;
    }

    setState(() => _loading = true);
    try {
      final body = <String, dynamic>{
        'email': _resetEmailController.text.trim(),
      };
      if (useWebCaptcha && _resetRecaptchaToken != null) {
        body['g_recaptcha_response'] = _resetRecaptchaToken;
      } else if (_resetCaptchaToken != null &&
          _resetCaptchaToken!.isNotEmpty &&
          _resetCaptchaAnswer.replaceAll(RegExp(r'\D'), '').length == 6) {
        body['captcha_token'] = _resetCaptchaToken;
        body['captcha_answer'] = _resetCaptchaAnswer.replaceAll(
          RegExp(r'\D'),
          '',
        );
      } else if (!useWebCaptcha && _resetRobotAck) {
        body['mobile_robot_ack'] = true;
      }
      final response = await _api.post(
        '/mobile/forgot-password',
        body: body,
        timeout: const Duration(seconds: 45),
      );
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _resetOtpSent = true;
        _resetOtpVerified = false;
        _resetOtpController.clear();
        _resetDebugOtp = data is Map<String, dynamic>
            ? data['otp_debug']?.toString()
            : null;
      });
      _showMessage(
        response['message']?.toString() ??
            'OTP reset terkirim. Cek inbox atau folder spam.',
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      // Sudah ada OTP aktif → kunci flow: lanjut isi OTP / batalkan / tunggu kadaluarsa.
      if (e.code == 'RESET_LOCKED' || e.statusCode == 409) {
        setState(() {
          _resetOtpSent = true;
          _resetOtpVerified = false;
        });
      } else if (_isCaptchaError(e)) {
        await _reloadActiveCaptcha();
      }
      _showMessage(e.toString(), isError: true);
      if (allowCustomApiBaseUrl && e.message.contains('Tidak bisa terhubung')) {
        setState(() => _showServerSettings = true);
      }
    } catch (e) {
      if (mounted) {
        _showMessage('Tidak bisa terhubung ke server: $e', isError: true);
        if (allowCustomApiBaseUrl) setState(() => _showServerSettings = true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _cancelPasswordReset() async {
    final email = _resetEmailController.text.trim();
    if (email.isEmpty) {
      setState(() {
        _forgotMode = false;
        _resetOtpSent = false;
        _resetOtpVerified = false;
        _resetDebugOtp = null;
      });
      return;
    }

    await _run(() async {
      final response = await _api.post(
        '/mobile/forgot-password/cancel',
        body: {'email': email},
      );
      if (!mounted) return;
      setState(() {
        _resetOtpSent = false;
        _resetOtpVerified = false;
        _resetDebugOtp = null;
        _resetOtpController.clear();
        _resetPasswordController.clear();
        _resetConfirmPasswordController.clear();
        _resetRecaptchaToken = null;
        _resetRobotAck = false;
        _resetCaptchaToken = null;
        _resetCaptchaAnswer = '';
      });
      _showMessage(
        response['message']?.toString() ??
            'Permintaan reset dibatalkan. Anda bisa memulai ulang.',
      );
    });
  }

  Future<void> _resendPasswordResetOtp() async {
    final email = _resetEmailController.text.trim();
    if (email.isEmpty) {
      _showMessage('Email tidak valid.', isError: true);
      return;
    }

    await _run(() async {
      final response = await _api.post(
        '/mobile/forgot-password',
        body: {'email': email, 'resend': true},
        timeout: const Duration(seconds: 45),
      );
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _resetOtpController.clear();
        _resetOtpVerified = false;
        _resetPasswordController.clear();
        _resetConfirmPasswordController.clear();
        _resetDebugOtp = data is Map<String, dynamic>
            ? data['otp_debug']?.toString()
            : null;
      });
      _showMessage(
        response['message']?.toString() ??
            'Kode OTP baru dikirim. Cek inbox atau folder spam.',
      );
    });
  }

  Future<void> _verifyPasswordReset() async {
    await _run(() async {
      final response = await _api.post(
        '/mobile/forgot-password/verify',
        body: {
          'email': _resetEmailController.text.trim(),
          'otp_code': _resetOtpController.text.trim(),
        },
      );
      setState(() => _resetOtpVerified = true);
      if (mounted) {
        _showMessage(response['message']?.toString() ?? 'OTP valid.');
      }
    });
  }

  Future<void> _resetPassword() async {
    final password = _resetPasswordController.text;
    final confirm = _resetConfirmPasswordController.text;
    final passwordError = _strongPasswordError(password);
    if (passwordError != null) {
      _showMessage(passwordError, isError: true);
      return;
    }
    if (password != confirm) {
      _showMessage('Konfirmasi password tidak sama.', isError: true);
      return;
    }

    await _run(() async {
      final response = await _api.post(
        '/mobile/forgot-password/reset',
        body: {
          'email': _resetEmailController.text.trim(),
          'password': password,
          'password_confirmation': confirm,
        },
      );
      setState(() {
        _forgotMode = false;
        _resetOtpSent = false;
        _resetOtpVerified = false;
        _resetDebugOtp = null;
        _tabController.index = 0;
      });
      if (mounted) {
        _showMessage(
          response['message']?.toString() ?? 'Password berhasil direset.',
        );
      }
    });
  }

  void _openForgotPassword() {
    setState(() {
      _forgotMode = true;
      _resetOtpSent = false;
      _resetOtpVerified = false;
      _resetRecaptchaToken = null;
      _resetRobotAck = false;
      _resetCaptchaToken = null;
      _resetCaptchaAnswer = '';
      _resetEmailController.text = _emailController.text.trim().isNotEmpty
          ? _emailController.text
          : '';
      _resetOtpController.clear();
      _resetPasswordController.clear();
      _resetConfirmPasswordController.clear();
      _resetDebugOtp = null;
    });
    _loadMobileConfig();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _loading = true);
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) {
        _showMessage(e.toString(), isError: true);
        if (_isCaptchaError(e)) {
          await _reloadActiveCaptcha();
        }
        if (allowCustomApiBaseUrl && e.message.contains('Tidak bisa terhubung')) {
          setState(() => _showServerSettings = true);
        }
      }
    } catch (e) {
      if (mounted) {
        _showMessage('Tidak bisa terhubung ke server: $e', isError: true);
        if (allowCustomApiBaseUrl) setState(() => _showServerSettings = true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  bool _isCaptchaError(ApiException e) {
    final blob = e.toString().toLowerCase();
    return blob.contains('captcha') ||
        blob.contains('bukan robot') ||
        blob.contains('kedaluwarsa');
  }

  Future<void> _reloadActiveCaptcha() async {
    final key = _forgotMode ? _resetCaptchaKey : _registerCaptchaKey;
    await key.currentState?.reload();
    _recaptchaToken = null;
    _resetRecaptchaToken = null;
    if (_forgotMode) {
      _resetCaptchaToken = null;
      _resetCaptchaAnswer = '';
    } else {
      _captchaToken = null;
      _captchaAnswer = '';
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? AppColors.rose : AppColors.teal,
      ),
    );
  }

  String get _authHeroTitle {
    if (_forgotMode) return 'Lupa Password';
    if (_otpResumeMode) return 'Lanjutkan Verifikasi';
    if (_otpMode) return 'Verifikasi Email';
    return _tabController.index == 0 ? 'Hai, login dulu yuk!' : 'Daftar Akun';
  }

  String get _authHeroSubtitle {
    if (_forgotMode) {
      return 'Reset password lewat kode OTP yang dikirim ke email Anda.';
    }
    if (_otpResumeMode) {
      return 'Masukkan email pendaftaran untuk melanjutkan verifikasi OTP.';
    }
    if (_otpMode) {
      return 'Masukkan kode verifikasi 6 digit yang dikirim ke email pendaftaran.';
    }
    return _tabController.index == 0
        ? 'Masuk dengan NIM dan password akun mahasiswa SIMALAS.'
        : 'Buat akun mahasiswa dalam beberapa langkah mudah.';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.auth),
        child: Stack(
          fit: StackFit.expand,
          children: [
            const Positioned.fill(child: _AuthSkyDecor()),
            SafeArea(
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 8, 8, 0),
                    child: Row(
                      children: [
                        if (widget.onBack != null)
                          IconButton(
                            onPressed: widget.onBack,
                            icon: const Icon(
                              Icons.arrow_back_rounded,
                              color: Colors.white,
                            ),
                          ),
                        const _AuthBrandMark(),
                        const Spacer(),
                        IconButton(
                          tooltip: 'Customer Service AI',
                          onPressed: () => showGuestHelpdeskChatSheet(
                            context,
                            apiBaseUrl: _baseUrlController.text.trim().isEmpty
                                ? widget.initialBaseUrl
                                : _baseUrlController.text.trim(),
                          ),
                          icon: const Icon(
                            Icons.smart_toy_rounded,
                            color: Colors.white,
                          ),
                        ),
                        if (allowCustomApiBaseUrl)
                          IconButton(
                            tooltip: 'Tembak IP / pengaturan server',
                            onPressed: () {
                              setState(
                                () =>
                                    _showServerSettings = !_showServerSettings,
                              );
                              if (_showServerSettings) {
                                _testServerConnection();
                              }
                            },
                            icon: Icon(
                              Icons.tune_rounded,
                              color: Colors.white.withValues(alpha: 0.95),
                            ),
                          ),
                      ],
                    ),
                  ),
                  AuthPageHero(
                    title: _authHeroTitle,
                    subtitle: _authHeroSubtitle,
                  ),
                  AuthFormSheet(
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                      physics: const ClampingScrollPhysics(),
                      children: [
                        if (widget.sessionExpiredMessage != null &&
                            widget.sessionExpiredMessage!
                                .trim()
                                .isNotEmpty) ...[
                          InfoStrip(
                            icon: Icons.info_outline_rounded,
                            text: widget.sessionExpiredMessage!,
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (_showServerSettings && allowCustomApiBaseUrl) ...[
                          Text('Tembak IP server lokal', style: AppText.label),
                          const SizedBox(height: 6),
                          Text(
                            'HP & laptop harus satu Wi-Fi. IP laptop sekarang biasanya $kLocalApiHost → URL: ${apiBaseUrlFromHost(kLocalApiHost)}',
                            style: AppText.tiny.copyWith(
                              color: AppColors.teal,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 8),
                          AppTextField(
                            controller: _serverIpController,
                            hint: kLocalApiHost,
                            icon: Icons.router_outlined,
                            keyboardType: TextInputType.url,
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              Expanded(
                                child: SecondaryButton(
                                  label: 'Terapkan IP',
                                  icon: Icons.save_outlined,
                                  onPressed: () {
                                    if (!_serverChecking) _applyServerIp();
                                  },
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: SecondaryButton(
                                  label: _serverChecking
                                      ? 'Cek…'
                                      : 'Tes koneksi',
                                  icon: Icons.wifi_tethering,
                                  onPressed: () {
                                    if (!_serverChecking) {
                                      _applyServerIp();
                                    }
                                  },
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Text('URL API lengkap (opsional)', style: AppText.label),
                          const SizedBox(height: 6),
                          AppTextField(
                            controller: _baseUrlController,
                            hint: defaultApiBaseUrlForPlatform(),
                            icon: Icons.link_rounded,
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              Expanded(
                                child: SecondaryButton(
                                  label: 'Default lokal',
                                  icon: Icons.desktop_windows_outlined,
                                  onPressed: () {
                                    if (!_serverChecking) _applyXamppUrl();
                                  },
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: SecondaryButton(
                                  label: 'Produksi',
                                  icon: Icons.cloud_outlined,
                                  onPressed: () {
                                    if (!_serverChecking) {
                                      _applyProductionUrl();
                                    }
                                  },
                                ),
                              ),
                            ],
                          ),
                          if (_serverReachable == true) ...[
                            const SizedBox(height: 8),
                            const InfoStrip(
                              icon: Icons.check_circle_outline,
                              text: 'Server API terhubung.',
                              tone: InfoStripTone.success,
                            ),
                          ] else if (_serverReachable == false) ...[
                            const SizedBox(height: 8),
                            InfoStrip(
                              icon: Icons.error_outline,
                              text:
                                  'Belum terhubung. Pastikan XAMPP aktif, buka http://$kLocalApiHost/Simalas/public di browser HP, lalu tembak IP yang sama.',
                              tone: InfoStripTone.error,
                            ),
                          ],
                          const SizedBox(height: 12),
                        ],
                        if (_forgotMode)
                          _ForgotPasswordForm(
                            emailController: _resetEmailController,
                            otpController: _resetOtpController,
                            passwordController: _resetPasswordController,
                            confirmPasswordController:
                                _resetConfirmPasswordController,
                            debugOtp: _resetDebugOtp,
                            otpSent: _resetOtpSent,
                            otpVerified: _resetOtpVerified,
                            recaptchaEnabled: _recaptchaEnabled,
                            recaptchaSiteKey: _recaptchaSiteKey,
                            recaptchaPageUrl: _recaptchaPageUrl,
                            recaptchaToken: _resetRecaptchaToken,
                            onRecaptchaToken: (token) =>
                                setState(() => _resetRecaptchaToken = token),
                            robotAck: _resetRobotAck,
                            onRobotAckChanged: (v) =>
                                setState(() => _resetRobotAck = v),
                            apiBaseUrl: _baseUrlController.text.trim(),
                            onCaptchaChanged: (token, answer) {
                              final digits = answer.replaceAll(
                                RegExp(r'\D'),
                                '',
                              );
                              final wasOk =
                                  (_resetCaptchaToken?.isNotEmpty ?? false) &&
                                  _resetCaptchaAnswer
                                          .replaceAll(RegExp(r'\D'), '')
                                          .length ==
                                      6;
                              final nowOk =
                                  (token?.isNotEmpty ?? false) &&
                                  digits.length == 6;
                              _resetCaptchaToken = token;
                              _resetCaptchaAnswer = answer;
                              if (wasOk != nowOk) {
                                setState(() {});
                              }
                            },
                            captchaToken: _resetCaptchaToken,
                            captchaAnswer: _resetCaptchaAnswer,
                            imageCaptchaKey: _resetCaptchaKey,
                            loading: _loading,
                            onRequestOtp: _requestPasswordReset,
                            onResendOtp: _resendPasswordResetOtp,
                            onVerifyOtp: _verifyPasswordReset,
                            onResetPassword: _resetPassword,
                            onCancelReset: _cancelPasswordReset,
                            onBack: () => setState(() => _forgotMode = false),
                          )
                        else ...[
                          Container(
                            decoration: BoxDecoration(
                              color: const Color(0xFF121212),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: AppCardDecoration.borderColor,
                              ),
                            ),
                            child: TabBar(
                              controller: _tabController,
                              indicatorSize: TabBarIndicatorSize.tab,
                              labelColor: AppColors.primary,
                              unselectedLabelColor: AppColors.muted,
                              labelStyle: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                              ),
                              unselectedLabelStyle: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: 14,
                              ),
                              indicator: BoxDecoration(
                                color: AppColors.primary.withValues(alpha: .18),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              dividerColor: Colors.transparent,
                              tabs: const [
                                Tab(text: 'Masuk'),
                                Tab(text: 'Daftar'),
                              ],
                            ),
                          ),
                          const SizedBox(height: 18),
                          AnimatedBuilder(
                            animation: _tabController,
                            builder: (context, _) {
                              final tabBody = _tabController.index == 0
                                  ? _LoginForm(
                                      idController: _loginIdController,
                                      passwordController:
                                          _loginPasswordController,
                                      loading: _loading,
                                      onForgotPassword: _openForgotPassword,
                                      onSubmit: _login,
                                    )
                                  : (_otpResumeMode
                                        ? _OtpResumeForm(
                                            emailController:
                                                _resumeEmailController,
                                            loading: _loading,
                                            onContinue: () =>
                                                _resumeRegistration(),
                                            onResendAndContinue: () =>
                                                _resumeRegistration(
                                                  resend: true,
                                                ),
                                            onBack: _closeOtpResume,
                                          )
                                        : (_otpMode
                                              ? _OtpForm(
                                                  email:
                                                      _pendingEmail ??
                                                      _emailController.text,
                                                  debugOtp: _debugOtp,
                                                  otpController: _otpController,
                                                  loading: _loading,
                                                  onVerify: _verifyOtp,
                                                  onResend: _resendOtp,
                                                  onRegisterAnother:
                                                      _leaveOtpForRegisterAnother,
                                                  onCancelRegistration:
                                                      _cancelRegistration,
                                                )
                                              : _RegisterWizard(
                                                  step: _registerStep,
                                                  nameController:
                                                      _nameController,
                                                  nimController: _nimController,
                                                  emailController:
                                                      _emailController,
                                                  passwordController:
                                                      _registerPasswordController,
                                                  confirmPasswordController:
                                                      _registerConfirmPasswordController,
                                                  prodiOptions: _prodiOptions,
                                                  selectedProdiId:
                                                      _selectedProdiId,
                                                  kelasOptions:
                                                      _kelasForProdiId(
                                                        _selectedProdiId,
                                                      ),
                                                  kelas: _selectedKelas,
                                                  pblTeamOptions:
                                                      _pblTeamOptions,
                                                  pblProjects: _pblProjects,
                                                  team: _selectedTeam,
                                                  angkatan: _selectedAngkatan,
                                                  semester: _selectedSemester,
                                                  semesterType:
                                                      _selectedSemesterType,
                                                  recaptchaEnabled:
                                                      _recaptchaEnabled,
                                                  recaptchaSiteKey:
                                                      _recaptchaSiteKey,
                                                  recaptchaPageUrl:
                                                      _recaptchaPageUrl,
                                                  recaptchaToken:
                                                      _recaptchaToken,
                                                  onRecaptchaToken: (token) =>
                                                      setState(
                                                        () => _recaptchaToken =
                                                            token,
                                                      ),
                                                  robotAck: _robotAck,
                                                  onRobotAckChanged:
                                                      _onRegisterRobotAckChanged,
                                                  termsAccepted: _termsAccepted,
                                                  apiBaseUrl: _baseUrlController
                                                      .text
                                                      .trim(),
                                                  onCaptchaChanged:
                                                      _onRegisterCaptchaChanged,
                                                  captchaToken: _captchaToken,
                                                  captchaAnswer: _captchaAnswer,
                                                  imageCaptchaKey:
                                                      _registerCaptchaKey,
                                                  onProdiChanged: (id) {
                                                    if (isFormPilih(id)) return;
                                                    setState(() {
                                                      _selectedProdiId = id;
                                                      _selectedKelas =
                                                          kFormPilih;
                                                    });
                                                  },
                                                  onKelasChanged: (value) {
                                                    if (isFormPilih(value))
                                                      return;
                                                    setState(
                                                      () => _selectedKelas =
                                                          value,
                                                    );
                                                  },
                                                  onTeamChanged: (value) =>
                                                      setState(
                                                        () => _selectedTeam =
                                                            value,
                                                      ),
                                                  onAngkatanChanged: (value) {
                                                    if (isFormPilih(value))
                                                      return;
                                                    setState(
                                                      () => _selectedAngkatan =
                                                          value,
                                                    );
                                                  },
                                                  onSemesterChanged: (value) {
                                                    if (isFormPilih(value))
                                                      return;
                                                    setState(
                                                      () => _selectedSemester =
                                                          value,
                                                    );
                                                  },
                                                  onSemesterTypeChanged: (value) {
                                                    if (isFormPilih(value))
                                                      return;
                                                    setState(() {
                                                      _selectedSemesterType =
                                                          value;
                                                      final sem = int.tryParse(
                                                        _selectedSemester,
                                                      );
                                                      if (sem != null &&
                                                          !semesterMatchesType(
                                                            sem,
                                                            value,
                                                          )) {
                                                        _selectedSemester =
                                                            kFormPilih;
                                                      }
                                                    });
                                                  },
                                                  loading: _loading,
                                                  onNext: _registerNextStep,
                                                  onBack: _registerPrevStep,
                                                  onResumeOtp: _openOtpResume,
                                                )));
                              return tabBody;
                            },
                          ),
                        ],
                        if (_showServerSettings && allowCustomApiBaseUrl)
                          Padding(
                            padding: const EdgeInsets.only(top: 16),
                            child: Text(
                              'Mode lokal: isi IP Wi-Fi laptop lalu Terapkan IP. Saat APK cloud/VPS dibuild dengan LOCK_API_TO_PRODUCTION, menu ini hilang.',
                              style: AppText.tiny,
                              textAlign: TextAlign.center,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LoginForm extends StatelessWidget {
  const _LoginForm({
    required this.idController,
    required this.passwordController,
    required this.loading,
    required this.onForgotPassword,
    required this.onSubmit,
  });

  final TextEditingController idController;
  final TextEditingController passwordController;
  final bool loading;
  final VoidCallback onForgotPassword;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('NIM / ID PENGGUNA', style: AppText.label),
        const SizedBox(height: 6),
        AppTextField(
          controller: idController,
          hint: 'Masukkan NIM',
          icon: Icons.badge_outlined,
        ),
        const SizedBox(height: 12),
        Text('PASSWORD', style: AppText.label),
        const SizedBox(height: 6),
        AppTextField(
          controller: passwordController,
          hint: 'Password',
          icon: Icons.lock_outline,
          obscureText: true,
        ),
        Align(
          alignment: Alignment.centerRight,
          child: TextButton(
            onPressed: loading ? null : onForgotPassword,
            child: const Text('Lupa Password?'),
          ),
        ),
        const SizedBox(height: 18),
        PrimaryButton(
          label: 'Login',
          icon: Icons.arrow_forward_rounded,
          loading: loading,
          onPressed: onSubmit,
        ),
        const SizedBox(height: 16),
        const InfoStrip(
          icon: Icons.shield_outlined,
          text:
              'Login setelah email diverifikasi dan akun disetujui admin.',
          centered: true,
        ),
      ],
    );
  }
}

class _ForgotPasswordForm extends StatefulWidget {
  const _ForgotPasswordForm({
    required this.emailController,
    required this.otpController,
    required this.passwordController,
    required this.confirmPasswordController,
    required this.debugOtp,
    required this.otpSent,
    required this.otpVerified,
    required this.recaptchaEnabled,
    required this.recaptchaSiteKey,
    required this.recaptchaPageUrl,
    required this.recaptchaToken,
    required this.onRecaptchaToken,
    required this.robotAck,
    required this.onRobotAckChanged,
    this.apiBaseUrl,
    this.onCaptchaChanged,
    this.captchaToken,
    this.captchaAnswer = '',
    this.imageCaptchaKey,
    required this.loading,
    required this.onRequestOtp,
    required this.onResendOtp,
    required this.onVerifyOtp,
    required this.onResetPassword,
    required this.onCancelReset,
    required this.onBack,
  });

  final TextEditingController emailController;
  final TextEditingController otpController;
  final TextEditingController passwordController;
  final TextEditingController confirmPasswordController;
  final String? debugOtp;
  final bool otpSent;
  final bool otpVerified;
  final bool recaptchaEnabled;
  final String? recaptchaSiteKey;
  final String? recaptchaPageUrl;
  final String? recaptchaToken;
  final ValueChanged<String?> onRecaptchaToken;
  final bool robotAck;
  final ValueChanged<bool> onRobotAckChanged;
  final String? apiBaseUrl;
  final void Function(String? token, String answer)? onCaptchaChanged;
  final String? captchaToken;
  final String captchaAnswer;
  final GlobalKey<MobileImageCaptchaFieldState>? imageCaptchaKey;
  final bool loading;
  final VoidCallback onRequestOtp;
  final VoidCallback onResendOtp;
  final VoidCallback onVerifyOtp;
  final VoidCallback onResetPassword;
  final VoidCallback onCancelReset;
  final VoidCallback onBack;

  @override
  State<_ForgotPasswordForm> createState() => _ForgotPasswordFormState();
}

class _ForgotPasswordFormState extends State<_ForgotPasswordForm> {
  @override
  void initState() {
    super.initState();
    widget.passwordController.addListener(_onPasswordFieldsChanged);
    widget.confirmPasswordController.addListener(_onPasswordFieldsChanged);
  }

  @override
  void dispose() {
    widget.passwordController.removeListener(_onPasswordFieldsChanged);
    widget.confirmPasswordController.removeListener(_onPasswordFieldsChanged);
    super.dispose();
  }

  void _onPasswordFieldsChanged() {
    if (mounted) setState(() {});
  }

  _PasswordStrength _strength(String value) => _passwordStrengthOf(value);

  @override
  Widget build(BuildContext context) {
    final password = widget.passwordController.text;
    final confirm = widget.confirmPasswordController.text;
    final strength = _strength(password);
    final confirmMismatch =
        confirm.isNotEmpty && password.isNotEmpty && password != confirm;

    return Column(
      children: [
        const Icon(Icons.lock_reset_rounded, size: 58, color: AppColors.amber),
        const SizedBox(height: 10),
        const Text(
          'Reset Password',
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Text(
          widget.otpVerified
              ? 'Buat password baru untuk akun Anda.'
              : 'OTP akan dikirim ke email mahasiswa yang terdaftar.',
          style: AppText.muted,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 18),
        AppTextField(
          controller: widget.emailController,
          hint: 'Email mahasiswa',
          icon: Icons.mail_outline,
          keyboardType: TextInputType.emailAddress,
          readOnly: widget.otpSent,
          enabled: !widget.otpSent,
        ),
        if (!widget.otpSent) ...[
          const SizedBox(height: 12),
          MobileVerificationField(
            recaptchaEnabled: widget.recaptchaEnabled,
            recaptchaSiteKey: widget.recaptchaSiteKey,
            recaptchaPageUrl: widget.recaptchaPageUrl,
            onRecaptchaToken: widget.onRecaptchaToken,
            robotAck: widget.robotAck,
            onRobotAckChanged: widget.onRobotAckChanged,
            apiBaseUrl: widget.apiBaseUrl,
            onCaptchaChanged: widget.onCaptchaChanged,
            imageCaptchaKey: widget.imageCaptchaKey,
          ),
        ],
        if (widget.otpSent && !widget.otpVerified) ...[
          const SizedBox(height: 10),
          const InfoStrip(
            icon: Icons.lock_clock_outlined,
            text:
                'Email terkunci sampai OTP kadaluarsa (±10 menit) atau Anda batalkan reset. Kirim ulang OTP tetap boleh.',
          ),
          const SizedBox(height: 10),
          OtpPinInput(controller: widget.otpController),
          if (widget.debugOtp != null && widget.debugOtp!.isNotEmpty) ...[
            const SizedBox(height: 10),
            InfoStrip(
              icon: Icons.bug_report_outlined,
              text: 'Mode lokal: kode OTP testing ${widget.debugOtp}',
            ),
          ],
        ],
        if (widget.otpVerified) ...[
          const SizedBox(height: 10),
          AppTextField(
            controller: widget.passwordController,
            hint: 'Password baru',
            icon: Icons.lock_outline,
            obscureText: true,
          ),
          if (password.isNotEmpty) ...[
            const SizedBox(height: 8),
            _PasswordStrengthBar(strength: strength),
          ],
          const SizedBox(height: 5),
          Text(
            'Wajib: huruf besar, huruf kecil, angka, dan simbol (mis. mahasisWa@123.).',
            style: AppText.tiny,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 10),
          AppTextField(
            controller: widget.confirmPasswordController,
            hint: 'Konfirmasi password baru',
            icon: Icons.lock_clock_outlined,
            obscureText: true,
          ),
          if (confirmMismatch) ...[
            const SizedBox(height: 8),
            Text(
              'Konfirmasi password tidak sama.',
              style: AppText.tiny.copyWith(
                color: AppColors.rose,
                fontWeight: FontWeight.w700,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ],
        const SizedBox(height: 16),
        PrimaryButton(
          label: !widget.otpSent
              ? 'Kirim OTP Reset'
              : (widget.otpVerified
                    ? 'Simpan Password Baru'
                    : 'Verifikasi OTP'),
          icon: !widget.otpSent
              ? Icons.mark_email_read_outlined
              : (widget.otpVerified
                    ? Icons.check_circle_outline
                    : Icons.verified_user_outlined),
          loading: widget.loading,
          onPressed: !widget.otpSent
              ? widget.onRequestOtp
              : (widget.otpVerified
                    ? (confirmMismatch ? null : widget.onResetPassword)
                    : widget.onVerifyOtp),
        ),
        if (!widget.otpSent &&
            widget.recaptchaEnabled &&
            !isMobileVerificationComplete(
              recaptchaEnabled: widget.recaptchaEnabled,
              recaptchaSiteKey: widget.recaptchaSiteKey,
              recaptchaPageUrl: widget.recaptchaPageUrl,
              recaptchaToken: widget.recaptchaToken,
              robotAck: widget.robotAck,
              captchaToken: widget.captchaToken,
              captchaAnswer: widget.captchaAnswer,
            )) ...[
          const SizedBox(height: 8),
          Text(
            'Selesaikan verifikasi keamanan terlebih dahulu untuk mengirim OTP.',
            style: AppText.tiny.copyWith(
              color: AppColors.skyDark,
              fontWeight: FontWeight.w700,
            ),
            textAlign: TextAlign.center,
          ),
        ],
        if (widget.otpSent && !widget.otpVerified) ...[
          const SizedBox(height: 10),
          SecondaryButton(
            label: 'Kirim Ulang Kode OTP',
            icon: Icons.refresh_rounded,
            onPressed: widget.loading ? () {} : widget.onResendOtp,
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: widget.loading ? null : widget.onCancelReset,
            child: Text(
              'Batalkan reset password',
              style: AppText.tiny.copyWith(
                color: AppColors.rose,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
        const SizedBox(height: 10),
        SecondaryButton(
          label: 'Kembali ke Login',
          icon: Icons.arrow_back_rounded,
          onPressed: widget.onBack,
        ),
      ],
    );
  }
}

enum _PasswordStrength { none, weak, medium, strong }

bool _isValidPersonName(String value) {
  final trimmed = value.trim();
  if (trimmed.isEmpty) return false;
  // Huruf (Unicode), spasi, titik, apostrof, hubung — tanpa angka/simbol lain.
  return RegExp(r"^[\p{L}\s'.\-]+$", unicode: true).hasMatch(trimmed);
}

String? _strongPasswordError(String value) {
  if (value.length < 8) {
    return 'Password minimal 8 karakter.';
  }
  if (!RegExp(r'[a-z]').hasMatch(value)) {
    return 'Password harus mengandung huruf kecil.';
  }
  if (!RegExp(r'[A-Z]').hasMatch(value)) {
    return 'Password harus mengandung huruf besar.';
  }
  if (!RegExp(r'[0-9]').hasMatch(value)) {
    return 'Password harus mengandung angka.';
  }
  if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) {
    return 'Password harus mengandung simbol (contoh: @ # ! , . _).';
  }
  const weak = {
    'password',
    'password1',
    'password123',
    '12345678',
    '123456789',
    'qwerty123',
    'admin123',
    'simalas123',
    'mahasiswa123',
    'mahasiswa@123',
  };
  if (weak.contains(value.toLowerCase())) {
    return 'Password terlalu umum. Gunakan kombinasi huruf, angka, dan simbol yang unik.';
  }
  if (RegExp(r'^(.)\1{7,}$').hasMatch(value)) {
    return 'Password terlalu sederhana. Hindari karakter berulang.';
  }
  return null;
}

_PasswordStrength _passwordStrengthOf(String value) {
  if (value.isEmpty) return _PasswordStrength.none;
  // Sudah memenuhi semua aturan ketat → Kuat.
  if (_strongPasswordError(value) == null) {
    return _PasswordStrength.strong;
  }
  var score = 0;
  if (value.length >= 8) score++;
  if (RegExp(r'[a-z]').hasMatch(value)) score++;
  if (RegExp(r'[A-Z]').hasMatch(value)) score++;
  if (RegExp(r'[0-9]').hasMatch(value)) score++;
  if (RegExp(r'[^A-Za-z0-9]').hasMatch(value)) score++;
  if (value.length >= 10) score++;
  if (score >= 4) return _PasswordStrength.medium;
  return _PasswordStrength.weak;
}

class _PasswordStrengthBar extends StatelessWidget {
  const _PasswordStrengthBar({required this.strength});

  final _PasswordStrength strength;

  @override
  Widget build(BuildContext context) {
    final (label, color, widthFactor) = switch (strength) {
      _PasswordStrength.weak => ('Lemah', AppColors.rose, 0.33),
      _PasswordStrength.medium => ('Sedang', AppColors.amber, 0.66),
      _PasswordStrength.strong => ('Kuat', AppColors.teal, 1.0),
      _ => ('', AppColors.muted, 0.0),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(6),
          child: LinearProgressIndicator(
            value: widthFactor,
            minHeight: 6,
            backgroundColor: AppCardDecoration.borderColor,
            color: color,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Kekuatan password: $label',
          style: AppText.tiny.copyWith(
            color: color,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _RegisterWizard extends StatelessWidget {
  const _RegisterWizard({
    required this.step,
    required this.nameController,
    required this.nimController,
    required this.emailController,
    required this.passwordController,
    required this.confirmPasswordController,
    required this.prodiOptions,
    required this.selectedProdiId,
    required this.kelasOptions,
    required this.kelas,
    required this.pblTeamOptions,
    required this.pblProjects,
    required this.team,
    required this.angkatan,
    required this.semester,
    required this.semesterType,
    required this.recaptchaEnabled,
    required this.recaptchaSiteKey,
    required this.recaptchaPageUrl,
    required this.recaptchaToken,
    required this.onRecaptchaToken,
    required this.robotAck,
    required this.onRobotAckChanged,
    required this.termsAccepted,
    this.apiBaseUrl,
    this.onCaptchaChanged,
    this.captchaToken,
    this.captchaAnswer = '',
    this.imageCaptchaKey,
    required this.onProdiChanged,
    required this.onKelasChanged,
    required this.onTeamChanged,
    required this.onAngkatanChanged,
    required this.onSemesterChanged,
    required this.onSemesterTypeChanged,
    required this.loading,
    required this.onNext,
    required this.onBack,
    required this.onResumeOtp,
  });

  final int step;
  final TextEditingController nameController;
  final TextEditingController nimController;
  final TextEditingController emailController;
  final TextEditingController passwordController;
  final TextEditingController confirmPasswordController;
  final List<Map<String, dynamic>> prodiOptions;
  final String? selectedProdiId;
  final List<String> kelasOptions;
  final String kelas;
  final List<String> pblTeamOptions;
  final List<Map<String, dynamic>> pblProjects;
  final String team;
  final String angkatan;
  final String semester;
  final String semesterType;
  final bool recaptchaEnabled;
  final String? recaptchaSiteKey;
  final String? recaptchaPageUrl;
  final String? recaptchaToken;
  final ValueChanged<String?> onRecaptchaToken;
  final bool robotAck;
  final ValueChanged<bool> onRobotAckChanged;
  final bool termsAccepted;
  final String? apiBaseUrl;
  final void Function(String? token, String answer)? onCaptchaChanged;
  final String? captchaToken;
  final String captchaAnswer;
  final GlobalKey<MobileImageCaptchaFieldState>? imageCaptchaKey;
  final ValueChanged<String> onProdiChanged;
  final ValueChanged<String> onKelasChanged;
  final ValueChanged<String> onTeamChanged;
  final ValueChanged<String> onAngkatanChanged;
  final ValueChanged<String> onSemesterChanged;
  final ValueChanged<String> onSemesterTypeChanged;
  final bool loading;
  final VoidCallback onNext;
  final VoidCallback onBack;
  final VoidCallback onResumeOtp;

  static const _stepTitles = ['Data Diri', 'Akademik & PBL', 'Verifikasi'];

  @override
  Widget build(BuildContext context) {
    final prodiItems = prodiOptions
        .map((p) => '${p['id']}')
        .where((id) => id.isNotEmpty)
        .toList();
    final prodiValue =
        !isFormPilih(selectedProdiId) && prodiItems.contains(selectedProdiId)
        ? selectedProdiId!
        : kFormPilih;
    final kelasValue = !isFormPilih(kelas) && kelasOptions.contains(kelas)
        ? kelas
        : kFormPilih;
    final angkatanValue = !isFormPilih(angkatan) ? angkatan : kFormPilih;
    final semesterTypeValue = !isFormPilih(semesterType)
        ? semesterType
        : kFormPilih;
    final semesterOptions = semestersForType(
      isFormPilih(semesterType) ? null : semesterType,
    );
    final semesterValue =
        !isFormPilih(semester) && semesterOptions.contains(semester)
        ? semester
        : kFormPilih;
    final password = passwordController.text;
    final confirm = confirmPasswordController.text;
    final confirmMismatch =
        confirm.isNotEmpty && password.isNotEmpty && password != confirm;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: List.generate(3, (i) {
            final active = i <= step;
            return Expanded(
              child: Padding(
                padding: EdgeInsets.only(right: i < 2 ? 6 : 0),
                child: Column(
                  children: [
                    Container(
                      height: 4,
                      decoration: BoxDecoration(
                        color: active
                            ? AppColors.blue
                            : AppColors.muted.withValues(alpha: 0.35),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _stepTitles[i],
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: i == step
                            ? FontWeight.w800
                            : FontWeight.w600,
                        color: i == step ? AppColors.blue : AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 14),
        if (step == 0) ...[
          AppTextField(
            controller: nameController,
            hint: 'Nama lengkap *',
            icon: Icons.person_outline,
            keyboardType: TextInputType.name,
            inputFormatters: [
              FilteringTextInputFormatter.allow(
                RegExp(r"[\p{L}\s'.\-]", unicode: true),
              ),
            ],
          ),
          const SizedBox(height: 10),
          AppTextField(
            controller: nimController,
            hint: 'NIM mahasiswa *',
            icon: Icons.badge_outlined,
            keyboardType: TextInputType.number,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
            ],
          ),
          const SizedBox(height: 10),
          AppEmailField(controller: emailController, hint: 'Email aktif *'),
          const SizedBox(height: 10),
          AppTextField(
            controller: passwordController,
            hint: 'Password kuat (min. 8) *',
            icon: Icons.lock_outline,
            obscureText: true,
          ),
          if (password.isNotEmpty) ...[
            const SizedBox(height: 8),
            _PasswordStrengthBar(
              strength: _passwordStrengthOf(password),
            ),
          ],
          const SizedBox(height: 5),
          Text(
            'Wajib: huruf besar, huruf kecil, angka, dan simbol (mis. mahasisWa@123. atau Nama,1abc!).',
            style: AppText.tiny,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 10),
          AppTextField(
            controller: confirmPasswordController,
            hint: 'Ulangi password *',
            icon: Icons.lock_clock_outlined,
            obscureText: true,
          ),
          if (confirmMismatch) ...[
            const SizedBox(height: 8),
            Text(
              'Konfirmasi password tidak sama.',
              style: AppText.tiny.copyWith(
                color: AppColors.rose,
                fontWeight: FontWeight.w700,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ],
        if (step == 1) ...[
          if (prodiItems.isEmpty)
            const InfoStrip(
              icon: Icons.warning_amber_rounded,
              text:
                  'Program studi belum dimuat. Periksa koneksi server lalu buka ulang tab Daftar.',
            )
          else
            AppDropdown(
              value: prodiValue,
              icon: Icons.school_outlined,
              items: prodiItems,
              labelBuilder: (id) {
                for (final p in prodiOptions) {
                  if ('${p['id']}' == id) {
                    return p['nama']?.toString() ?? id;
                  }
                }
                return id;
              },
              onChanged: onProdiChanged,
            ),
          const SizedBox(height: 10),
          AppDropdown(
            value: kelasValue,
            icon: Icons.meeting_room_outlined,
            items: kelasOptions,
            labelBuilder: (v) => 'Kelas $v',
            onChanged: onKelasChanged,
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: AppDropdown(
                  value: angkatanValue,
                  icon: Icons.calendar_month_outlined,
                  items: const ['2023', '2024', '2025', '2026'],
                  onChanged: onAngkatanChanged,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: AppDropdown(
                  value: semesterTypeValue,
                  icon: Icons.event_repeat_outlined,
                  items: const ['ganjil', 'genap'],
                  labelBuilder: (value) =>
                      value == 'ganjil' ? 'Ganjil' : 'Genap',
                  onChanged: onSemesterTypeChanged,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: AppDropdown(
                  value: semesterValue,
                  icon: Icons.layers_outlined,
                  items: semesterOptions,
                  labelBuilder: (value) => 'Semester $value',
                  onChanged: semesterOptions.isEmpty
                      ? (_) {}
                      : onSemesterChanged,
                ),
              ),
            ],
          ),
          if (isFormPilih(semesterType)) ...[
            const SizedBox(height: 6),
            Text(
              'Pilih ganjil/genap dulu, lalu pilih nomor semester yang sesuai.',
              style: AppText.tiny,
            ),
          ],
          const SizedBox(height: 10),
          PblProjectPickerField(
            projects: pblProjects,
            selectedNama: team,
            onSelected: onTeamChanged,
          ),
          const SizedBox(height: 8),
          Text(
            'Ketik kode (mis. RE-044) atau nama proyek, lalu pilih dari daftar.',
            style: AppText.tiny,
          ),
        ],
        if (step == 2) ...[
          if (recaptchaEnabled) ...[
            MobileVerificationField(
              recaptchaEnabled: recaptchaEnabled,
              recaptchaSiteKey: recaptchaSiteKey,
              recaptchaPageUrl: recaptchaPageUrl,
              onRecaptchaToken: onRecaptchaToken,
              robotAck: robotAck,
              onRobotAckChanged: onRobotAckChanged,
              apiBaseUrl: apiBaseUrl,
              onCaptchaChanged: onCaptchaChanged,
              imageCaptchaKey: imageCaptchaKey,
            ),
            const SizedBox(height: 12),
          ],
          const InfoStrip(
            icon: Icons.mail_outline,
            text:
                'OTP dikirim ke email setelah daftar. Login setelah admin menyetujui.',
          ),
        ],
        const SizedBox(height: 14),
        PrimaryButton(
          label: step < 2 ? 'Lanjut' : 'Daftar',
          icon: step < 2
              ? Icons.arrow_forward_rounded
              : Icons.person_add_alt_1_rounded,
          loading: loading,
          onPressed: step < 2
              ? onNext
              : (termsAccepted ? onNext : null),
        ),
        if (step == 0) ...[
          const SizedBox(height: 10),
          TextButton(
            onPressed: onResumeOtp,
            child: Text(
              'Sudah daftar? Lanjutkan verifikasi OTP',
              style: AppText.tiny.copyWith(
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
              ),
              textAlign: TextAlign.center,
            ),
          ),
        ],
        if (step > 0) ...[
          const SizedBox(height: 10),
          SecondaryButton(
            label: 'Kembali',
            icon: Icons.arrow_back_rounded,
            onPressed: onBack,
          ),
        ],
      ],
    );
  }
}

class _OtpResumeForm extends StatelessWidget {
  const _OtpResumeForm({
    required this.emailController,
    required this.loading,
    required this.onContinue,
    required this.onResendAndContinue,
    required this.onBack,
  });

  final TextEditingController emailController;
  final bool loading;
  final VoidCallback onContinue;
  final VoidCallback onResendAndContinue;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const InfoStrip(
          icon: Icons.mark_email_unread_outlined,
          text:
              'Gunakan fitur ini jika Anda sudah mendaftar tetapi belum sempat '
              'memasukkan kode OTP (misalnya aplikasi tertutup atau kode kedaluwarsa).',
        ),
        const SizedBox(height: 14),
        AppEmailField(controller: emailController, hint: 'Email pendaftaran *'),
        const SizedBox(height: 14),
        PrimaryButton(
          label: 'Lanjutkan Verifikasi',
          icon: Icons.verified_outlined,
          loading: loading,
          onPressed: onContinue,
        ),
        const SizedBox(height: 10),
        SecondaryButton(
          label: 'Kirim Ulang Kode OTP',
          icon: Icons.refresh_rounded,
          onPressed: loading ? () {} : onResendAndContinue,
        ),
        const SizedBox(height: 10),
        SecondaryButton(
          label: 'Kembali ke Form Daftar',
          icon: Icons.arrow_back_rounded,
          onPressed: onBack,
        ),
      ],
    );
  }
}

class _OtpForm extends StatelessWidget {
  const _OtpForm({
    required this.email,
    required this.debugOtp,
    required this.otpController,
    required this.loading,
    required this.onVerify,
    required this.onResend,
    required this.onRegisterAnother,
    required this.onCancelRegistration,
  });

  final String email;
  final String? debugOtp;
  final TextEditingController otpController;
  final bool loading;
  final VoidCallback onVerify;
  final VoidCallback onResend;
  final VoidCallback onRegisterAnother;
  final VoidCallback onCancelRegistration;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'Masukkan OTP yang dikirim ke $email. Cek juga folder spam/promosi.',
          textAlign: TextAlign.center,
          style: AppText.muted.copyWith(fontSize: 13),
        ),
        const SizedBox(height: 10),
        InfoStrip(
          icon: Icons.mail_outline,
          text: 'Kode 6 digit — berlaku 10 menit.',
        ),
        if (debugOtp != null && debugOtp!.isNotEmpty) ...[
          const SizedBox(height: 10),
          InfoStrip(
            icon: Icons.bug_report_outlined,
            text: 'Mode lokal: kode OTP testing $debugOtp',
          ),
        ],
        const SizedBox(height: 14),
        OtpPinInput(controller: otpController),
        const SizedBox(height: 14),
        PrimaryButton(
          label: 'Verifikasi Akun',
          icon: Icons.verified_user_outlined,
          loading: loading,
          onPressed: onVerify,
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: SecondaryButton(label: 'Kirim Ulang', onPressed: onResend),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: SecondaryButton(
                label: 'Daftar akun lain',
                onPressed: onRegisterAnother,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        TextButton(
          onPressed: loading ? null : onCancelRegistration,
          child: Text(
            'Batalkan registrasi ini',
            style: AppText.tiny.copyWith(
              color: AppColors.rose,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({
    super.key,
    required this.api,
    required this.user,
    required this.session,
    required this.themeStore,
    required this.onLogout,
    required this.onSessionExpired,
    required this.onUserChanged,
    this.showLoginWelcome = false,
    this.onLoginWelcomeShown,
  });

  final ApiClient api;
  final Map<String, dynamic> user;
  final SessionStore session;
  final ThemeStore themeStore;
  final Future<void> Function() onLogout;
  final Future<void> Function() onSessionExpired;
  final void Function(Map<String, dynamic> user) onUserChanged;
  final bool showLoginWelcome;
  final VoidCallback? onLoginWelcomeShown;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> with WidgetsBindingObserver {
  int _index = 0;
  int _unreadAnnouncements = 0;

  /// Naik setiap tab Home dibuka ulang agar data absensi di-fetch fresh dari server.
  int _homeReload = 0;
  Timer? _idleTimer;
  bool _coachMarksReady = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _resetIdleTimer();
    _refreshUnreadAnnouncements();
    _registerPushToken();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      checkAndPromptUpdate(context, widget.session.baseUrl);
    });
    if (widget.showLoginWelcome) {
      _coachMarksReady = false;
      WidgetsBinding.instance.addPostFrameCallback((_) => _showLoginWelcome());
    }
  }

  Future<void> _registerPushToken() async {
    final token = widget.session.token;
    if (token == null) return;

    Future<bool> send(String fcmToken) async {
      try {
        await widget.api.post(
          '/mobile/me/push-token',
          body: {'token': fcmToken, 'platform': 'android'},
          auth: true,
        );
        return true;
      } catch (_) {
        return false;
      }
    }

    await PushService.instance.registerWithServer(send);
    // Retry sekali setelah beberapa detik (izin notifikasi kadang telat).
    Future.delayed(const Duration(seconds: 5), () {
      if (mounted) PushService.instance.registerWithServer(send);
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _idleTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkIdleExpiry();
      _registerPushToken();
      checkAndPromptUpdate(context, widget.session.baseUrl);
    }
  }

  void _resetIdleTimer() {
    widget.session.touchActivity();
    _idleTimer?.cancel();
    _idleTimer = Timer(SessionStore.idleTimeout, _handleIdleTimeout);
  }

  Future<void> _checkIdleExpiry() async {
    if (!mounted) return;
    if (await widget.session.isIdleExpired()) {
      await _handleIdleTimeout();
    } else {
      _resetIdleTimer();
    }
  }

  Future<void> _handleIdleTimeout() async {
    if (!mounted) return;
    _idleTimer?.cancel();
    await widget.onSessionExpired();
  }

  void _onUserInteraction() {
    _resetIdleTimer();
  }

  @override
  void didUpdateWidget(covariant HomeShell oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.showLoginWelcome && !oldWidget.showLoginWelcome) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _showLoginWelcome());
    }
  }

  Future<void> _showLoginWelcome() async {
    if (!mounted || !widget.showLoginWelcome) return;
    await showMhsLoginWelcomeDialog(context, widget.user);
    widget.onLoginWelcomeShown?.call();
    if (!mounted) return;
    setState(() => _coachMarksReady = true);
    if (mhsNeedsEmailVerification(widget.user)) {
      await _promptEmailVerification();
    }
  }

  Future<void> _promptEmailVerification() async {
    final needsEmail = mhsNeedsEmail(widget.user);
    final action = await showDialog<String>(
      context: context,
      barrierDismissible: true,
      builder: (ctx) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Text(
            'Verifikasi Email',
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          content: Text(
            needsEmail
                ? 'Akun Anda belum memiliki email. Tambahkan email aktif untuk verifikasi.'
                : 'Email belum diverifikasi. Masukkan kode OTP yang dikirim ke email Anda.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, 'later'),
              child: const Text('Nanti'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, 'verify'),
              child: const Text('Verifikasi sekarang'),
            ),
          ],
        );
      },
    );
    if (!mounted || action != 'verify') return;
    setState(() => _index = 2);
    await Future<void>.delayed(const Duration(milliseconds: 300));
    if (!mounted) return;
    await showEmailVerificationDialog(
      context: context,
      api: widget.api,
      user: widget.user,
      onUserChanged: widget.onUserChanged,
    );
  }

  void _goToTab(int index) {
    _onUserInteraction();
    if (index == 1 && !mhsIsFingerprintEnrolled(widget.user)) {
      mhsShowEnrollRequiredSnack(context);
      return;
    }
    setState(() {
      if (index == 0) _homeReload++;
      _index = index;
    });
    if (index == 0) {
      _refreshUnreadAnnouncements();
    }
  }

  void _openHistory({int tab = 0}) {
    _onUserInteraction();
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => MhsHistoryPage(
          api: widget.api,
          user: widget.user,
          initialTab: 0,
        ),
      ),
    );
  }

  void _handleUserChanged(Map<String, dynamic> user) {
    widget.onUserChanged(user);
    // Jangan remount beranda saat data di-sync dari _load() sendiri (hindari loop loading).
    if (_index != 0) {
      setState(() => _homeReload++);
    }
  }

  Future<void> _refreshUnreadAnnouncements() async {
    try {
      final response = await widget.api.get(
        '/mobile/notifications?per_page=5&page=1',
        auth: true,
      );
      if (mounted) {
        setState(() {
          _unreadAnnouncements =
              (response['unread_count'] as num?)?.toInt() ?? 0;
        });
      }
    } catch (_) {
      // Badge refresh should not interrupt the main app.
    }
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      DashboardPage(
        key: ValueKey('home_$_homeReload'),
        api: widget.api,
        user: widget.user,
        coachMarksReady: _coachMarksReady,
        onUserChanged: _handleUserChanged,
        onUnreadChanged: (value) =>
            setState(() => _unreadAnnouncements = value),
        onNavigateTab: _goToTab,
        onOpenHistory: () => _openHistory(tab: 0),
        themeStore: widget.themeStore,
      ),
      StudentAttendancePage(
        api: widget.api,
        user: widget.user,
        onOpenHistory: () => _openHistory(tab: 0),
      ),
      ProfilePage(
        api: widget.api,
        user: widget.user,
        onUserChanged: _handleUserChanged,
        onLogout: widget.onLogout,
      ),
    ];

    return Listener(
      onPointerDown: (_) => _onUserInteraction(),
      child: Scaffold(
        backgroundColor: widget.themeStore.isLight
            ? AppColors.bgLight
            : AppColors.bg,
        body: pages[_index],
        bottomNavigationBar: StitchBottomNav(
                selectedIndex: _index,
                unreadCount: _unreadAnnouncements,
                profilePhotoUrl: widget.user['profile_photo_url']?.toString(),
                profileInitial:
                    (widget.user['nama_lengkap']?.toString().trim().isNotEmpty ==
                        true)
                    ? widget.user['nama_lengkap']
                          .toString()
                          .trim()[0]
                          .toUpperCase()
                    : 'M',
                onSelected: _goToTab,
              ),
      ),
    );
  }
}

class DashboardPage extends StatefulWidget {
  const DashboardPage({
    super.key,
    required this.api,
    required this.user,
    required this.onUserChanged,
    required this.onUnreadChanged,
    required this.onNavigateTab,
    this.onOpenHistory,
    this.coachMarksReady = true,
    this.onOpenSettings,
    this.themeStore,
  });

  final ApiClient api;
  final Map<String, dynamic> user;
  final void Function(Map<String, dynamic> user) onUserChanged;
  final ValueChanged<int> onUnreadChanged;
  final ValueChanged<int> onNavigateTab;
  final VoidCallback? onOpenHistory;
  final bool coachMarksReady;
  final VoidCallback? onOpenSettings;
  final ThemeStore? themeStore;

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  static const _coachMarksPrefKey = 'mhs_home_coach_marks_done';

  final GlobalKey _keyProfil = GlobalKey();
  final GlobalKey _keyNotif = GlobalKey();
  final GlobalKey _keyCs = GlobalKey();
  final GlobalKey _keyAbsensi = GlobalKey();
  final GlobalKey _keyTim = GlobalKey();
  final GlobalKey _keyRiwayat = GlobalKey();
  final GlobalKey _keyPengumuman = GlobalKey();
  final GlobalKey _keyProfilMenu = GlobalKey();
  final GlobalKey _keySaran = GlobalKey();
  final GlobalKey _keyLainnya = GlobalKey();
  final GlobalKey _keyAktivitas = GlobalKey();
  bool _coachMarksQueued = false;
  BuildContext? _showcaseHostContext;

  late Future<_DashboardData> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_DashboardData> _load() async {
    final responses = await Future.wait([
      widget.api.get('/mobile/me', auth: true),
      widget.api.get('/mobile/me/absensi/snapshot?days=365', auth: true),
      widget.api.get('/mobile/announcements?limit=20', auth: true),
      widget.api.get('/mobile/notifications?per_page=5&page=1', auth: true),
    ]);
    final user = responses[0]['data'] as Map<String, dynamic>;
    final absensiResponse = responses[1];
    final unreadCount =
        (responses[3]['unread_count'] as num?)?.toInt() ??
        (responses[2]['unread_count'] as num?)?.toInt() ??
        0;
    widget.onUserChanged(user);
    widget.onUnreadChanged(unreadCount);
    return _DashboardData(
      user: user,
      harian: mhsFilterHarianStudentView(
        List<Map<String, dynamic>>.from(
          absensiResponse['ringkasan_harian'] as List? ?? [],
        ),
      ),
      announcements: List<Map<String, dynamic>>.from(
        responses[2]['data'] as List,
      ),
      unreadAnnouncements: unreadCount,
    );
  }

  Future<void> _markAnnouncementRead(Map<String, dynamic> item) async {
    if (item['id'] == null) return;
    await widget.api.post(
      '/mobile/announcements/${item['id']}/read',
      auth: true,
    );
    if (mounted) setState(() => _future = _load());
  }

  @override
  void didUpdateWidget(covariant DashboardPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.coachMarksReady && !oldWidget.coachMarksReady) {
      _queueCoachMarksIfNeeded();
    }
  }

  Future<void> _onCoachMarksFinished() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_coachMarksPrefKey, true);
  }

  void _queueCoachMarksIfNeeded() {
    if (_coachMarksQueued || !widget.coachMarksReady) return;
    _coachMarksQueued = true;
    _startCoachMarks();
  }

  void _replayCoachMarks() {
    _startCoachMarks(force: true);
  }

  void _startCoachMarks({bool force = false}) {
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      if (!force) {
        final prefs = await SharedPreferences.getInstance();
        if (prefs.getBool(_coachMarksPrefKey) ?? false) return;
      }
      if (!mounted) return;
      final showcaseCtx = _showcaseHostContext;
      if (showcaseCtx == null || !showcaseCtx.mounted) return;
      ShowCaseWidget.of(showcaseCtx).startShowCase([
        _keyProfil,
        _keyNotif,
        _keyCs,
        _keyAbsensi,
        _keyTim,
        _keyRiwayat,
        _keyPengumuman,
        _keyProfilMenu,
        _keySaran,
        _keyLainnya,
        _keyAktivitas,
      ], delay: Duration(milliseconds: force ? 250 : 450));
    });
  }

  @override
  Widget build(BuildContext context) {
    return ShowCaseWidget(
      onFinish: _onCoachMarksFinished,
      enableAutoScroll: true,
      blurValue: 2,
      disableBarrierInteraction: false,
      globalTooltipActionConfig: const TooltipActionConfig(
        position: TooltipActionPosition.inside,
        alignment: MainAxisAlignment.spaceBetween,
        actionGap: 12,
      ),
      globalTooltipActions: [
        TooltipActionButton(
          type: TooltipDefaultActionType.skip,
          name: 'Lewati',
          textStyle: const TextStyle(color: Colors.white70, fontSize: 13),
          backgroundColor: const Color(0x33FFFFFF),
        ),
        TooltipActionButton(
          type: TooltipDefaultActionType.next,
          name: 'Lanjut',
          textStyle: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
          backgroundColor: MhsHomePalette.headerBlue,
        ),
      ],
      builder: (showcaseContext) {
        _showcaseHostContext = showcaseContext;
        return FutureBuilder<_DashboardData>(
          future: _future,
          builder: (context, snapshot) {
            final loading =
                !snapshot.hasData &&
                snapshot.connectionState != ConnectionState.done;
            final data = snapshot.data;
            final user = data?.user ?? widget.user;
            if (data != null) {
              _queueCoachMarksIfNeeded();
            }

            return BlurPageLoader(
              loading: loading,
              message: 'Memuat beranda…',
              child: RefreshIndicator(
                onRefresh: () async => setState(() => _future = _load()),
                color: MhsHomePalette.headerBlue,
                child: CustomScrollView(
                  slivers: [
                    SliverToBoxAdapter(
                      child: MhsGopayHomeTop(
                        user: user,
                        unreadCount: data?.unreadAnnouncements ?? 0,
                        onNotifications: () {
                          showAnnouncementsPopup(
                            context,
                            api: widget.api,
                            onUnreadChanged: widget.onUnreadChanged,
                          );
                        },
                        onSuggestion: () =>
                            showMhsHelpdeskChatSheet(context, api: widget.api),
                        onOpenProfile: () => widget.onNavigateTab(2),
                        onNavigateTab: widget.onNavigateTab,
                        onBantuan: () =>
                            showMhsSuggestionSheet(context, api: widget.api),
                        onOpenTeam: () {
                          Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => MhsTeamPage(
                                api: widget.api,
                                user: user,
                              ),
                            ),
                          );
                        },
                        onOpenHistory: () => widget.onOpenHistory?.call(),
                        profileShowcaseKey: _keyProfil,
                        notifShowcaseKey: _keyNotif,
                        csShowcaseKey: _keyCs,
                        absensiShowcaseKey: _keyAbsensi,
                        timShowcaseKey: _keyTim,
                        riwayatShowcaseKey: _keyRiwayat,
                        pengumumanShowcaseKey: _keyPengumuman,
                        profilMenuShowcaseKey: _keyProfilMenu,
                        saranShowcaseKey: _keySaran,
                        lainnyaShowcaseKey: _keyLainnya,
                        onReplayCoachMarks: _replayCoachMarks,
                        onOpenSettings: () {
                          final store = widget.themeStore;
                          if (store == null) {
                            widget.onOpenSettings?.call();
                            return;
                          }
                          showMhsSettingsSheet(
                            context,
                            store,
                            api: widget.api,
                            user: user,
                            onReplayCoachMarks: _replayCoachMarks,
                            onOpenCsAi: () => showMhsHelpdeskChatSheet(
                              context,
                              api: widget.api,
                            ),
                            onOpenProfile: () => widget.onNavigateTab(2),
                          );
                        },
                      ),
                    ),
                    if (data != null)
                      SliverToBoxAdapter(
                        child: Container(
                          color: MhsHomePalette.body(context),
                          padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              MhsHomeQuickStats(
                                tim: user['tim']?.toString(),
                                onOpenTeam: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute<void>(
                                      builder: (_) => MhsTeamPage(
                                        api: widget.api,
                                        user: user,
                                      ),
                                    ),
                                  );
                                },
                                onOpenAbsensi: () => widget.onNavigateTab(1),
                              ),
                              const SizedBox(height: 16),
                              MhsInfoBannerCarousel(
                                announcements: data.announcements,
                                onAnnouncementTap: _markAnnouncementRead,
                              ),
                              const SizedBox(height: 16),
                              MhsRecentActivityFeed(
                                harian: data.harian,
                                limit: 8,
                                onOpenAbsensi: () => widget.onNavigateTab(1),
                                onOpenAll: () => widget.onOpenHistory?.call(),
                                showcaseKey: _keyAktivitas,
                              ),
                            ],
                          ),
                        ),
                      )
                    else if (snapshot.hasError)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.cloud_off_outlined,
                                  size: 48,
                                  color: AppColors.muted.withValues(alpha: 0.6),
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  snapshot.error is ApiException
                                      ? (snapshot.error as ApiException).message
                                      : 'Gagal memuat beranda. Periksa koneksi server.',
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    color: AppColors.rose,
                                    height: 1.4,
                                  ),
                                ),
                                const SizedBox(height: 20),
                                OutlinedButton.icon(
                                  onPressed: () =>
                                      setState(() => _future = _load()),
                                  icon: const Icon(Icons.refresh_rounded),
                                  label: const Text('Coba lagi'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _DashboardData {
  const _DashboardData({
    required this.user,
    required this.harian,
    required this.announcements,
    required this.unreadAnnouncements,
  });

  final Map<String, dynamic> user;
  final List<Map<String, dynamic>> harian;
  final List<Map<String, dynamic>> announcements;
  final int unreadAnnouncements;
}

String _semesterTypeLabel(dynamic value) {
  final raw = value?.toString().toLowerCase();
  if (raw == 'ganjil') return 'Ganjil';
  if (raw == 'genap') return 'Genap';
  return '';
}


class HistoryPage extends StatefulWidget {
  const HistoryPage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.loader,
    required this.itemBuilder,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final Future<Map<String, dynamic>> Function() loader;
  final Widget Function(Map<String, dynamic> item) itemBuilder;

  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await widget.loader();
    return List<Map<String, dynamic>>.from(response['data'] as List);
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async => setState(() => _future = _load()),
      child: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          final items = snapshot.data ?? [];
          return ListView(
            padding: const EdgeInsets.all(18),
            children: [
              PageHeader(
                title: widget.title,
                subtitle: widget.subtitle,
                icon: widget.icon,
                color: widget.color,
              ),
              const SizedBox(height: 14),
              if (!snapshot.hasData)
                Center(
                  child: Padding(
                    padding: const EdgeInsets.all(40),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: const [
                        const ModernLoadIndicator(size: 56),
                        SizedBox(height: 16),
                        SizedBox(
                          width: 28,
                          height: 28,
                          child: CircularProgressIndicator(strokeWidth: 2.5),
                        ),
                      ],
                    ),
                  ),
                )
              else if (items.isEmpty)
                const EmptyState(text: 'Belum ada data pada riwayat ini.')
              else
                ...items.map(widget.itemBuilder),
            ],
          );
        },
      ),
    );
  }
}

class AnnouncementPage extends StatefulWidget {
  const AnnouncementPage({
    super.key,
    required this.api,
    required this.user,
    required this.unreadCount,
    required this.onUnreadChanged,
  });

  final ApiClient api;
  final Map<String, dynamic> user;
  final int unreadCount;
  final ValueChanged<int> onUnreadChanged;

  @override
  State<AnnouncementPage> createState() => _AnnouncementPageState();
}

class _AnnouncementPageState extends State<AnnouncementPage> {
  int _page = 1;
  int _lastPage = 1;
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  Future<void> _load({bool reset = false}) async {
    final nextPage = reset ? 1 : _page + 1;
    if (!reset && nextPage > _lastPage) return;

    setState(() {
      _error = null;
      if (reset) {
        _loading = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final response = await widget.api.get(
        '/mobile/notifications?page=$nextPage&per_page=12',
        auth: true,
      );
      final rows = List<Map<String, dynamic>>.from(
        response['data'] as List? ?? [],
      );
      final pagination = Map<String, dynamic>.from(
        response['pagination'] as Map? ?? {},
      );

      if (!mounted) return;
      setState(() {
        if (reset) {
          _items = rows;
        } else {
          _items = [..._items, ...rows];
        }
        _page = (pagination['current_page'] as num?)?.toInt() ?? nextPage;
        _lastPage = (pagination['last_page'] as num?)?.toInt() ?? 1;
        _loading = false;
        _loadingMore = false;
      });
      widget.onUnreadChanged((response['unread_count'] as num?)?.toInt() ?? 0);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  Future<void> _markRead(Map<String, dynamic> item) async {
    if (item['id'] == null) return;
    final type = item['type']?.toString() ?? 'announcement';
    if (type == 'suggestion_reply') {
      await widget.api.post(
        '/mobile/suggestions/${item['id']}/read-reply',
        auth: true,
      );
    } else {
      await widget.api.post(
        '/mobile/announcements/${item['id']}/read',
        auth: true,
      );
    }
    if (mounted) {
      await _load(reset: true);
    }
  }

  Future<void> _markAllRead() async {
    try {
      await Future.wait([
        widget.api.post('/mobile/announcements/read-all', auth: true),
        widget.api.post('/mobile/suggestions/replies/read-all', auth: true),
      ]);
      if (mounted) await _load(reset: true);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: AppColors.rose),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _items
        .where((e) => !isNotificationExpired(e) && !_isLockerAnnouncement(e))
        .toList();
    final hasUnread = items.any((e) => e['is_read'] != true);
    final hasMore = _page < _lastPage;

    return SafeArea(
      bottom: false,
      child: BlurPageLoader(
        loading: _loading,
        message: 'Memuat notifikasi…',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => _load(reset: true),
                child: ListView(
                  padding: const EdgeInsets.all(18),
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Notifikasi',
                                style: TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Pembaruan sistem, pengumuman lab, dan balasan admin saran Anda.',
                                style: AppText.muted,
                              ),
                            ],
                          ),
                        ),
                        if (hasUnread)
                          TextButton(
                            onPressed: _markAllRead,
                            child: const Text(
                              'Tandai semua dibaca',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                color: AppColors.blue,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    if (_error != null)
                      GlassCard(
                        child: Text(
                          _error!,
                          style: const TextStyle(color: AppColors.rose),
                        ),
                      )
                    else if (items.isEmpty && !_loading)
                      const EmptyState(text: 'Belum ada notifikasi.')
                    else ...[
                      ...items.map(
                        (item) => AnnouncementBubble(
                          item: item,
                          onTap: () => _markRead(item),
                        ),
                      ),
                      if (hasMore) ...[
                        const SizedBox(height: 8),
                        OutlinedButton(
                          onPressed: _loadingMore ? null : () => _load(),
                          child: _loadingMore
                              ? const SizedBox.square(
                                  dimension: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : Text(
                                  'Muat halaman ${_page + 1} dari $_lastPage',
                                ),
                        ),
                      ] else if (_lastPage > 1) ...[
                        const SizedBox(height: 12),
                        Text(
                          'Halaman $_page dari $_lastPage',
                          textAlign: TextAlign.center,
                          style: AppText.muted,
                        ),
                      ],
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

Future<void> showEmailVerificationDialog({
  required BuildContext context,
  required ApiClient api,
  required Map<String, dynamic> user,
  required void Function(Map<String, dynamic> user) onUserChanged,
}) async {
  final needsEmail = mhsNeedsEmail(user);
  final emailController = TextEditingController(
    text: user['email']?.toString() ?? '',
  );
  final otpController = TextEditingController();
  var step = needsEmail ? 'email' : 'otp';
  var pendingEmail = user['email']?.toString() ?? '';
  var loading = false;
  String? errorText;

  Future<void> submitEmail(StateSetter setSheetState) async {
    final email = emailController.text.trim();
    if (email.isEmpty) {
      setSheetState(() => errorText = 'Email wajib diisi.');
      return;
    }
    setSheetState(() {
      loading = true;
      errorText = null;
    });
    try {
      final response = await api.post(
        '/mobile/me/account-email',
        auth: true,
        body: {'email': email},
      );
      pendingEmail = response['data']?['email']?.toString() ?? email;
      setSheetState(() {
        step = 'otp';
        loading = false;
        errorText = null;
      });
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ?? 'Kode OTP dikirim.',
            ),
            backgroundColor: AppColors.teal,
          ),
        );
      }
    } catch (e) {
      setSheetState(() {
        loading = false;
        errorText = e.toString();
      });
    }
  }

  Future<void> verifyOtp(StateSetter setSheetState) async {
    final otp = otpController.text.trim();
    if (otp.length != 6) {
      setSheetState(() => errorText = 'Masukkan 6 digit kode OTP.');
      return;
    }
    setSheetState(() {
      loading = true;
      errorText = null;
    });
    try {
      final response = await api.post(
        '/mobile/me/account-email/verify',
        auth: true,
        body: {'email': pendingEmail, 'otp_code': otp},
      );
      final updated = Map<String, dynamic>.from(
        response['data']?['user'] as Map<String, dynamic>? ?? user,
      );
      updated['email_verification_required'] = false;
      updated['needs_email'] = false;
      onUserChanged(updated);
      if (context.mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ?? 'Email berhasil diverifikasi.',
            ),
            backgroundColor: AppColors.teal,
          ),
        );
      }
    } catch (e) {
      setSheetState(() {
        loading = false;
        errorText = e.toString();
      });
    }
  }

  Future<void> resendOtp(StateSetter setSheetState) async {
    setSheetState(() {
      loading = true;
      errorText = null;
    });
    try {
      final response = await api.post(
        '/mobile/me/account-email/resend',
        auth: true,
      );
      setSheetState(() => loading = false);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ?? 'OTP dikirim ulang.',
            ),
            backgroundColor: AppColors.teal,
          ),
        );
      }
    } catch (e) {
      setSheetState(() {
        loading = false;
        errorText = e.toString();
      });
    }
  }

  if (!needsEmail && pendingEmail.isNotEmpty) {
    unawaited(
      api
          .post('/mobile/me/account-email/resend', auth: true)
          .catchError((_) {}),
    );
  }

  await showDialog<void>(
    context: context,
    barrierDismissible: !loading,
    builder: (dialogContext) {
      return StatefulBuilder(
        builder: (context, setSheetState) {
          return Dialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(24),
            ),
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                24,
                24,
                24,
                20 + MediaQuery.of(context).viewInsets.bottom,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.12),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            step == 'email'
                                ? Icons.email_outlined
                                : Icons.pin_outlined,
                            color: AppColors.primary,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            step == 'email'
                                ? 'Tambah Email'
                                : 'Verifikasi Email',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        if (!loading)
                          IconButton(
                            onPressed: () => Navigator.pop(context),
                            icon: const Icon(Icons.close_rounded),
                            visualDensity: VisualDensity.compact,
                          ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      step == 'email'
                          ? 'Masukkan email aktif. Kode OTP akan dikirim ke email tersebut.'
                          : 'Kode OTP telah dikirim ke $pendingEmail. Masukkan 6 digit kode di bawah.',
                      style: AppText.tiny.copyWith(color: AppColors.muted),
                    ),
                    if (step == 'otp') ...[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.skyLight.withValues(alpha: 0.5),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: AppColors.primary.withValues(alpha: 0.2),
                          ),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.mark_email_read_outlined,
                              color: AppColors.primary,
                              size: 20,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                'Menunggu kode OTP… cek kotak masuk & folder spam.',
                                style: AppText.tiny.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    if (errorText != null) ...[
                      InfoStrip(icon: Icons.error_outline, text: errorText!),
                      const SizedBox(height: 10),
                    ],
                    if (step == 'email') ...[
                      AppTextField(
                        controller: emailController,
                        hint: 'Email aktif',
                        icon: Icons.email_outlined,
                        keyboardType: TextInputType.emailAddress,
                      ),
                      const SizedBox(height: 16),
                      PrimaryButton(
                        label: 'Kirim Kode OTP',
                        icon: Icons.mail_outline,
                        loading: loading,
                        onPressed: loading
                            ? null
                            : () => submitEmail(setSheetState),
                      ),
                    ] else ...[
                      AppTextField(
                        controller: otpController,
                        hint: 'Kode OTP (6 digit)',
                        icon: Icons.pin_outlined,
                        keyboardType: TextInputType.number,
                      ),
                      const SizedBox(height: 16),
                      PrimaryButton(
                        label: 'Verifikasi Email',
                        icon: Icons.verified_outlined,
                        loading: loading,
                        onPressed: loading
                            ? null
                            : () => verifyOtp(setSheetState),
                      ),
                      const SizedBox(height: 6),
                      Center(
                        child: TextButton(
                          onPressed: loading
                              ? null
                              : () => resendOtp(setSheetState),
                          child: const Text('Kirim ulang kode OTP'),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          );
        },
      );
    },
  );

  emailController.dispose();
  otpController.dispose();
}

class ProfilePage extends StatefulWidget {
  const ProfilePage({
    super.key,
    required this.api,
    required this.user,
    required this.onUserChanged,
    required this.onLogout,
  });

  final ApiClient api;
  final Map<String, dynamic> user;
  final void Function(Map<String, dynamic> user) onUserChanged;
  final Future<void> Function() onLogout;

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  bool _refreshing = false;
  List<String> _kelasOptions = const [
    'Robotika A',
    'Robotika B',
    'Robotika C',
    'Mekatronika A',
    'Mekatronika B',
    'Mekatronika C',
  ];
  List<Map<String, dynamic>> _prodiOptions = [];
  List<String> _pblTeamOptions = const [
    'AIOT',
    'Robotika',
    'Vision',
    'Embedded',
  ];

  @override
  void initState() {
    super.initState();
    _loadProfileOptions();
  }

  Future<void> _loadProfileOptions() async {
    try {
      final config = await widget.api.get('/mobile/config');
      final data = config['data'] as Map<String, dynamic>? ?? {};
      final kelas = List<String>.from(data['kelas'] as List? ?? []);
      final prodi = <Map<String, dynamic>>[];
      for (final item in data['program_studi'] as List? ?? []) {
        if (item is Map) {
          prodi.add(Map<String, dynamic>.from(item));
        }
      }
      final pbl = List<String>.from(data['pbl_tim'] as List? ?? []);
      if (mounted) {
        setState(() {
          if (kelas.isNotEmpty) _kelasOptions = kelas;
          _prodiOptions = prodi;
          if (pbl.isNotEmpty) _pblTeamOptions = pbl;
        });
      }
    } catch (_) {
      // Pakai fallback statis.
    }
  }

  String? _inferProdiIdForKelas(String kelas) {
    final k = kelas.toLowerCase();
    for (final p in _prodiOptions) {
      final nama = p['nama']?.toString().toLowerCase() ?? '';
      final kode = p['kode']?.toString().toLowerCase() ?? '';
      if (k.contains('mekatronika') &&
          (nama.contains('mekatronika') || kode.contains('mekatronika'))) {
        return '${p['id']}';
      }
      if (k.contains('rekayasa') ||
          k.contains('elektro') ||
          k.contains('tre')) {
        if (nama.contains('rekayasa') ||
            nama.contains('elektro') ||
            kode == 'tre') {
          return '${p['id']}';
        }
      }
      if (k.contains('robotika') &&
          (nama.contains('robotika') || kode.contains('robotika'))) {
        return '${p['id']}';
      }
    }
    return _prodiOptions.isNotEmpty ? '${_prodiOptions.first['id']}' : null;
  }

  Future<void> _refreshProfile() async {
    setState(() => _refreshing = true);
    try {
      final response = await widget.api.get('/mobile/me', auth: true);
      widget.onUserChanged(response['data'] as Map<String, dynamic>);
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _pickProfilePhoto() async {
    try {
      final picker = ImagePicker();
      final photo = await picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 82,
        maxWidth: 1200,
      );
      if (photo == null) return;

      setState(() => _refreshing = true);
      final response = await widget.api.multipart(
        '/mobile/me/photo',
        fieldName: 'photo',
        filePath: photo.path,
        auth: true,
      );
      widget.onUserChanged(response['data'] as Map<String, dynamic>);
      if (mounted) _showSnack('Foto profil berhasil diperbarui.');
    } catch (e) {
      if (mounted) _showSnack(e.toString(), isError: true);
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _deleteProfilePhoto() async {
    try {
      setState(() => _refreshing = true);
      final response = await widget.api.post(
        '/mobile/me/photo/delete',
        auth: true,
      );
      widget.onUserChanged(response['data'] as Map<String, dynamic>);
      if (mounted) _showSnack('Foto profil dihapus.');
    } catch (e) {
      if (mounted) _showSnack(e.toString(), isError: true);
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _saveProfile({
    required String nama,
    required String kelas,
    required String tim,
    required String angkatan,
    required String semester,
    required String semesterType,
    required int programStudiId,
  }) async {
    try {
      setState(() => _refreshing = true);
      final body = <String, dynamic>{
        'nama_lengkap': nama,
        'kelas': kelas,
        'tim': tim,
        'angkatan': int.tryParse(angkatan),
        'semester': int.tryParse(semester),
        'semester_type': semesterType,
        'program_studi_id': programStudiId,
      };
      final response = await widget.api.post(
        '/mobile/me/profile',
        auth: true,
        body: body,
      );
      widget.onUserChanged(response['data'] as Map<String, dynamic>);
      if (mounted) {
        Navigator.pop(context);
        _showSnack('Profil berhasil diperbarui.');
      }
    } catch (e) {
      if (mounted) _showSnack(e.toString(), isError: true);
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  void _showSnack(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? AppColors.rose : AppColors.teal,
      ),
    );
  }

  Future<void> _openEditProfileSheet() async {
    final user = widget.user;
    final nameController = TextEditingController(
      text: user['nama_lengkap']?.toString() ?? '',
    );
    var kelas = user['kelas']?.toString() ?? 'Robotika A';
    var team = user['tim']?.toString() ?? 'AIOT';
    final teamOptions = _pblTeamOptions;
    if (!teamOptions.contains(team)) {
      team = teamOptions.isNotEmpty ? teamOptions.first : 'AIOT';
    }
    var angkatan = user['angkatan']?.toString() ?? '2024';
    var semester = user['semester']?.toString() ?? '1';
    var semesterType = user['semester_type']?.toString() ?? 'ganjil';
    var programStudiId = user['program_studi_id']?.toString();
    if (programStudiId == null || programStudiId.isEmpty) {
      programStudiId = _inferProdiIdForKelas(kelas);
    }
    final prodiOptions = _prodiOptions;
    final kelasOptions = _kelasOptions;

    if (!mounted) return;

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Edit Profil Mahasiswa',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Program studi wajib diisi agar tampil di admin.',
                      style: AppText.tiny.copyWith(color: AppColors.amber),
                    ),
                    const SizedBox(height: 14),
                    AppTextField(
                      controller: nameController,
                      hint: 'Nama lengkap',
                      icon: Icons.person_outline,
                    ),
                    const SizedBox(height: 10),
                    if (prodiOptions.isEmpty)
                      const InfoStrip(
                        icon: Icons.warning_amber_rounded,
                        text:
                            'Daftar prodi belum dimuat. Periksa koneksi lalu buka ulang halaman profil.',
                      )
                    else
                      AppDropdown(
                        value: programStudiId ?? '${prodiOptions.first['id']}',
                        icon: Icons.school_outlined,
                        items: prodiOptions.map((p) => '${p['id']}').toList(),
                        labelBuilder: (value) {
                          for (final p in prodiOptions) {
                            if ('${p['id']}' == value) {
                              return p['nama']?.toString() ?? value;
                            }
                          }
                          return value;
                        },
                        onChanged: (value) =>
                            setSheetState(() => programStudiId = value),
                      ),
                    const SizedBox(height: 10),
                    AppDropdown(
                      value: kelasOptions.contains(kelas)
                          ? kelas
                          : kelasOptions.first,
                      icon: Icons.meeting_room_outlined,
                      items: kelasOptions,
                      labelBuilder: (value) => 'Kelas $value',
                      onChanged: (value) => setSheetState(() {
                        kelas = value;
                        if (programStudiId == null || programStudiId!.isEmpty) {
                          programStudiId = _inferProdiIdForKelas(kelas);
                        }
                      }),
                    ),
                    const SizedBox(height: 10),
                    AppDropdown(
                      value: angkatan,
                      icon: Icons.calendar_month_outlined,
                      items: const ['2023', '2024', '2025', '2026'],
                      onChanged: (value) =>
                          setSheetState(() => angkatan = value),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: AppDropdown(
                            value: semester,
                            icon: Icons.layers_outlined,
                            items: const [
                              '1',
                              '2',
                              '3',
                              '4',
                              '5',
                              '6',
                              '7',
                              '8',
                            ],
                            labelBuilder: (value) => 'Semester $value',
                            onChanged: (value) =>
                                setSheetState(() => semester = value),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: AppDropdown(
                            value: semesterType,
                            icon: Icons.event_repeat_outlined,
                            items: const ['ganjil', 'genap'],
                            labelBuilder: (value) =>
                                value == 'ganjil' ? 'Ganjil' : 'Genap',
                            onChanged: (value) =>
                                setSheetState(() => semesterType = value),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    AppDropdown(
                      value: team,
                      icon: Icons.groups_2_outlined,
                      items: teamOptions,
                      labelBuilder: (value) => value,
                      onChanged: (value) => setSheetState(() => team = value),
                    ),
                    const SizedBox(height: 16),
                    PrimaryButton(
                      label: 'Simpan Profil',
                      icon: Icons.save_outlined,
                      loading: _refreshing,
                      onPressed: () {
                        final prodiId = int.tryParse(programStudiId ?? '');
                        if (prodiId == null || prodiOptions.isEmpty) {
                          _showSnack(
                            'Pilih program studi terlebih dahulu.',
                            isError: true,
                          );
                          return;
                        }
                        _saveProfile(
                          nama: nameController.text.trim(),
                          kelas: kelas,
                          tim: team,
                          angkatan: angkatan,
                          semester: semester,
                          semesterType: semesterType,
                          programStudiId: prodiId,
                        );
                      },
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    ).whenComplete(() {
      nameController.dispose();
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.user;
    final enrolled = mhsIsFingerprintEnrolled(user);

    return SafeArea(
      bottom: false,
      child: BlurPageLoader(
        loading: _refreshing,
        message: 'Memuat profil…',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 24),
                children: [
                  ProfileHeroCard(user: user, onPickPhoto: _pickProfilePhoto),
                  const SizedBox(height: 20),
                  ProfileSection(
                    title: 'IDENTITAS AKUN',
                    children: [
                      ProfileRow(
                        label: 'NIM',
                        value: user['nitk_nidn']?.toString() ?? '-',
                      ),
                      ProfileRow(
                        label: 'Role',
                        value: user['role']?.toString() ?? 'mahasiswa',
                      ),
                      ProfileEmailRow(
                        user: user,
                        api: widget.api,
                        onUserChanged: widget.onUserChanged,
                      ),
                    ],
                  ),
                  ProfileSection(
                    title: 'DATA AKADEMIK',
                    children: [
                      ProfileRow(
                        label: 'Prodi',
                        value: user['prodi']?.toString() ?? 'Belum diisi',
                      ),
                      ProfileRow(
                        label: 'Kelas',
                        value: user['kelas']?.toString() ?? '-',
                      ),
                      ProfileMultilineRow(
                        label: 'Tim PBL',
                        value: user['tim']?.toString() ?? '-',
                      ),
                      ProfileRow(
                        label: 'Angkatan',
                        value: user['angkatan']?.toString() ?? '-',
                      ),
                      ProfileRow(
                        label: 'Semester',
                        value:
                            '${user['semester'] ?? '-'} ${_semesterTypeLabel(user['semester_type'])}',
                      ),
                    ],
                  ),
                  ProfileSection(
                    title: 'STATUS & SIDIK JARI',
                    children: [
                      ProfileStatusRow(label: 'Status Mahasiswa', user: user),
                      ProfileRow(
                        label: 'Finger ID',
                        value: user['finger_id']?.toString() ?? '-',
                      ),
                      ProfileStatusRow(label: 'Status Fingerprint', user: user),
                    ],
                  ),
                  if (!enrolled) ...[
                    const SizedBox(height: 4),
                    MhsEnrollWarningBanner(),
                  ],
                  const SizedBox(height: 14),
                  SecondaryButton(
                    label: 'Keluar dari Akun',
                    icon: Icons.logout_rounded,
                    onPressed: () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          backgroundColor: AppColors.card,
                          title: const Text('Keluar dari aplikasi?'),
                          content: const Text(
                            'Sesi login akan diakhiri di perangkat ini.',
                          ),
                          actions: [
                            TextButton(
                              onPressed: () => Navigator.pop(ctx, false),
                              child: const Text('Batal'),
                            ),
                            FilledButton(
                              onPressed: () => Navigator.pop(ctx, true),
                              child: const Text('Keluar'),
                            ),
                          ],
                        ),
                      );
                      if (ok == true) {
                        await widget.onLogout();
                      }
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Indikator loading modern (tanpa logo) — tiga titik bergelombang.
class ModernLoadIndicator extends StatefulWidget {
  const ModernLoadIndicator({super.key, this.size = 48});

  final double size;

  @override
  State<ModernLoadIndicator> createState() => _ModernLoadIndicatorState();
}

class _ModernLoadIndicatorState extends State<ModernLoadIndicator>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dot = widget.size * 0.18;
    final reduce = MediaQuery.maybeOf(context)?.disableAnimations ?? false;
    if (reduce) {
      return SizedBox(
        width: widget.size,
        height: dot,
        child: const LinearProgressIndicator(
          backgroundColor: AppColors.skyLight,
          color: AppColors.primary,
        ),
      );
    }

    return SizedBox(
      width: widget.size,
      height: dot * 2.2,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          return Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(3, (i) {
              final phase = (_controller.value + i * 0.2) % 1.0;
              final scale = 0.55 + Curves.easeInOut.transform(phase) * 0.45;
              return Padding(
                padding: EdgeInsets.symmetric(horizontal: dot * 0.35),
                child: Transform.scale(
                  scale: scale,
                  child: Container(
                    width: dot,
                    height: dot,
                    decoration: BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primary.withValues(alpha: 0.35),
                          blurRadius: 6,
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          );
        },
      ),
    );
  }
}

/// Intro cold-start: logo brand utuh + kilau + garis aksen, lalu fade out.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key, this.onFinished});

  final VoidCallback? onFinished;

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  bool _finished = false;

  static const Duration _total = Duration(milliseconds: 4200);

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: _total);
    _ctrl.addStatusListener((status) {
      if (status == AnimationStatus.completed) _finish();
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (MediaQuery.of(context).disableAnimations) {
        _finish();
        return;
      }
      _ctrl.forward();
    });
  }

  void _finish() {
    if (_finished || !mounted) return;
    _finished = true;
    widget.onFinished?.call();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final logoOpacity = CurvedAnimation(
      parent: _ctrl,
      curve: const Interval(0.08, 0.36, curve: Curves.easeOutCubic),
    );
    final logoScale = Tween<double>(begin: 0.88, end: 1).animate(
      CurvedAnimation(
        parent: _ctrl,
        curve: const Interval(0.08, 0.40, curve: Curves.easeOutCubic),
      ),
    );
    final lineGrow = CurvedAnimation(
      parent: _ctrl,
      curve: const Interval(0.34, 0.55, curve: Curves.easeInOutCubic),
    );
    final shine = CurvedAnimation(
      parent: _ctrl,
      curve: const Interval(0.28, 0.58, curve: Curves.easeInOut),
    );
    final fadeOut = Tween<double>(begin: 1, end: 0).animate(
      CurvedAnimation(
        parent: _ctrl,
        curve: const Interval(0.84, 1.0, curve: Curves.easeInCubic),
      ),
    );

    return Scaffold(
      backgroundColor: const Color(0xFF05080F),
      body: AnimatedBuilder(
        animation: _ctrl,
        builder: (context, _) {
          return Opacity(
            opacity: fadeOut.value,
            child: Stack(
              fit: StackFit.expand,
              children: [
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Color(0xFF05080F),
                        Color(0xFF0A121C),
                        Color(0xFF071018),
                      ],
                    ),
                  ),
                ),
                Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 36),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        FadeTransition(
                          opacity: logoOpacity,
                          child: ScaleTransition(
                            scale: logoScale,
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                SizedBox(
                                  width: 168,
                                  height: 168,
                                  child: Stack(
                                    fit: StackFit.expand,
                                    children: [
                                      Image.asset(
                                        'assets/branding/aiot-simalas-mark.png',
                                        fit: BoxFit.contain,
                                        filterQuality: FilterQuality.high,
                                      ),
                                      IgnorePointer(
                                        child: Opacity(
                                          opacity: 0.35,
                                          child: ShaderMask(
                                            blendMode: BlendMode.srcATop,
                                            shaderCallback: (bounds) {
                                              final t = shine.value;
                                              return LinearGradient(
                                                begin: Alignment(
                                                  -1.2 + t * 2.4,
                                                  -0.2,
                                                ),
                                                end: Alignment(
                                                  -0.4 + t * 2.4,
                                                  0.2,
                                                ),
                                                colors: const [
                                                  Colors.transparent,
                                                  Colors.white54,
                                                  Colors.transparent,
                                                ],
                                                stops: const [0.35, 0.5, 0.65],
                                              ).createShader(bounds);
                                            },
                                            child: Image.asset(
                                              'assets/branding/aiot-simalas-mark.png',
                                              fit: BoxFit.contain,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 14),
                                const Text(
                                  'Smart Lab',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 22,
                                    letterSpacing: 0.4,
                                    height: 1.1,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: 120,
                          height: 2,
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(2),
                            child: Align(
                              alignment: Alignment.centerLeft,
                              widthFactor: lineGrow.value.clamp(0.0, 1.0),
                              child: const ColoredBox(color: Color(0xFF00AED6)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SplashGridPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.04)
      ..strokeWidth = 1;
    for (var x = 0.0; x < size.width; x += 40) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }
    for (var y = 0.0; y < size.height; y += 40) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class FingerprintHeroArt extends StatelessWidget {
  const FingerprintHeroArt({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 260,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(32),
        gradient: RadialGradient(
          colors: [
            AppColors.teal.withValues(alpha: .32),
            AppColors.blue.withValues(alpha: .18),
            AppColors.card.withValues(alpha: .72),
          ],
          radius: .95,
        ),
        border: Border.all(color: Colors.white.withValues(alpha: .10)),
        boxShadow: [
          BoxShadow(
            color: AppColors.teal.withValues(alpha: .20),
            blurRadius: 38,
            offset: const Offset(0, 20),
          ),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned.fill(child: CustomPaint(painter: _CircuitPainter())),
          Container(
            width: 150,
            height: 150,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withValues(alpha: .08),
              border: Border.all(color: AppColors.teal.withValues(alpha: .34)),
            ),
            child: const Icon(
              Icons.fingerprint_rounded,
              size: 112,
              color: AppColors.teal,
            ),
          ),
          Positioned(
            left: 22,
            bottom: 24,
            child: _FloatingBadge(
              icon: Icons.fact_check_outlined,
              label: 'Absensi IoT',
            ),
          ),
        ],
      ),
    );
  }
}

class _FloatingBadge extends StatelessWidget {
  const _FloatingBadge({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: AppColors.bg.withValues(alpha: .56),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: .10)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: AppColors.teal, size: 17),
          const SizedBox(width: 7),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _CircuitPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: .075)
      ..strokeWidth = 1.2
      ..style = PaintingStyle.stroke;

    for (var i = 0; i < 7; i++) {
      final y = 28.0 + (i * 31);
      canvas.drawLine(Offset(22, y), Offset(size.width - 22, y + 18), paint);
    }

    final dotPaint = Paint()
      ..color = AppColors.teal.withValues(alpha: .22)
      ..style = PaintingStyle.fill;
    for (final offset in [
      Offset(size.width * .16, size.height * .22),
      Offset(size.width * .78, size.height * .30),
      Offset(size.width * .23, size.height * .72),
      Offset(size.width * .84, size.height * .76),
    ]) {
      canvas.drawCircle(offset, 4, dotPaint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final titleStyle = TextStyle(
      fontSize: compact ? 22 : 30,
      fontWeight: FontWeight.w900,
    );
    if (compact) {
      return Row(
        children: [
          const LogoMark(size: 44),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Smart Lab', style: titleStyle),
                Text(
                  'Mahasiswa',
                  style: AppText.muted.copyWith(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const LogoMark(),
        const SizedBox(height: 18),
        Text('Smart Lab', style: titleStyle),
        const SizedBox(height: 6),
        const Text(
          'Sistem Manajemen Laboratorium Pintar untuk mahasiswa.',
          style: TextStyle(color: AppColors.muted, height: 1.5),
        ),
      ],
    );
  }
}

/// Logo animasi MP4 untuk splash & login.
/// Diputar dari 0.5s → akhir (skip frame gelap di awal), lalu loop (opsional).
class AnimatedLogoMark extends StatefulWidget {
  const AnimatedLogoMark({
    super.key,
    this.size = 96,
    this.onDark = false,
    this.loop = true,
    this.markOnly = false,
    this.onCompleted,
    this.onFailed,
  });

  final double size;
  final bool onDark;
  final bool loop;

  /// Fallback/ikon tanpa teks brand (hindari duplikat judul di UI).
  final bool markOnly;
  final VoidCallback? onCompleted;
  final VoidCallback? onFailed;

  static const Duration trimStart = Duration(milliseconds: 500);

  @override
  State<AnimatedLogoMark> createState() => _AnimatedLogoMarkState();
}

class _AnimatedLogoMarkState extends State<AnimatedLogoMark>
    with SingleTickerProviderStateMixin {
  VideoPlayerController? _controller;
  bool _ready = false;
  bool _failed = false;
  bool _seeking = false;
  bool _completedNotified = false;
  late final AnimationController _fallbackMotion;

  @override
  void initState() {
    super.initState();
    _fallbackMotion = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1600),
    )..repeat(reverse: true);
    _boot();
  }

  void _notifyCompleted() {
    if (_completedNotified) return;
    _completedNotified = true;
    widget.onCompleted?.call();
  }

  void _notifyFailed() {
    if (_completedNotified) return;
    _completedNotified = true;
    widget.onFailed?.call();
  }

  Future<void> _boot() async {
    try {
      final c = VideoPlayerController.asset(
        'assets/branding/aiot-simalas-logo-anim.mp4',
      );
      await c.initialize();
      if (!mounted) {
        await c.dispose();
        return;
      }
      await c.setVolume(0);
      // Jangan pakai setLooping(true): restart ke 0.0 bikin flash gelap.
      await c.setLooping(false);
      await c.seekTo(AnimatedLogoMark.trimStart);
      c.addListener(_onTick);
      await c.play();
      if (!mounted) {
        c.removeListener(_onTick);
        await c.dispose();
        return;
      }
      setState(() {
        _controller = c;
        _ready = true;
      });
    } catch (e, st) {
      if (kDebugMode) {
        debugPrint('AnimatedLogoMark video gagal: $e\n$st');
      }
      if (mounted) {
        setState(() => _failed = true);
        _notifyFailed();
      }
    }
  }

  void _onTick() {
    final c = _controller;
    if (c == null || !c.value.isInitialized || _seeking) return;
    final pos = c.value.position;
    final dur = c.value.duration;
    if (dur <= Duration.zero) return;

    // Mentok akhir → ulang dari 0.5s (atau pause + callback kalau tidak loop).
    if (pos >= dur - const Duration(milliseconds: 80)) {
      if (!widget.loop) {
        c.pause();
        _notifyCompleted();
        return;
      }
      _seeking = true;
      c.seekTo(AnimatedLogoMark.trimStart).then((_) {
        _seeking = false;
        if (mounted) c.play();
      });
      return;
    }

    // Kalau terseret ke frame < 0.5s, loncat lagi.
    if (pos < AnimatedLogoMark.trimStart - const Duration(milliseconds: 40)) {
      _seeking = true;
      c.seekTo(AnimatedLogoMark.trimStart).then((_) {
        _seeking = false;
      });
    }
  }

  @override
  void dispose() {
    _fallbackMotion.dispose();
    _controller?.removeListener(_onTick);
    _controller?.dispose();
    super.dispose();
  }

  Widget _markFallback() {
    return AnimatedBuilder(
      animation: _fallbackMotion,
      builder: (context, child) {
        final t = _fallbackMotion.value;
        return Transform.scale(
          scale: 0.96 + (t * 0.08),
          child: child,
        );
      },
      child: LogoMark(
        size: widget.size,
        fullLogo: !widget.markOnly,
        onDark: widget.onDark,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_failed || !_ready || _controller == null) {
      return _markFallback();
    }

    final c = _controller!;
    final vw = c.value.size.width;
    final vh = c.value.size.height;

    // Video brand sudah berisi wordmark — tanpa kotak putih tebal.
    return SizedBox(
      width: widget.size,
      height: widget.size,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(widget.size * 0.14),
        child: ColoredBox(
          color: Colors.white,
          child: FittedBox(
            fit: BoxFit.contain,
            child: SizedBox(
              width: vw > 0 ? vw : widget.size,
              height: vh > 0 ? vh : widget.size,
              child: VideoPlayer(c),
            ),
          ),
        ),
      ),
    );
  }
}

class LogoMark extends StatelessWidget {
  const LogoMark({
    super.key,
    this.size = 54,
    this.onDark = false,
    this.fullLogo = false,
    this.plain = false,
  });

  final double size;
  final bool onDark;

  /// Logo lengkap (ikon + teks) untuk layar besar; selain itu pakai mark.
  final bool fullLogo;

  /// Tanpa kotak gelap / shadow — untuk app bar terang.
  final bool plain;

  @override
  Widget build(BuildContext context) {
    // fullLogo eksplisit — jangan auto-ganti ke logo penuh hanya karena size besar
    // (itu yang bikin glow biru "kosongan" di auth).
    final asset = fullLogo
        ? 'assets/branding/aiot-simalas-logo.png'
        : 'assets/branding/aiot-simalas-mark.png';
    final img = Image.asset(
      asset,
      width: size,
      height: size,
      fit: BoxFit.contain,
      gaplessPlayback: true,
      filterQuality: FilterQuality.high,
      errorBuilder: (context, error, stackTrace) =>
          _LogoMarkFallback(size: size, plain: plain),
    );

    if (plain || onDark) {
      // Tanpa kotak / shadow cahaya di belakang logo.
      return SizedBox(width: size, height: size, child: img);
    }

    return SizedBox(
      width: size,
      height: size,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(size * 0.18),
          color: const Color(0xFF0B1220),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.22),
              blurRadius: 14,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(size * 0.18),
          child: img,
        ),
      ),
    );
  }
}

class _LogoMarkFallback extends StatelessWidget {
  const _LogoMarkFallback({required this.size, this.plain = false});

  final double size;
  final bool plain;

  @override
  Widget build(BuildContext context) {
    if (plain) {
      return Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        child: Text(
          'S',
          style: TextStyle(
            color: AppColors.skyDark,
            fontSize: size * 0.42,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
    }

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.28),
        gradient: AppGradients.sunset,
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.32),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Center(
        child: Text(
          'S',
          style: TextStyle(
            color: Colors.white,
            fontSize: size * 0.42,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class HeroPanel extends StatelessWidget {
  const HeroPanel({super.key, required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: AppGradients.sunset,
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: .28),
            blurRadius: 30,
            offset: const Offset(0, 18),
          ),
        ],
      ),
      child: Row(
        children: [
          const LogoMark(size: 48, onDark: true),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: .86),
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class PageHeader extends StatelessWidget {
  const PageHeader({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      child: Row(
        children: [
          IconBubble(icon: icon, color: color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(subtitle, style: AppText.muted),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SummaryCard extends StatelessWidget {
  const SummaryCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    this.prominent = false,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final bool prominent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(prominent ? 20 : 16),
      constraints: BoxConstraints(minHeight: prominent ? 132 : 0),
      decoration: AppCardDecoration.box(context, radius: prominent ? 20 : 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBubble(icon: icon, color: color, size: prominent ? 48 : 40),
          SizedBox(height: prominent ? 16 : 14),
          Text(label, style: AppText.label),
          SizedBox(height: prominent ? 8 : 6),
          Text(
            value,
            style: TextStyle(
              fontSize: prominent ? 30 : 22,
              fontWeight: FontWeight.w900,
              height: 1.1,
            ),
          ),
        ],
      ),
    );
  }
}


class AttendanceMiniChart extends StatelessWidget {
  const AttendanceMiniChart({super.key, required this.absensi});

  final List<Map<String, dynamic>> absensi;

  @override
  Widget build(BuildContext context) {
    final bars = List<int>.generate(5, (index) {
      if (absensi.isEmpty) return index == 4 ? 2 : 1;
      return index < absensi.length ? 4 : 1;
    });

    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SectionTitle(
            title: 'Trend Kehadiran Singkat',
            action: 'Minggu ini',
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 120,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                for (var i = 0; i < bars.length; i++)
                  Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Container(
                        width: 22,
                        height: 20.0 + (bars[i] * 18),
                        decoration: BoxDecoration(
                          color: AppColors.blue.withValues(alpha: .82),
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'][i],
                        style: AppText.tiny,
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ActivityTile extends StatelessWidget {
  const ActivityTile({
    super.key,
    required this.title,
    required this.subtitle,
    required this.time,
    required this.icon,
    required this.color,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final String? time;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: GlassCard(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              IconBubble(icon: icon, color: color, size: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(subtitle, style: AppText.muted),
                  ],
                ),
              ),
              Text(
                formatDateTime(time),
                style: AppText.tiny,
                textAlign: TextAlign.right,
              ),
              if (onTap != null) ...[
                const SizedBox(width: 6),
                Icon(
                  Icons.chevron_right_rounded,
                  color: Colors.white.withValues(alpha: .55),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class NoticeCard extends StatelessWidget {
  const NoticeCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          IconBubble(icon: icon, color: color, size: 42),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(subtitle, style: AppText.muted),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Kartu pengumuman klasik — delegasi ke bubble chat.
class AnnouncementCard extends StatelessWidget {
  const AnnouncementCard({super.key, required this.item, this.onTap});

  final Map<String, dynamic> item;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return AnnouncementBubble(item: item, onTap: onTap);
  }
}

/// Preview foto profil layar penuh (pinch-zoom).
void showMhsProfilePhotoPreview(BuildContext context, String photoUrl) {
  if (photoUrl.trim().isEmpty) return;
  showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.85),
    builder: (ctx) {
      return Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(16),
        child: Stack(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: InteractiveViewer(
                minScale: 0.8,
                maxScale: 4.0,
                child: Image.network(
                  photoUrl,
                  fit: BoxFit.contain,
                  errorBuilder: (_, __, ___) => Container(
                    color: Colors.black,
                    padding: const EdgeInsets.all(20),
                    child: const Text(
                      'Foto profil tidak dapat dimuat.',
                      style: TextStyle(color: Colors.white),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton(
                tooltip: 'Tutup',
                onPressed: () => Navigator.of(ctx).pop(),
                icon: const Icon(Icons.close_rounded, color: Colors.white),
              ),
            ),
          ],
        ),
      );
    },
  );
}

class ProfileHeroCard extends StatelessWidget {
  const ProfileHeroCard({
    super.key,
    required this.user,
    required this.onPickPhoto,
  });

  final Map<String, dynamic> user;
  final VoidCallback onPickPhoto;

  @override
  Widget build(BuildContext context) {
    final displayName = formatDisplayName(user['nama_lengkap']?.toString());
    final photoUrl = user['profile_photo_url']?.toString();
    final initial = displayName.trim().isNotEmpty
        ? displayName.trim()[0].toUpperCase()
        : 'M';

    return GlassCard(
      variant: 3,
      accent: AppColors.primary,
      child: Column(
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              InkWell(
                borderRadius: BorderRadius.circular(60),
                onTap: (photoUrl != null && photoUrl.isNotEmpty)
                    ? () => showMhsProfilePhotoPreview(context, photoUrl)
                    : null,
                onLongPress: (photoUrl != null && photoUrl.isNotEmpty)
                    ? () => showMhsProfilePhotoPreview(context, photoUrl)
                    : null,
                child: CircleAvatar(
                  radius: 52,
                  backgroundColor: AppColors.violet.withValues(alpha: .18),
                  backgroundImage: photoUrl != null && photoUrl.isNotEmpty
                      ? NetworkImage(photoUrl)
                      : null,
                  child: photoUrl == null || photoUrl.isEmpty
                      ? Text(
                          initial,
                          style: const TextStyle(
                            color: AppColors.primary,
                            fontSize: 42,
                            fontWeight: FontWeight.w900,
                          ),
                        )
                      : null,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            formatDisplayName(user['nama_lengkap']?.toString()),
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                mhsIsAccountActive(user)
                    ? 'Mahasiswa Aktif'
                    : (user['status_mahasiswa']?.toString() ??
                          'Menunggu verifikasi'),
                style: AppText.muted.copyWith(fontWeight: FontWeight.w700),
              ),
              if (mhsIsAccountActive(user)) ...[
                const SizedBox(width: 8),
                const Icon(
                  Icons.check_circle,
                  color: AppColors.success,
                  size: 22,
                ),
              ],
            ],
          ),
          const SizedBox(height: 14),
          SecondaryButton(
            label: 'Ganti Foto',
            icon: Icons.photo_camera_outlined,
            onPressed: onPickPhoto,
          ),
        ],
      ),
    );
  }
}

class ProfileSection extends StatelessWidget {
  const ProfileSection({
    super.key,
    required this.title,
    required this.children,
  });

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(4, 4, 4, 10),
          child: Text(
            title,
            style: AppText.tiny.copyWith(
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
              color: AppColors.skyDark,
            ),
          ),
        ),
        Container(
          decoration: AppCardDecoration.box(context, radius: 16),
          clipBehavior: Clip.antiAlias,
          child: Column(
            children: [
              for (var i = 0; i < children.length; i++) ...[
                if (i > 0)
                  const Divider(
                    height: 1,
                    thickness: 1,
                    indent: 16,
                    endIndent: 16,
                    color: AppColors.skyLight,
                  ),
                children[i],
              ],
            ],
          ),
        ),
        const SizedBox(height: 18),
      ],
    );
  }
}

class ProfileRow extends StatelessWidget {
  const ProfileRow({
    super.key,
    required this.label,
    required this.value,
    this.trailing,
  });

  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      child: Row(
        children: [
          Expanded(flex: 2, child: Text(label, style: AppText.label)),
          const SizedBox(width: 10),
          Flexible(
            flex: 3,
            child: Text(
              value,
              textAlign: TextAlign.right,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
            ),
          ),
          if (trailing != null) ...[const SizedBox(width: 6), trailing!],
        ],
      ),
    );
  }
}

/// Baris profil untuk teks panjang (mis. nama tim PBL).
class ProfileMultilineRow extends StatelessWidget {
  const ProfileMultilineRow({
    super.key,
    required this.label,
    required this.value,
    this.icon = Icons.groups_2_outlined,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(flex: 2, child: Text(label, style: AppText.label)),
          const SizedBox(width: 10),
          Flexible(
            flex: 3,
            child: Text(
              value,
              textAlign: TextAlign.right,
              maxLines: 3,
              softWrap: true,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
            ),
          ),
        ],
      ),
    );
  }
}

class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.padding,
    this.accent,
    this.variant = 0,
  });

  final Widget child;
  final EdgeInsetsGeometry? padding;

  /// Border & glow tint — coral, mint, violet, amber, …
  final Color? accent;

  /// Rotasi warna kartu (0–3) — disimpan untuk kompatibilitas API widget.
  final int variant;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding ?? const EdgeInsets.all(16),
      decoration: AppCardDecoration.box(context, radius: 22),
      child: child,
    );
  }
}

class IconBubble extends StatelessWidget {
  const IconBubble({
    super.key,
    required this.icon,
    required this.color,
    this.size = 46,
  });

  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .16),
        borderRadius: BorderRadius.circular(15),
      ),
      child: Icon(icon, color: color),
    );
  }
}

class BadgeIcon extends StatelessWidget {
  const BadgeIcon({super.key, required this.icon, required this.count});

  final IconData icon;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Icon(icon),
        if (count > 0)
          Positioned(
            right: -7,
            top: -7,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: AppColors.rose,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: AppColors.card, width: 1.5),
              ),
              child: Text(
                count > 99 ? '99+' : count.toString(),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle({super.key, required this.title, this.action});

  final String title;
  final String? action;

  @override
  Widget build(BuildContext context) {
    final onSurface = AppColors.onSurfaceOf(context);
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: onSurface,
            ),
          ),
        ),
        if (action != null) Text(action!, style: AppText.tiny),
      ],
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Text(text, style: AppText.muted, textAlign: TextAlign.center),
        ),
      ),
    );
  }
}

class InfoStripTone {
  InfoStripTone._();
  static const info = 'info';
  static const success = 'success';
  static const error = 'error';
}

class ServerConnectionBanner extends StatelessWidget {
  const ServerConnectionBanner({
    super.key,
    required this.checking,
    required this.reachable,
    required this.onRetry,
  });

  final bool checking;
  final bool? reachable;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    if (checking) {
      return InfoStrip(
        icon: Icons.sync_rounded,
        text: 'Menghubungkan ke server SIMALAS…',
        tone: InfoStripTone.info,
      );
    }
    if (reachable == true) {
      return InfoStrip(
        icon: Icons.cloud_done_outlined,
        text: 'Terhubung ke server SIMALAS.',
        tone: InfoStripTone.success,
      );
    }
    if (reachable == false) {
      return InfoStrip(
        icon: Icons.cloud_off_outlined,
        text:
            'Server SIMALAS tidak terjangkau. Periksa internet atau coba lagi nanti.',
        tone: InfoStripTone.error,
        actionLabel: 'Coba lagi',
        onAction: onRetry,
      );
    }
    return const SizedBox.shrink();
  }
}

class InfoStrip extends StatelessWidget {
  const InfoStrip({
    super.key,
    required this.icon,
    required this.text,
    this.tone = InfoStripTone.info,
    this.actionLabel,
    this.onAction,
    this.centered = false,
  });

  final IconData icon;
  final String text;
  final String tone;
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool centered;

  @override
  Widget build(BuildContext context) {
    final Color accent;
    final Color bg;
    final Color border;
    switch (tone) {
      case InfoStripTone.success:
        accent = const Color(0xFF16A34A);
        bg = const Color(0xFF052E16).withValues(alpha: 0.55);
        border = accent.withValues(alpha: 0.35);
      case InfoStripTone.error:
        accent = const Color(0xFFEF4444);
        bg = const Color(0xFF450A0A).withValues(alpha: 0.45);
        border = accent.withValues(alpha: 0.35);
      default:
        // Info profesional (bukan amber kotor).
        accent = const Color(0xFF38BDF8);
        bg = const Color(0xFF0C4A6E).withValues(alpha: 0.42);
        border = const Color(0xFF38BDF8).withValues(alpha: 0.28);
    }

    final hasAction = actionLabel != null && onAction != null;
    final textStyle = TextStyle(
      fontSize: 12.5,
      height: 1.45,
      fontWeight: FontWeight.w600,
      color: Colors.white.withValues(alpha: 0.88),
    );

    return Container(
      padding: EdgeInsets.fromLTRB(centered ? 12 : 0, 12, 12, 12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (!centered)
            Container(
              width: 4,
              height: 44,
              margin: const EdgeInsets.only(right: 12),
              decoration: BoxDecoration(
                color: accent,
                borderRadius: const BorderRadius.horizontal(
                  right: Radius.circular(4),
                ),
              ),
            ),
          Expanded(
            child: centered && !hasAction
                ? Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Icon(icon, color: accent, size: 20),
                      const SizedBox(width: 10),
                      Flexible(
                        child: Text(
                          text,
                          textAlign: TextAlign.center,
                          style: textStyle,
                        ),
                      ),
                    ],
                  )
                : Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(icon, color: accent, size: 20),
                      const SizedBox(width: 10),
                      Expanded(child: Text(text, style: textStyle)),
                      if (hasAction) ...[
                        const SizedBox(width: 8),
                        TextButton(
                          onPressed: onAction,
                          style: TextButton.styleFrom(
                            foregroundColor: accent,
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                          ),
                          child: Text(
                            actionLabel!,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

/// Field email dengan centang/silang setelah diisi (validasi format + domain).
class AuthFieldTheme {
  AuthFieldTheme._();

  static const fill = Color(0xFF121212);
  static const border = Color(0xFF333333);
  static const radius = 16.0;

  static OutlineInputBorder outline({Color? color, double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(radius),
      borderSide: BorderSide(color: color ?? border, width: width),
    );
  }

  static InputDecoration base({
    String? hint,
    Widget? prefixIcon,
    Widget? suffixIcon,
    Color? fillColor,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: TextStyle(color: AppColors.muted.withValues(alpha: 0.85)),
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: fillColor ?? fill,
      border: outline(),
      enabledBorder: outline(),
      focusedBorder: outline(color: AppColors.primary, width: 1.5),
    );
  }
}

class AppEmailField extends StatefulWidget {
  const AppEmailField({
    super.key,
    required this.controller,
    required this.hint,
  });

  final TextEditingController controller;
  final String hint;

  @override
  State<AppEmailField> createState() => _AppEmailFieldState();
}

class _AppEmailFieldState extends State<AppEmailField> {
  final FocusNode _focus = FocusNode();
  bool _showStatus = false;
  bool? _isValid;

  @override
  void initState() {
    super.initState();
    _focus.addListener(_onFocusChange);
    widget.controller.addListener(_onTextChanged);
  }

  @override
  void dispose() {
    _focus.removeListener(_onFocusChange);
    widget.controller.removeListener(_onTextChanged);
    _focus.dispose();
    super.dispose();
  }

  void _runValidation() {
    final text = widget.controller.text.trim();
    if (text.isEmpty) {
      setState(() {
        _showStatus = false;
        _isValid = null;
      });
      return;
    }
    setState(() {
      _showStatus = true;
      _isValid = validateStudentEmail(text).isValid;
    });
  }

  void _onFocusChange() {
    if (!_focus.hasFocus && widget.controller.text.trim().isNotEmpty) {
      _runValidation();
    }
  }

  void _onTextChanged() {
    final text = widget.controller.text.trim();
    if (text.isEmpty) {
      setState(() {
        _showStatus = false;
        _isValid = null;
      });
      return;
    }
    _runValidation();
  }

  @override
  Widget build(BuildContext context) {
    Color borderColor = AuthFieldTheme.border;
    if (_showStatus && _isValid != null) {
      borderColor = _isValid!
          ? AppColors.success.withValues(alpha: 0.65)
          : AppColors.rose.withValues(alpha: 0.65);
    }

    Widget? suffix;
    if (_showStatus && _isValid != null) {
      suffix = Padding(
        padding: const EdgeInsets.only(right: 4),
        child: Icon(
          _isValid! ? Icons.check_circle_rounded : Icons.cancel_rounded,
          color: _isValid! ? AppColors.success : AppColors.rose,
          size: 22,
        ),
      );
    }

    return TextField(
      controller: widget.controller,
      focusNode: _focus,
      keyboardType: TextInputType.emailAddress,
      autocorrect: false,
      style: const TextStyle(color: AppColors.textPrimary),
      onEditingComplete: () => _focus.unfocus(),
      decoration:
          AuthFieldTheme.base(
            hint: widget.hint,
            prefixIcon: Icon(Icons.mail_outline, color: AppColors.muted),
            suffixIcon: suffix,
          ).copyWith(
            enabledBorder: AuthFieldTheme.outline(color: borderColor),
            focusedBorder: AuthFieldTheme.outline(
              color: _showStatus && _isValid == false
                  ? AppColors.rose
                  : AppColors.primary,
              width: 1.5,
            ),
          ),
    );
  }
}

class AppTextField extends StatefulWidget {
  const AppTextField({
    super.key,
    required this.controller,
    required this.hint,
    required this.icon,
    this.obscureText = false,
    this.keyboardType,
    this.readOnly = false,
    this.enabled = true,
    this.inputFormatters,
  });

  final TextEditingController controller;
  final String hint;
  final IconData icon;

  /// Field password: teks disembunyikan + ikon mata untuk tampil/sembunyikan.
  final bool obscureText;
  final TextInputType? keyboardType;
  final bool readOnly;
  final bool enabled;
  final List<TextInputFormatter>? inputFormatters;

  @override
  State<AppTextField> createState() => _AppTextFieldState();
}

class _AppTextFieldState extends State<AppTextField> {
  late bool _hidden;

  @override
  void initState() {
    super.initState();
    _hidden = widget.obscureText;
  }

  @override
  void didUpdateWidget(covariant AppTextField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!oldWidget.obscureText && widget.obscureText) {
      _hidden = true;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isPassword = widget.obscureText;

    return TextField(
      controller: widget.controller,
      readOnly: widget.readOnly,
      enabled: widget.enabled,
      obscureText: isPassword ? _hidden : false,
      keyboardType: isPassword
          ? TextInputType.visiblePassword
          : widget.keyboardType,
      inputFormatters: widget.inputFormatters,
      autocorrect: false,
      enableSuggestions: false,
      style: const TextStyle(color: AppColors.textPrimary),
      decoration: AuthFieldTheme.base(
        hint: widget.hint,
        prefixIcon: Icon(widget.icon, color: AppColors.muted),
        suffixIcon: isPassword
            ? IconButton(
                tooltip: _hidden
                    ? 'Tampilkan password'
                    : 'Sembunyikan password',
                onPressed: () => setState(() => _hidden = !_hidden),
                icon: Icon(
                  _hidden
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                  color: AppColors.muted,
                ),
              )
            : null,
      ),
    );
  }
}

class AppDropdown extends StatelessWidget {
  const AppDropdown({
    super.key,
    required this.value,
    required this.items,
    required this.icon,
    required this.onChanged,
    this.labelBuilder,
    this.placeholder = 'Pilih',
  });

  final String value;
  final List<String> items;
  final IconData icon;
  final ValueChanged<String> onChanged;
  final String Function(String value)? labelBuilder;
  final String placeholder;

  @override
  Widget build(BuildContext context) {
    // Jangan duplikat placeholder — Flutter menuntut tepat 1 item dengan value terpilih.
    final optionValues = items
        .where((item) => item.isNotEmpty && item != kFormPilih)
        .toSet()
        .toList();
    final selectedValue =
        value.isNotEmpty &&
            value != kFormPilih &&
            optionValues.contains(value)
        ? value
        : kFormPilih;
    final isPlaceholder = selectedValue == kFormPilih;

    return DropdownButtonFormField<String>(
      key: ValueKey('dd-$selectedValue-${optionValues.join("|")}'),
      initialValue: selectedValue,
      isExpanded: true,
      style: const TextStyle(color: AppColors.textPrimary),
      dropdownColor: AppColors.cardWarm,
      decoration: InputDecoration(
        prefixIcon: Icon(icon, color: AppColors.muted),
        filled: true,
        fillColor: isPlaceholder
            ? AppColors.amber.withValues(alpha: 0.08)
            : AuthFieldTheme.fill,
        border: AuthFieldTheme.outline(
          color: isPlaceholder
              ? AppColors.amber.withValues(alpha: 0.35)
              : AuthFieldTheme.border,
        ),
        enabledBorder: AuthFieldTheme.outline(
          color: isPlaceholder
              ? AppColors.amber.withValues(alpha: 0.35)
              : AuthFieldTheme.border,
        ),
        focusedBorder: AuthFieldTheme.outline(
          color: AppColors.primary,
          width: 1.5,
        ),
      ),
      items: [
        DropdownMenuItem<String>(
          value: kFormPilih,
          enabled: false,
          child: Text(
            placeholder,
            style: const TextStyle(
              color: AppColors.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        ...optionValues.map(
          (item) => DropdownMenuItem<String>(
            value: item,
            child: Text(labelBuilder?.call(item) ?? item),
          ),
        ),
      ],
      onChanged: (picked) {
        if (picked == null || isFormPilih(picked)) return;
        onChanged(picked);
      },
    );
  }
}

class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    required this.icon,
    required this.onPressed,
    this.loading = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final enabled = !loading && onPressed != null;
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: FilledButton.icon(
        onPressed: enabled ? onPressed : null,
        icon: loading
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Icon(icon),
        label: Text(label),
        style: FilledButton.styleFrom(
          backgroundColor: enabled ? AppColors.primary : AppColors.muted,
          foregroundColor: Colors.white,
          disabledBackgroundColor: AppColors.muted.withValues(alpha: 0.45),
          disabledForegroundColor: Colors.white.withValues(alpha: 0.65),
          elevation: enabled ? 4 : 0,
          shadowColor: AppColors.primary.withValues(alpha: 0.45),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }
}

class SecondaryButton extends StatelessWidget {
  const SecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.danger = false,
  });

  final String label;
  final VoidCallback onPressed;
  final IconData? icon;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? AppColors.rose : AppColors.secondary;
    return SizedBox(
      height: 50,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon ?? Icons.arrow_forward_rounded),
        label: Text(label),
        style: OutlinedButton.styleFrom(
          foregroundColor: color,
          side: BorderSide(color: color.withValues(alpha: .5)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }
}

String formatDateTime(String? raw) {
  if (raw == null || raw.isEmpty) return '-';
  try {
    final normalized = raw.contains('T') ? raw : raw.replaceFirst(' ', 'T');
    final date = DateTime.parse(normalized).toLocal();
    return DateFormat('dd MMM\nHH:mm', 'id_ID').format(date);
  } catch (_) {
    return raw;
  }
}

/// Tanggal saja, bulan lengkap (contoh: 02 Juli 2025). Tanpa jam 00:00.
String formatDateFull(String? raw) {
  if (raw == null || raw.isEmpty) return '-';
  try {
    final normalized = raw.contains('T') ? raw : raw.replaceFirst(' ', 'T');
    final date = DateTime.parse(normalized).toLocal();
    return DateFormat('dd MMMM yyyy', 'id_ID').format(date);
  } catch (_) {
    return raw;
  }
}

/// Tanggal singkat dengan bulan lengkap (contoh: 02 Juli).
String formatDateShortFull(String? raw) {
  if (raw == null || raw.isEmpty) return '-';
  try {
    final normalized = raw.contains('T') ? raw : raw.replaceFirst(' ', 'T');
    final date = DateTime.parse(normalized).toLocal();
    return DateFormat('dd MMMM', 'id_ID').format(date);
  } catch (_) {
    return raw;
  }
}

String formatTimeWib(String? raw) {
  final parsed = mhsParseDate(raw);
  if (parsed == null) return '-';
  return '${DateFormat('HH:mm', 'id_ID').format(parsed)} WIB';
}

class AppCardDecoration {
  static const borderColor = Color(0xFF333333);

  static List<BoxShadow> get shadow => [
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.35),
      blurRadius: 20,
      offset: const Offset(0, 8),
    ),
  ];

  static List<BoxShadow> get lightShadow => [
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.06),
      blurRadius: 14,
      offset: const Offset(0, 6),
    ),
  ];

  static BoxDecoration box(
    BuildContext context, {
    double radius = 20,
    Color? color,
    List<BoxShadow>? boxShadow,
  }) {
    final light = AppColors.isLight(context);
    return BoxDecoration(
      color: color ?? AppColors.surfaceOf(context),
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: AppColors.borderOf(context)),
      boxShadow: boxShadow ?? (light ? lightShadow : shadow),
    );
  }
}

String formatDisplayName(String? raw) {
  final trimmed = raw?.trim() ?? '';
  if (trimmed.isEmpty) return 'Mahasiswa';
  return trimmed
      .split(RegExp(r'\s+'))
      .map((word) {
        if (word.isEmpty) return word;
        if (word.length == 1) return word.toUpperCase();
        return '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}';
      })
      .join(' ');
}

class AppColors {
  static const bg = Color(0xFF121212);
  static const card = Color(0xFF1E1E1E);
  static const cardWarm = Color(0xFF242424);
  static const softPanel = Color(0xFF2A2A2A);
  static const navBar = Color(0xFF1A1A1A);

  /// Aksen utama — cyan/biru (selaras header GoPay).
  static const primary = Color(0xFF00AED6);
  static const secondary = Color(0xFF0EA5E9);
  static const skyDark = Color(0xFF38BDF8);
  static const skyLight = Color(0xFF164E63);

  /// Status kehadiran (badge/tabel).
  static const success = Color(0xFF22C55E);
  static const amber = Color(0xFFF59E0B);
  static const rose = Color(0xFFEF4444);
  static const chartDot = Color(0xFFEF4444);
  static const notifYellow = Color(0xFFF59E0B);

  static const blue = primary;
  static const teal = success;
  static const violet = secondary;
  static const purple = secondary;
  static const lime = success;

  static const muted = Color(0xFF9E9E9E);
  static const textPrimary = Color(0xFFE8E8E8);
  static const textSecondary = Color(0xFFBDBDBD);
  static const navy = Color(0xFF1E3A8A);
  static const navyDark = Color(0xFF0F172A);

  /// Warna adaptif untuk mode terang (MaterialApp themeMode).
  static const bgLight = Color(0xFFF8FAFC);
  static const cardLight = Color(0xFFFFFFFF);
  static const textPrimaryLight = Color(0xFF0F172A);
  static const mutedLight = Color(0xFF475569);
  static const softPanelLight = Color(0xFFF1F5F9);
  static const borderLight = Color(0xFFE2E8F0);

  /// Teks utama / sekunder mengikuti [ThemeStore.light] (tanpa context).
  static Color get ink => ThemeStore.light ? textPrimaryLight : textPrimary;
  static Color get fade => ThemeStore.light ? mutedLight : muted;
  static Color get panel => ThemeStore.light ? softPanelLight : softPanel;
  static Color get line =>
      ThemeStore.light ? borderLight : const Color(0xFF333333);

  /// Status badge: lebih gelap di mode terang agar kontras di atas putih.
  static Color get successInk =>
      ThemeStore.light ? const Color(0xFF15803D) : success;
  static Color get amberInk =>
      ThemeStore.light ? const Color(0xFFB45309) : amber;
  static Color get roseInk =>
      ThemeStore.light ? const Color(0xFFB91C1C) : rose;

  static bool isLight(BuildContext context) =>
      Theme.of(context).brightness == Brightness.light;

  static Color surfaceOf(BuildContext context) =>
      isLight(context) ? cardLight : card;

  static Color pageOf(BuildContext context) =>
      isLight(context) ? bgLight : bg;

  static Color onSurfaceOf(BuildContext context) =>
      isLight(context) ? textPrimaryLight : textPrimary;

  static Color mutedOf(BuildContext context) =>
      isLight(context) ? mutedLight : muted;

  static Color softPanelOf(BuildContext context) =>
      isLight(context) ? softPanelLight : softPanel;

  static Color borderOf(BuildContext context) =>
      isLight(context) ? borderLight : const Color(0xFF333333);
}

/// Tema gelap global — dipasang di [MaterialApp.theme].
class AppTheme {
  AppTheme._();

  static const double cardRadius = 18;

  static ThemeData get dark {
    const primary = AppColors.primary;
    const onSurface = AppColors.textPrimary;
    const muted = AppColors.muted;

    final colorScheme = const ColorScheme.dark(
      primary: primary,
      onPrimary: Colors.white,
      secondary: AppColors.secondary,
      onSecondary: Colors.white,
      surface: AppColors.card,
      onSurface: onSurface,
      surfaceContainerHighest: AppColors.softPanel,
      error: AppColors.rose,
      onError: Colors.white,
      outline: Color(0xFF404040),
    );

    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      scaffoldBackgroundColor: AppColors.bg,
      primaryColor: primary,
      colorScheme: colorScheme,
      dividerColor: const Color(0xFF333333),
      iconTheme: const IconThemeData(color: onSurface),
    );

    final textTheme = base.textTheme
        .apply(bodyColor: onSurface, displayColor: onSurface)
        .copyWith(
          bodyLarge: const TextStyle(color: onSurface, height: 1.45),
          bodyMedium: const TextStyle(color: onSurface, height: 1.4),
          bodySmall: const TextStyle(color: muted, height: 1.35),
          titleLarge: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w900,
            fontSize: 22,
          ),
          titleMedium: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
          titleSmall: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w700,
            fontSize: 14,
          ),
          labelLarge: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w700,
          ),
          labelMedium: const TextStyle(
            color: muted,
            fontWeight: FontWeight.w600,
          ),
        );

    return base.copyWith(
      textTheme: textTheme,
      cardTheme: CardThemeData(
        color: AppColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(cardRadius),
          side: const BorderSide(color: AppCardDecoration.borderColor),
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.bg,
        foregroundColor: onSurface,
        elevation: 0,
        centerTitle: false,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.cardWarm,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        titleTextStyle: textTheme.titleMedium,
        contentTextStyle: textTheme.bodyMedium,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.cardWarm,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.softPanel,
        contentTextStyle: textTheme.bodyMedium,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.softPanel,
        labelStyle: const TextStyle(color: muted),
        hintStyle: TextStyle(color: muted.withValues(alpha: 0.85)),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppCardDecoration.borderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppCardDecoration.borderColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: primary, width: 1.5),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.softPanel,
        labelStyle: const TextStyle(color: onSurface, fontSize: 12),
        side: const BorderSide(color: AppCardDecoration.borderColor),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      dividerTheme: const DividerThemeData(
        color: Color(0xFF333333),
        thickness: 1,
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.navBar,
        selectedItemColor: primary,
        unselectedItemColor: muted,
        type: BottomNavigationBarType.fixed,
        elevation: 0,
        showUnselectedLabels: true,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.navBar,
        indicatorColor: primary.withValues(alpha: 0.18),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 11,
            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            color: selected ? primary : muted,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(color: selected ? primary : muted, size: 24);
        }),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          side: const BorderSide(color: AppCardDecoration.borderColor),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: primary),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: primary),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: primary,
        foregroundColor: Colors.white,
      ),
    );
  }

  static ThemeData get light {
    const primary = AppColors.primary;
    const onSurface = Color(0xFF0F172A);
    const muted = Color(0xFF475569);

    final colorScheme = const ColorScheme.light(
      primary: primary,
      onPrimary: Colors.white,
      secondary: AppColors.secondary,
      surface: Color(0xFFFFFFFF),
      onSurface: onSurface,
      error: AppColors.rose,
      outline: Color(0xFFCBD5E1),
    );

    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      scaffoldBackgroundColor: const Color(0xFFF8FAFC),
      colorScheme: colorScheme,
      iconTheme: const IconThemeData(color: onSurface),
    );

    final textTheme = base.textTheme
        .apply(bodyColor: onSurface, displayColor: onSurface)
        .copyWith(
          bodyLarge: const TextStyle(color: onSurface, height: 1.45),
          bodyMedium: const TextStyle(color: onSurface, height: 1.4),
          bodySmall: const TextStyle(color: muted, height: 1.35),
          titleLarge: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w900,
            fontSize: 22,
          ),
          titleMedium: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
          titleSmall: const TextStyle(
            color: onSurface,
            fontWeight: FontWeight.w700,
            fontSize: 14,
          ),
        );

    return base.copyWith(
      textTheme: textTheme,
      primaryTextTheme: textTheme,
      appBarTheme: const AppBarTheme(
        backgroundColor: Color(0xFFF8FAFC),
        foregroundColor: onSurface,
        elevation: 0,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: const Color(0xFFFFFFFF),
        contentTextStyle: textTheme.bodyMedium,
        titleTextStyle: textTheme.titleMedium,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.softPanelLight,
        labelStyle: const TextStyle(color: onSurface, fontSize: 12),
        secondaryLabelStyle: const TextStyle(color: onSurface, fontSize: 12),
        side: const BorderSide(color: AppColors.borderLight),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: Color(0xFFFFFFFF),
        selectedItemColor: primary,
        unselectedItemColor: muted,
        type: BottomNavigationBarType.fixed,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: const Color(0xFFFFFFFF),
        indicatorColor: primary.withValues(alpha: 0.15),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 11,
            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            color: selected ? primary : muted,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(color: selected ? primary : muted, size: 24);
        }),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: AppColors.borderLight),
        ),
      ),
    );
  }
}

class AppGradients {
  /// Auth/landing: lebih terang di area logo agar wordmark SIMALAS terbaca.
  static const auth = LinearGradient(
    colors: [Color(0xFF1E3A5F), Color(0xFF152A45), Color(0xFF0F1C2E)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  static const main = LinearGradient(
    colors: [Color(0xFF121212), Color(0xFF121212)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  static const header = LinearGradient(
    colors: [Color(0xFF38BDF8), Color(0xFF0EA5E9)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const sunset = LinearGradient(
    colors: [Color(0xFF38BDF8), Color(0xFF0EA5E9)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const aurora = LinearGradient(
    colors: [Color(0xFF38BDF8), Color(0xFF0EA5E9)],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );
}

class AppText {
  static TextStyle get label => TextStyle(
    color: AppColors.fade,
    fontSize: 12,
    fontWeight: FontWeight.w800,
    letterSpacing: .3,
  );
  static TextStyle get muted => TextStyle(color: AppColors.fade, height: 1.4);
  static TextStyle get tiny => TextStyle(
    color: AppColors.fade,
    fontSize: 11,
    height: 1.3,
  );
}
