part of '../main.dart';

const _kOnboardingDoneKey = 'mhs_onboarding_intro_done_v1';

Future<bool> mhsIsOnboardingDone() async {
  final prefs = await SharedPreferences.getInstance();
  return prefs.getBool(_kOnboardingDoneKey) ?? false;
}

Future<void> mhsMarkOnboardingDone() async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setBool(_kOnboardingDoneKey, true);
}

class _OnboardSlide {
  const _OnboardSlide({
    required this.title,
    required this.subtitle,
    required this.icon,
    this.lottieAsset,
    this.useBrandLogo = false,
    this.badges = const [],
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final String? lottieAsset;
  final bool useBrandLogo;
  final List<(IconData, String, Color)> badges;
}

/// Intro APK potret — 4 slide native (bukan gambar AI).
class OnboardingIntroPage extends StatefulWidget {
  const OnboardingIntroPage({super.key, required this.onFinished});

  final VoidCallback onFinished;

  @override
  State<OnboardingIntroPage> createState() => _OnboardingIntroPageState();
}

class _OnboardingIntroPageState extends State<OnboardingIntroPage> {
  static const _slides = [
    _OnboardSlide(
      title: 'Smart Lab',
      subtitle: 'Lab pintar untuk absensi sidik jari.',
      icon: Icons.fingerprint_rounded,
      useBrandLogo: true,
    ),
    _OnboardSlide(
      title: 'Absensi Sidik Jari',
      subtitle:
          'Absensi lab lewat mesin sidik jari. Status jelas: hadir, terlambat, atau alfa.',
      icon: Icons.fingerprint_rounded,
      badges: [
        (Icons.check_circle_rounded, 'Hadir', Color(0xFF22C55E)),
        (Icons.schedule_rounded, 'Terlambat', Color(0xFFF59E0B)),
        (Icons.cancel_rounded, 'Alfa', Color(0xFFEF4444)),
      ],
    ),
    _OnboardSlide(
      title: 'Siap Digunakan',
      subtitle: 'Akun aman, data sinkron, akses cepat ke beranda lab.',
      icon: Icons.verified_rounded,
      lottieAsset: 'assets/animations/brand_splash.json',
    ),
  ];

  late final PageController _page;
  int _index = 0;
  Timer? _auto;
  bool _finishing = false;

  @override
  void initState() {
    super.initState();
    // Index besar agar PageView terasa infinite ke kiri/kanan.
    _page = PageController(initialPage: 1000 * _slides.length);
    _index = 0;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _startAuto();
    });
  }

  @override
  void dispose() {
    _auto?.cancel();
    _page.dispose();
    super.dispose();
  }

