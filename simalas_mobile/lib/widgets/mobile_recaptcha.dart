import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'mobile_image_captcha.dart';

export 'mobile_image_captcha.dart' show MobileImageCaptchaField, MobileImageCaptchaFieldState;

/// Google reCAPTCHA WebView hanya didukung di Android & iOS (bukan Windows/Linux desktop).
bool get supportsInlineRecaptchaWebView {
  if (kIsWeb) return false;
  try {
    return Platform.isAndroid || Platform.isIOS;
  } catch (_) {
    return false;
  }
}

bool shouldShowRecaptchaWebView({
  required bool recaptchaEnabled,
  required String? recaptchaSiteKey,
  required String? recaptchaPageUrl,
}) {
  // WebView /mobile/recaptcha tidak stabil di semua perangkat — checkbox + mobile_robot_ack.
  return false;
}

/// Siap kirim setelah CAPTCHA gambar (HP) atau checkbox (dev desktop).
bool isMobileVerificationComplete({
  required bool recaptchaEnabled,
  required String? recaptchaSiteKey,
  required String? recaptchaPageUrl,
  required String? recaptchaToken,
  required bool robotAck,
  String? captchaToken,
  String? captchaAnswer,
}) {
  if (shouldShowRecaptchaWebView(
    recaptchaEnabled: recaptchaEnabled,
    recaptchaSiteKey: recaptchaSiteKey,
    recaptchaPageUrl: recaptchaPageUrl,
  )) {
    return recaptchaToken != null && recaptchaToken.isNotEmpty;
  }
  if (recaptchaEnabled) {
    final digits = (captchaAnswer ?? '').replaceAll(RegExp(r'\D'), '');
    if (captchaToken != null &&
        captchaToken.isNotEmpty &&
        digits.length == 6) {
      return true;
    }
    return robotAck;
  }
  return true;
}

// Warna selaras dengan AppColors di main.dart (file terpisah agar tidak circular import).
abstract final class _VerifyTheme {
  static const card = Color(0xFFFFFFFF);
  static const softPanel = Color(0xFFF0F9FF);
  static const border = Color(0xFFD8E2EE);
  static const divider = Color(0xFFE8EEF4);
  static const textPrimary = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);
  static const primary = Color(0xFF38BDF8);
  static const skyDark = Color(0xFF0284C7);
  static const success = Color(0xFF16A34A);
  static const googleBlue = Color(0xFF4285F4);
}

/// Verifikasi registrasi / lupa password: CAPTCHA gambar, fallback checkbox jika gagal.
class MobileVerificationField extends StatefulWidget {
  const MobileVerificationField({
    super.key,
    required this.recaptchaEnabled,
    required this.recaptchaSiteKey,
    required this.recaptchaPageUrl,
    required this.onRecaptchaToken,
    required this.robotAck,
    required this.onRobotAckChanged,
    this.apiBaseUrl,
    this.onCaptchaChanged,
    this.imageCaptchaKey,
  });

  final bool recaptchaEnabled;
  final String? recaptchaSiteKey;
  final String? recaptchaPageUrl;
  final ValueChanged<String?> onRecaptchaToken;
  final bool robotAck;
  final ValueChanged<bool> onRobotAckChanged;
  final String? apiBaseUrl;
  final void Function(String? token, String answer)? onCaptchaChanged;
  final GlobalKey<MobileImageCaptchaFieldState>? imageCaptchaKey;

  @override
  State<MobileVerificationField> createState() =>
      _MobileVerificationFieldState();
}

class _MobileVerificationFieldState extends State<MobileVerificationField> {
  bool _useRobotFallback = false;

