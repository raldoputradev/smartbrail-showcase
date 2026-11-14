import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

/// CAPTCHA gambar angka (mirip JKN) — ambil dari API server.
class MobileImageCaptchaField extends StatefulWidget {
  const MobileImageCaptchaField({
    super.key,
    required this.apiBaseUrl,
    required this.onChanged,
    this.onUnavailable,
  });

  final String apiBaseUrl;
  final void Function(String? token, String answer) onChanged;
  /// Dipanggil jika gambar CAPTCHA gagal dimuat (mis. GD PHP mati) — parent bisa fallback.
  final VoidCallback? onUnavailable;

  @override
  State<MobileImageCaptchaField> createState() => MobileImageCaptchaFieldState();
}

class MobileImageCaptchaFieldState extends State<MobileImageCaptchaField> {
  String? _token;
  String? _imageBase64;
  bool _loading = true;
  String? _error;
  int _loadGen = 0;
  final _answerController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _answerController.addListener(_notify);
    _load();
  }

  @override
  void didUpdateWidget(covariant MobileImageCaptchaField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.apiBaseUrl.trim() != widget.apiBaseUrl.trim()) {
      _load();
    }
  }

  @override
  void dispose() {
    _answerController.removeListener(_notify);
    _answerController.dispose();
    super.dispose();
  }

  void _notify() {
    widget.onChanged(_token, _answerController.text.trim());
  }

  /// Dipanggil parent setelah submit CAPTCHA gagal.
  Future<void> reload() => _load();

  Future<void> _load() async {
    final gen = ++_loadGen;
    setState(() {
      _loading = true;
      _error = null;
    });
    widget.onChanged(null, '');
    try {
      var base = widget.apiBaseUrl.trim();
      if (base.endsWith('/')) base = base.substring(0, base.length - 1);
      final uri = Uri.parse('$base/mobile/captcha').replace(
        queryParameters: {'_': DateTime.now().millisecondsSinceEpoch.toString()},
      );
      final response = await http
          .get(
            uri,
            headers: const {
              'Cache-Control': 'no-cache, no-store',
              'Pragma': 'no-cache',
              'Accept': 'application/json',
            },
          )
          .timeout(const Duration(seconds: 12));
      if (gen != _loadGen) return;

      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (decoded['success'] != true) {
        throw Exception(
          decoded['message']?.toString() ?? 'Gagal memuat CAPTCHA',
        );
      }
      final data = decoded['data'] as Map<String, dynamic>? ?? {};
      final token = data['token']?.toString();
      final image = data['image_base64']?.toString();
      if (token == null || token.isEmpty || image == null || image.isEmpty) {
        throw Exception('Respons CAPTCHA tidak lengkap');
      }
      if (!mounted || gen != _loadGen) return;
      setState(() {
        _token = token;
        _imageBase64 = image;
        _answerController.clear();
        _loading = false;
        _error = null;
      });
      _notify();
    } catch (e) {
      if (!mounted || gen != _loadGen) return;
      widget.onChanged(null, '');
      if (widget.onUnavailable != null) {
        widget.onUnavailable!();
        return;
      }
      final serverMsg = e.toString().replaceFirst(RegExp(r'^Exception:\s*'), '');
      setState(() {
        _loading = false;
        _token = null;
        _imageBase64 = null;
        _error = serverMsg.isNotEmpty
            ? serverMsg
            : 'Gambar verifikasi belum tersedia. Periksa koneksi server, lalu muat ulang.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Masukkan 6 angka yang terlihat pada gambar.',
          style: TextStyle(
            fontSize: 12.5,
            height: 1.4,
            fontWeight: FontWeight.w600,
            color: Color(0xFF475569),
          ),
        ),
        const SizedBox(height: 12),
        if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 20),
            child: Center(
              child: SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(strokeWidth: 2.4),
              ),
            ),
          )
        else if (_error != null)
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFCBD5E1)),
            ),
            child: Column(
              children: [
                const Icon(
                  Icons.wifi_off_rounded,
                  color: Color(0xFF64748B),
                  size: 28,
                ),
                const SizedBox(height: 10),
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Color(0xFF334155),
                    fontSize: 13,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: _load,
                    icon: const Icon(Icons.refresh_rounded, size: 18),
                    label: const Text(
                      'Muat ulang',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF0284C7),
                      side: const BorderSide(color: Color(0xFF7DD3FC)),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          )
        else if (_imageBase64 != null) ...[
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.memory(
              base64Decode(_imageBase64!.split(',').last),
              height: 72,
              width: double.infinity,
              fit: BoxFit.cover,
              gaplessPlayback: true,
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _answerController,
            keyboardType: TextInputType.number,
            maxLength: 6,
            style: const TextStyle(
              color: Color(0xFF0F172A),
              letterSpacing: 4,
              fontWeight: FontWeight.w700,
            ),
            decoration: InputDecoration(
              counterText: '',
              hintText: '6 digit pada gambar',
              hintStyle: const TextStyle(
                color: Color(0xFF94A3B8),
                letterSpacing: 0,
              ),
              filled: true,
              fillColor: const Color(0xFFF0F9FF),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Muat ulang gambar'),
            ),
          ),
        ],
      ],
    );
  }
}