  void _startAuto() {
    _auto?.cancel();
    if (MediaQuery.maybeOf(context)?.disableAnimations == true) return;
    _auto = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!mounted || _finishing) return;
      _page.nextPage(
        duration: const Duration(milliseconds: 380),
        curve: Curves.easeOutCubic,
      );
    });
  }

  int _slideOf(int page) => page % _slides.length;

  Future<void> _finish() async {
    if (_finishing) return;
    _finishing = true;
    _auto?.cancel();
    await mhsMarkOnboardingDone();
    if (!mounted) return;
    widget.onFinished();
  }

  void _goNext() {
    final logical = _slideOf(_page.page?.round() ?? _page.initialPage);
    if (logical >= _slides.length - 1) {
      _finish();
      return;
    }
    _page.nextPage(
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final last = _index >= _slides.length - 1;

    return Scaffold(
      backgroundColor: const Color(0xFF0B1220),
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: _finish,
                child: Text(
                  'Lewati',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.55),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _page,
                onPageChanged: (page) {
                  setState(() => _index = _slideOf(page));
                  if (!reduceMotion) _startAuto();
                },
                itemBuilder: (context, page) {
                  final slide = _slides[_slideOf(page)];
                  return _OnboardSlideView(
                    slide: slide,
                    reduceMotion: reduceMotion,
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 20),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      for (var i = 0; i < _slides.length; i++)
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 220),
                          margin: const EdgeInsets.symmetric(horizontal: 3),
                          width: i == _index ? 18 : 7,
                          height: 7,
                          decoration: BoxDecoration(
                            color: i == _index
                                ? const Color(0xFF00AED6)
                                : const Color(0xFF3A4A63),
                            borderRadius: BorderRadius.circular(999),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: FilledButton(
                      onPressed: last ? _finish : _goNext,
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF00AED6),
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: Text(
                        last ? 'Mulai Sekarang' : 'Lanjutkan',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                        ),
                      ),
                    ),
                  ),
                  if (last) ...[
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: _finish,
                      child: Text(
                        'Sudah punya akun? Masuk',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.7),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OnboardSlideView extends StatelessWidget {
  const _OnboardSlideView({required this.slide, required this.reduceMotion});

  final _OnboardSlide slide;
  final bool reduceMotion;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 8, 28, 8),
      child: Column(
        children: [
          const Spacer(flex: 1),
          _OnboardHero(
            icon: slide.icon,
            lottieAsset: reduceMotion ? null : slide.lottieAsset,
            useBrandLogo: slide.useBrandLogo,
          ),
          if (slide.badges.isNotEmpty) ...[
            const SizedBox(height: 18),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              alignment: WrapAlignment.center,
              children: [
                for (final b in slide.badges)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: b.$3.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: b.$3.withValues(alpha: 0.35)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(b.$1, size: 16, color: b.$3),
                        const SizedBox(width: 6),
                        Text(
                          b.$2,
                          style: TextStyle(
                            color: b.$3,
                            fontWeight: FontWeight.w800,
                            fontSize: 12.5,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ],
          const Spacer(flex: 1),
          Text(
            slide.title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.w900,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            slide.subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: const Color(0xFFB6C7E0).withValues(alpha: 0.95),
              fontSize: 15,
              height: 1.45,
              fontWeight: FontWeight.w500,
            ),
          ),
          const Spacer(flex: 1),
        ],
      ),
    );
  }
}

class _OnboardHero extends StatelessWidget {
  const _OnboardHero({
    required this.icon,
    this.lottieAsset,
    this.useBrandLogo = false,
  });

  final IconData icon;
  final String? lottieAsset;
  final bool useBrandLogo;

  @override
  Widget build(BuildContext context) {
    final useLottie = lottieAsset != null;

    return Container(
      width: 280,
      height: 280,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: const Color(0xFF0F1B2E),
        border: Border.all(
          color: const Color(0xFF00AED6).withValues(alpha: 0.28),
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF00AED6).withValues(alpha: 0.12),
            blurRadius: 36,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: useLottie
          ? Padding(
              padding: const EdgeInsets.all(28),
              child: Lottie.asset(
                lottieAsset!,
                fit: BoxFit.contain,
                repeat: true,
                errorBuilder: (_, _, _) => _FallbackIcon(icon: icon),
              ),
            )
          : useBrandLogo
          ? Padding(
              padding: const EdgeInsets.all(36),
              child: Image.asset(
                'assets/branding/aiot-simalas-logo.png',
                fit: BoxFit.contain,
                filterQuality: FilterQuality.high,
                errorBuilder: (_, _, _) => _FallbackIcon(icon: icon),
              ),
            )
          : Container(
              width: 132,
              height: 132,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF00AED6).withValues(alpha: 0.12),
                border: Border.all(
                  color: const Color(0xFF00AED6).withValues(alpha: 0.45),
                  width: 1.5,
                ),
              ),
              child: Icon(icon, size: 72, color: const Color(0xFF00AED6)),
            ),
    );
  }
}

class _FallbackIcon extends StatelessWidget {
  const _FallbackIcon({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Icon(icon, size: 96, color: const Color(0xFF00AED6));
  }
}