  @override
  Widget build(BuildContext context) {
    if (!widget.recaptchaEnabled) {
      return const SizedBox.shrink();
    }

    final useWebView = shouldShowRecaptchaWebView(
      recaptchaEnabled: widget.recaptchaEnabled,
      recaptchaSiteKey: widget.recaptchaSiteKey,
      recaptchaPageUrl: widget.recaptchaPageUrl,
    );
    final useImageCaptcha = !useWebView &&
        !_useRobotFallback &&
        widget.apiBaseUrl != null &&
        widget.onCaptchaChanged != null;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: _VerifyTheme.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _VerifyTheme.border),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: _VerifyTheme.primary.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.verified_user_outlined,
                    color: _VerifyTheme.skyDark,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Verifikasi keamanan',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: _VerifyTheme.textPrimary,
                          letterSpacing: 0.1,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        useWebView
                            ? 'Centang "Saya bukan robot" pada reCAPTCHA Google di bawah.'
                            : (useImageCaptcha
                                ? 'Masukkan angka pada gambar CAPTCHA di bawah.'
                                : 'Centang "Saya bukan robot" untuk melanjutkan.'),
                        style: TextStyle(
                          fontSize: 12,
                          height: 1.35,
                          color: _VerifyTheme.muted.withValues(alpha: 0.95),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, thickness: 1, color: _VerifyTheme.divider),
          Padding(
            padding: const EdgeInsets.all(14),
            child: useWebView
                ? MobileRecaptchaField(
                    siteKey: widget.recaptchaSiteKey!,
                    pageUrl: widget.recaptchaPageUrl,
                    onToken: widget.onRecaptchaToken,
                  )
                : (useImageCaptcha
                      ? MobileImageCaptchaField(
                          key: widget.imageCaptchaKey,
                          apiBaseUrl: widget.apiBaseUrl!,
                          onChanged: widget.onCaptchaChanged!,
                          onUnavailable: () {
                            if (!_useRobotFallback && mounted) {
                              setState(() => _useRobotFallback = true);
                              widget.onCaptchaChanged?.call(null, '');
                            }
                          },
                        )
                      : MobileRobotCheckbox(
                          value: widget.robotAck,
                          onChanged: widget.onRobotAckChanged,
                        )),
          ),
        ],
      ),
    );
  }
}

/// Logo merek reCAPTCHA (approximasi desain resmi).
class RecaptchaBrandBadge extends StatelessWidget {
  const RecaptchaBrandBadge({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final iconSize = compact ? 18.0 : 22.0;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        SizedBox(
          width: iconSize,
          height: iconSize,
          child: CustomPaint(
            painter: _RecaptchaLogoPainter(size: iconSize),
          ),
        ),
        SizedBox(height: compact ? 2 : 4),
        Text(
          'reCAPTCHA',
          style: TextStyle(
            fontSize: compact ? 9 : 10,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF555555),
            letterSpacing: 0.15,
          ),
        ),
        if (!compact) ...[
          const SizedBox(height: 1),
          Text(
            'Privasi · Ketentuan',
            style: TextStyle(
              fontSize: 7.5,
              color: _VerifyTheme.muted.withValues(alpha: 0.85),
              height: 1.1,
            ),
          ),
        ],
      ],
    );
  }
}

class _RecaptchaLogoPainter extends CustomPainter {
  _RecaptchaLogoPainter({required this.size});

  final double size;

