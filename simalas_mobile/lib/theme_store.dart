import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Penyimpanan preferensi tema terang/gelap.
class ThemeStore extends ChangeNotifier {
  static const _key = 'theme_mode_light';

  /// Snapshot global agar [AppText]/[AppColors.ink] ikut tema tanpa BuildContext.
  static bool light = false;

  bool _isLight = false;

  bool get isLight => _isLight;

  ThemeMode get themeMode => _isLight ? ThemeMode.light : ThemeMode.dark;

  void _syncStatic() {
    light = _isLight;
  }

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _isLight = prefs.getBool(_key) ?? false;
    _syncStatic();
    notifyListeners();
  }

  Future<void> setLight(bool value) async {
    if (_isLight == value) return;
    _isLight = value;
    _syncStatic();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_key, value);
    notifyListeners();
  }

  Future<void> toggle() => setLight(!_isLight);
}