  @override
  void paint(Canvas canvas, Size s) {
    final cx = s.width / 2;
    final cy = s.height / 2;
    final r = size * 0.38;
    final stroke = size * 0.11;

    void arc(Color color, double start, double sweep) {
      final paint = Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = stroke
        ..strokeCap = StrokeCap.round;
      canvas.drawArc(
        Rect.fromCircle(center: Offset(cx, cy), radius: r),
        start,
        sweep,
        false,
        paint,
      );
    }

    arc(const Color(0xFF4285F4), -0.4, 2.1);
    arc(const Color(0xFFDB4437), 1.8, 2.1);
    arc(const Color(0xFFF4B400), 4.0, 2.1);
    arc(const Color(0xFF0F9D58), 6.2, 2.1);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Kotak centang bergaya aplikasi (desktop / fallback Windows).
class MobileRobotCheckbox extends StatelessWidget {
  const MobileRobotCheckbox({
    super.key,
    required this.value,
    required this.onChanged,
  });

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final borderColor =
        value ? _VerifyTheme.success.withValues(alpha: 0.55) : _VerifyTheme.border;
    final bgColor = value
        ? _VerifyTheme.success.withValues(alpha: 0.06)
        : _VerifyTheme.softPanel;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => onChanged(!value),
        borderRadius: BorderRadius.circular(14),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: bgColor,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: borderColor, width: 1.2),
          ),
          child: Row(
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                width: 26,
                height: 26,
                decoration: BoxDecoration(
                  color: value ? _VerifyTheme.googleBlue : Colors.white,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(
                    color: value
                        ? _VerifyTheme.googleBlue
                        : const Color(0xFFC1C1C1),
                    width: 1.5,
                  ),
                ),
                child: value
                    ? const Icon(Icons.check_rounded, color: Colors.white, size: 18)
                    : null,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  'Saya bukan robot',
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: _VerifyTheme.textPrimary.withValues(alpha: 0.92),
                  ),
                ),
              ),
              const RecaptchaBrandBadge(compact: true),
            ],
          ),
        ),
      ),
    );
  }
}

/// Widget reCAPTCHA v2 (Android/iOS) — muat halaman resmi dari domain SIMALAS.
class MobileRecaptchaField extends StatefulWidget {
  const MobileRecaptchaField({
    super.key,
    required this.siteKey,
    required this.onToken,
    this.pageUrl,
    this.height = 84,
  });

  final String siteKey;
  final String? pageUrl;
  final ValueChanged<String?> onToken;
  final double height;

  @override
  State<MobileRecaptchaField> createState() => _MobileRecaptchaFieldState();
}

class _MobileRecaptchaFieldState extends State<MobileRecaptchaField> {
  late final WebViewController _controller;
  bool _ready = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(_VerifyTheme.softPanel)
      ..addJavaScriptChannel(
        'RecaptchaChannel',
        onMessageReceived: (msg) {
          final token = msg.message.trim();
          widget.onToken(token.isEmpty ? null : token);
          if (mounted) {
            setState(() => _ready = true);
          }
        },
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (_) {
            if (mounted) setState(() => _ready = true);
          },
          onWebResourceError: (err) {
            if (mounted) {
              setState(() {
                _error = 'Gagal memuat reCAPTCHA. Periksa koneksi internet.';
                _ready = true;
              });
            }
          },
        ),
      );

    final page = widget.pageUrl?.trim();
    if (page != null && page.isNotEmpty) {
      _controller.loadRequest(Uri.parse(page));
    } else {
      _controller.loadHtmlString(_html(widget.siteKey));
    }
  }

  static String _html(String siteKey) => '''
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style>
    * { box-sizing: border-box; }
    html, body {
      margin: 0; padding: 0;
      background: #f0f9ff;
      display: flex; justify-content: flex-start; align-items: center;
      min-height: 72px;
    }
    .g-recaptcha { transform-origin: left center; }
  </style>
</head>
<body>
  <div class="g-recaptcha" data-sitekey="$siteKey" data-callback="onCaptcha"></div>
  <script>
    function onCaptcha(token) {
      if (window.RecaptchaChannel) {
        RecaptchaChannel.postMessage(token || '');
      }
    }
  </script>
</body>
</html>
''';

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: _VerifyTheme.softPanel,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _VerifyTheme.border),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: SizedBox(
          height: widget.height,
          width: double.infinity,
          child: _error != null
              ? Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    _error!,
                    style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 12),
                  ),
                )
              : Stack(
                  alignment: Alignment.centerLeft,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(left: 4),
                      child: WebViewWidget(controller: _controller),
                    ),
                    if (!_ready)
                      const Center(
                        child: SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: _VerifyTheme.skyDark,
                          ),
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}
