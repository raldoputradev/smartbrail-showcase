part of '../main.dart';

/// Landing awal bergaya myBCA: logo kiri + CS AI kanan + panel aksi.
class MobileLandingPage extends StatefulWidget {
  const MobileLandingPage({
    super.key,
    required this.onLogin,
    required this.onRegister,
    required this.apiBaseUrl,
  });

  final VoidCallback onLogin;
  final VoidCallback onRegister;
  final String apiBaseUrl;

  @override
  State<MobileLandingPage> createState() => _MobileLandingPageState();
}

class _MobileLandingPageState extends State<MobileLandingPage>
    with SingleTickerProviderStateMixin {
  late final AnimationController _intro;
  late final Animation<Offset> _panelSlide;
  late final Animation<double> _panelFade;
  late final PageController _heroPage;
  int _heroIndex = 0;
  Timer? _heroAuto;

  static const _heroSlides = [
    (
      title: 'Absensi Sidik Jari',
      subtitle:
          'Absensi lab lewat mesin sidik jari. Pantau kehadiran harian di menu Absensi.',
      icon: Icons.fingerprint_rounded,
      color: Color(0xFF5EEAD4),
    ),
    (
      title: 'Tim PBL',
      subtitle:
          'Lihat manajer proyek, ketua, dan anggota tim beserta peran masing-masing.',
      icon: Icons.groups_outlined,
      color: Color(0xFFA5B4FC),
    ),
    (
      title: 'Riwayat Lengkap',
      subtitle:
          'Arsip absensi dengan filter tanggal.',
      icon: Icons.history_rounded,
      color: Color(0xFF67E8F9),
    ),
    (
      title: 'Pengumuman & Saran',
      subtitle:
          'Baca info lab dari admin, dan kirim saran atau pertanyaan seputar SIMALAS.',
      icon: Icons.campaign_outlined,
      color: Color(0xFFF9A8D4),
    ),
  ];

  int get _slideCount => _heroSlides.length;

  int _slideOf(int page) => page % _slideCount;

  @override
  void initState() {
    super.initState();
    // Mulai di tengah agar bisa geser kiri/kanan tanpa ujung (loop).
    _heroPage = PageController(initialPage: 1000 * _heroSlides.length);
    _heroIndex = 0;
    _intro = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    _panelSlide = Tween<Offset>(
      begin: const Offset(0, .12),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(
        parent: _intro,
        curve: const Interval(.2, 1, curve: Curves.easeOutCubic),
      ),
    );
    _panelFade = CurvedAnimation(
      parent: _intro,
      curve: const Interval(.2, 1, curve: Curves.easeOut),
    );
    _intro.forward();
    WidgetsBinding.instance.addPostFrameCallback((_) => _startHeroAuto());
  }

  void _startHeroAuto() {
    _heroAuto?.cancel();
    if (!mounted) return;
    if (MediaQuery.disableAnimationsOf(context)) return;
    _heroAuto = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted || !_heroPage.hasClients) return;
      _heroPage.nextPage(
        duration: const Duration(milliseconds: 380),
        curve: Curves.easeOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _heroAuto?.cancel();
    _heroPage.dispose();
    _intro.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.auth),
        child: Stack(
          fit: StackFit.expand,
          children: [
            const Positioned.fill(
              child: IgnorePointer(child: _AuthSkyDecor()),
            ),
            SafeArea(
              child: Column(
                children: [
                  const Padding(
                    padding: EdgeInsets.fromLTRB(16, 14, 16, 0),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: _AuthBrandMark(),
                    ),
                  ),
                  Expanded(
                    child: Column(
                      children: [
                        Expanded(
                          child: ScrollConfiguration(
                            behavior: ScrollConfiguration.of(context).copyWith(
                              dragDevices: {
                                PointerDeviceKind.touch,
                                PointerDeviceKind.mouse,
                                PointerDeviceKind.trackpad,
                                PointerDeviceKind.stylus,
                              },
                            ),
                            child: PageView.builder(
                              controller: _heroPage,
                              // Tanpa itemCount = infinite; index di-map ke 5 slide.
                              allowImplicitScrolling: true,
                              physics: const PageScrollPhysics(
                                parent: BouncingScrollPhysics(),
                              ),
                              onPageChanged: (page) {
                                setState(() => _heroIndex = _slideOf(page));
                                _startHeroAuto();
                              },
                              itemBuilder: (context, page) {
                                final s = _heroSlides[_slideOf(page)];
                                return _LandingHeroSlide(
                                  title: s.title,
                                  subtitle: s.subtitle,
                                  icon: s.icon,
                                  accent: s.color,
                                );
                              },
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              for (var i = 0; i < _slideCount; i++)
                                GestureDetector(
                                  onTap: () {
                                    if (!_heroPage.hasClients) return;
                                    final current =
                                        _heroPage.page?.round() ??
                                        _heroPage.initialPage;
                                    final currentSlide = _slideOf(current);
                                    var delta = i - currentSlide;
                                    // Pilih arah terdekat untuk loop.
                                    if (delta > _slideCount / 2) {
                                      delta -= _slideCount;
                                    } else if (delta < -_slideCount / 2) {
                                      delta += _slideCount;
                                    }
                                    _heroPage.animateToPage(
                                      current + delta,
                                      duration: const Duration(
                                        milliseconds: 280,
                                      ),
                                      curve: Curves.easeOutCubic,
                                    );
                                    _startHeroAuto();
                                  },
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 220),
                                    margin: const EdgeInsets.symmetric(
                                      horizontal: 3,
                                    ),
                                    width: i == _heroIndex ? 16 : 7,
                                    height: 7,
                                    decoration: BoxDecoration(
                                      color: i == _heroIndex
                                          ? const Color(0xFF00AED6)
                                          : Colors.white.withValues(
                                              alpha: 0.28,
                                            ),
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  SlideTransition(
                    position: reduce
                        ? const AlwaysStoppedAnimation(Offset.zero)
                        : _panelSlide,
                    child: FadeTransition(
                      opacity: reduce
                          ? const AlwaysStoppedAnimation(1)
                          : _panelFade,
                      child: _LandingActionPanel(
                        onLogin: widget.onLogin,
                        onRegister: widget.onRegister,
                      ),
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

/// Visual fitur di area atas panel “Selamat datang”.
class _LandingHeroSlide extends StatelessWidget {
  const _LandingHeroSlide({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.accent,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 12, 28, 4),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 148,
            height: 148,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(36),
              color: accent.withValues(alpha: 0.12),
              border: Border.all(color: accent.withValues(alpha: 0.28)),
            ),
            child: Icon(icon, size: 72, color: accent),
          ),
          const SizedBox(height: 20),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.82),
              fontSize: 14.5,
              height: 1.4,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

/// Brand mark pojok kiri (tanpa plate putih) — mirip myBCA.
class _AuthBrandMark extends StatelessWidget {
  const _AuthBrandMark();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset(
          'assets/branding/aiot-simalas-mark.png',
          height: 28,
          width: 28,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
          errorBuilder: (context, error, stackTrace) => const Icon(
            Icons.memory_rounded,
            color: Colors.white,
            size: 24,
          ),
        ),
        const SizedBox(width: 5),
        const Text(
          'Smart Lab',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w900,
            fontSize: 17.5,
            letterSpacing: 0.15,
            height: 1.05,
          ),
        ),
      ],
    );
  }
}

class _LandingActionPanel extends StatelessWidget {
  const _LandingActionPanel({
    required this.onLogin,
    required this.onRegister,
  });

  final VoidCallback onLogin;
  final VoidCallback onRegister;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(24, 26, 24, 22),
      decoration: const BoxDecoration(
        color: Color(0xFFF8FAFC),
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: Color(0x33000000),
            blurRadius: 24,
            offset: Offset(0, -6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Selamat datang',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Masuk untuk mengelola absensi laboratorium.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 14,
              height: 1.45,
              fontWeight: FontWeight.w500,
              color: Color(0xFF64748B),
            ),
          ),
          const SizedBox(height: 22),
          SizedBox(
            height: 52,
            width: double.infinity,
            child: FilledButton(
              onPressed: onLogin,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFF0284C7),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              child: const Text(
                'Masuk',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
              ),
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 52,
            width: double.infinity,
            child: OutlinedButton(
              onPressed: onRegister,
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF0284C7),
                side: const BorderSide(color: Color(0xFF7DD3FC), width: 1.4),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              child: const Text(
                'Daftar Akun',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Header dekoratif untuk layar auth (login / daftar / lupa password).
/// Logo brand sudah di pojok kiri top bar — di sini hanya judul.
class AuthPageHero extends StatelessWidget {
  const AuthPageHero({
    super.key,
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
      child: Column(
        children: [
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w500,
              height: 1.45,
              color: Colors.white.withValues(alpha: 0.88),
            ),
          ),
        ],
      ),
    );
  }
}

/// Syarat & ketentuan: popup wajib (scroll → centang → Setuju). Tidak bisa ditutup tanpa setuju.
const kSimalasTermsBody = """
SYARAT & KETENTUAN PENGGUNAAN AIoT SIMALAS

Dengan mendaftar dan menggunakan aplikasi AIoT SIMALAS, Anda menyatakan memahami serta menyetujui ketentuan berikut:

1. Keabsahan data
Anda wajib mengisi data pribadi dan akademik dengan benar (nama, NIM, email, program studi, kelas, angkatan, semester, dan tim PBL). Data yang tidak valid atau menyesatkan dapat ditolak, ditangguhkan, atau dinonaktifkan oleh admin/laboran.

2. Aktivasi akun
Akun mahasiswa belum dapat digunakan untuk login penuh sebelum:
• email diverifikasi melalui kode OTP, dan
• pendaftaran disetujui oleh admin/laboran.
Admin berhak menolak pendaftaran jika data tidak memenuhi persyaratan laboratorium.

3. Keamanan akun
Anda bertanggung jawab penuh menjaga kerahasiaan password dan akses akun. Segala aktivitas yang dilakukan melalui akun Anda dianggap sebagai tindakan Anda. Jangan membagikan akun kepada pihak lain.

4. Penggunaan layanan laboratorium
Fitur absensi biometrik, notifikasi, dan layanan terkait wajib digunakan sesuai ketentuan operasional laboratorium serta kebijakan Politeknik Negeri Batam / unit pengelola terkait.

5. Larangan & sanksi
Dilarang memanipulasi absensi, mengganggu sistem, atau menyalahgunakan aplikasi untuk tujuan yang merugikan. Pelanggaran dapat dikenai sanksi akademik dan/atau administratif sesuai kebijakan kampus.

6. Notifikasi & komunikasi
Aplikasi dapat mengirim pemberitahuan terkait status akun, OTP, pengumuman laboratorium, dan informasi layanan lain yang relevan.

7. Perubahan ketentuan
Pengelola dapat memperbarui syarat ini sewaktu-waktu. Penggunaan aplikasi secara berkelanjutan setelah pembaruan dianggap sebagai penerimaan atas ketentuan yang berlaku.

Dengan mencentang persetujuan, Anda mengonfirmasi telah membaca, memahami, dan menyetujui seluruh ketentuan di atas.
""";

/// Popup syarat: return `true` hanya jika user gulir, centang, lalu Setuju.
Future<bool> showTermsAgreeDialog(BuildContext context) async {
  final result = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) => const _TermsAgreeDialog(),
  );
  return result == true;
}

class _TermsAgreeDialog extends StatefulWidget {
  const _TermsAgreeDialog();

  @override
  State<_TermsAgreeDialog> createState() => _TermsAgreeDialogState();
}

class _TermsAgreeDialogState extends State<_TermsAgreeDialog> {
  final _scroll = ScrollController();
  bool _readFully = false;
  bool _accepted = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scroll.hasClients) return;
      if (_scroll.position.maxScrollExtent <= 0) {
        setState(() => _readFully = true);
      }
    });
  }

  @override
  void dispose() {
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_readFully || !_scroll.hasClients) return;
    final max = _scroll.position.maxScrollExtent;
    if (max <= 0 || _scroll.offset >= max - 12) {
      setState(() => _readFully = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final height = MediaQuery.sizeOf(context).height;
    return PopScope(
      canPop: false,
      child: Dialog(
        backgroundColor: const Color(0xFF121A26),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: height * 0.86, maxWidth: 440),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Syarat & Ketentuan',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 18,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Wajib dibaca sampai akhir dan disetujui untuk mendaftar.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12.5,
                    height: 1.35,
                    fontWeight: FontWeight.w600,
                    color: Colors.white.withValues(alpha: 0.62),
                  ),
                ),
                const SizedBox(height: 14),
                Flexible(
                  child: Container(
                    constraints: BoxConstraints(minHeight: height * 0.38),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0B1220),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFF334155)),
                    ),
                    child: Scrollbar(
                      controller: _scroll,
                      thumbVisibility: true,
                      child: SingleChildScrollView(
                        controller: _scroll,
                        padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
                        child: Text(
                          kSimalasTermsBody.trim(),
                          style: TextStyle(
                            fontSize: 13,
                            height: 1.55,
                            fontWeight: FontWeight.w500,
                            color: Colors.white.withValues(alpha: 0.9),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _readFully
                      ? 'Centang untuk menyetujui, lalu lanjut.'
                      : 'Gulir teks sampai akhir untuk mengaktifkan centang.',
                  textAlign: TextAlign.left,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _readFully
                        ? const Color(0xFF4ADE80)
                        : Colors.white.withValues(alpha: 0.5),
                  ),
                ),
                const SizedBox(height: 4),
                InkWell(
                  onTap: !_readFully
                      ? null
                      : () => setState(() => _accepted = !_accepted),
                  borderRadius: BorderRadius.circular(10),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Checkbox(
                          value: _accepted,
                          onChanged: !_readFully
                              ? null
                              : (v) => setState(() => _accepted = v ?? false),
                          activeColor: AppColors.primary,
                          materialTapTargetSize:
                              MaterialTapTargetSize.shrinkWrap,
                          visualDensity: VisualDensity.compact,
                          side: BorderSide(
                            color: _readFully
                                ? const Color(0xFF7DD3FC)
                                : const Color(0xFF475569),
                            width: 1.6,
                          ),
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            'Saya menyetujui Syarat & Ketentuan',
                            style: TextStyle(
                              fontSize: 13.5,
                              height: 1.35,
                              fontWeight: FontWeight.w700,
                              color: _readFully
                                  ? Colors.white
                                  : Colors.white.withValues(alpha: 0.4),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  height: 48,
                  child: FilledButton(
                    onPressed: _accepted
                        ? () => Navigator.of(context).pop(true)
                        : null,
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF0284C7),
                      disabledBackgroundColor: const Color(0xFF334155),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: const Text(
                      'Setuju & Lanjutkan',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

String pblProjectDisplayLabel(Map<String, dynamic> project) {
  final label = project['label']?.toString().trim() ?? '';
  if (label.isNotEmpty) return label;
  final short = project['short_kode']?.toString().trim() ?? '';
  final nama = project['nama']?.toString().trim() ?? '';
  if (short.isNotEmpty && nama.isNotEmpty) return '$short · $nama';
  return nama.isNotEmpty ? nama : '-';
}

/// Pilih tim PBL dengan kode (RE-044) + pencarian.
class PblProjectPickerField extends StatelessWidget {
  const PblProjectPickerField({
    super.key,
    required this.projects,
    required this.selectedNama,
    required this.onSelected,
  });

  final List<Map<String, dynamic>> projects;
  final String selectedNama;
  final ValueChanged<String> onSelected;

  Map<String, dynamic>? get _selectedProject {
    if (selectedNama.trim().isEmpty) return null;
    for (final p in projects) {
      if (p['nama']?.toString() == selectedNama) return p;
    }
    return null;
  }

  bool get _hasSelection => _selectedProject != null;

  Future<void> _openPicker(BuildContext context) async {
    if (projects.isEmpty) return;
    final picked = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => _PblProjectPickerSheet(projects: projects),
    );
    if (picked != null) {
      final nama = picked['nama']?.toString() ?? '';
      if (nama.isNotEmpty) onSelected(nama);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selected = _selectedProject;
    final hasSelection = _hasSelection;
    final display = hasSelection
        ? pblProjectDisplayLabel(selected!)
        : 'Belum dipilih — tap untuk memilih';

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: projects.isEmpty ? null : () => _openPicker(context),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            color: hasSelection
                ? AppColors.softPanel
                : AppColors.amber.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: hasSelection
                  ? AppColors.skyLight.withValues(alpha: 0.8)
                  : AppColors.amber.withValues(alpha: 0.45),
            ),
          ),
          child: Row(
            children: [
              Icon(
                Icons.groups_2_outlined,
                color: hasSelection ? AppColors.navy : AppColors.amber,
                size: 22,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Tim PBL *', style: AppText.label),
                    const SizedBox(height: 2),
                    Text(
                      display,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontWeight:
                            hasSelection ? FontWeight.w800 : FontWeight.w600,
                        fontSize: 14,
                        fontStyle:
                            hasSelection ? FontStyle.normal : FontStyle.italic,
                        color: hasSelection
                            ? AppColors.textPrimary
                            : AppColors.amber,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.search_rounded,
                color: hasSelection ? AppColors.muted : AppColors.amber,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PblProjectPickerSheet extends StatefulWidget {
  const _PblProjectPickerSheet({required this.projects});

  final List<Map<String, dynamic>> projects;

  @override
  State<_PblProjectPickerSheet> createState() => _PblProjectPickerSheetState();
}

class _PblProjectPickerSheetState extends State<_PblProjectPickerSheet> {
  final _search = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _filtered {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return widget.projects;
    return widget.projects.where((p) {
      final label = pblProjectDisplayLabel(p).toLowerCase();
      final kode = (p['kode']?.toString() ?? '').toLowerCase();
      final short = (p['short_kode']?.toString() ?? '').toLowerCase();
      final nama = (p['nama']?.toString() ?? '').toLowerCase();
      return label.contains(q) ||
          kode.contains(q) ||
          short.contains(q) ||
          nama.contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final items = _filtered;
    return Padding(
      padding: EdgeInsets.only(
        left: 18,
        right: 18,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 18,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Pilih Proyek PBL',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            'Ketik kode (mis. RE-044) atau nama proyek.',
            style: AppText.tiny,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _search,
            onChanged: (v) => setState(() => _query = v),
            style: const TextStyle(color: AppColors.textPrimary),
            decoration: AuthFieldTheme.base(
              hint: 'Cari RE-044, AIoT, Drone…',
              prefixIcon: Icon(Icons.search_rounded, color: AppColors.muted),
            ),
          ),
          const SizedBox(height: 10),
          ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.45,
            ),
            child: items.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(20),
                    child: Text(
                      'Proyek tidak ditemukan.',
                      textAlign: TextAlign.center,
                      style: AppText.muted,
                    ),
                  )
                : ListView.separated(
                    shrinkWrap: true,
                    itemCount: items.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final p = items[index];
                      final short = p['short_kode']?.toString() ?? '';
                      return ListTile(
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 4,
                          vertical: 2,
                        ),
                        leading: short.isNotEmpty
                            ? Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.navy.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  short,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w900,
                                    color: AppColors.navy,
                                  ),
                                ),
                              )
                            : const Icon(Icons.folder_outlined),
                        title: Text(
                          p['nama']?.toString() ?? '-',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: short.isNotEmpty
                            ? Text('Kode: $short', style: AppText.tiny)
                            : null,
                        onTap: () => Navigator.pop(context, p),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

/// Kartu gelap untuk form auth (login / daftar / lupa password).
class AuthFormSheet extends StatelessWidget {
  const AuthFormSheet({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        width: double.infinity,
        decoration: const BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          boxShadow: [
            BoxShadow(
              color: Color(0x66000000),
              blurRadius: 24,
              offset: Offset(0, -6),
            ),
          ],
        ),
        child: child,
      ),
    );
  }
}

String _guestHelpdeskUrl(String apiBaseUrl) {
  var base = apiBaseUrl.trim();
  if (base.endsWith('/')) base = base.substring(0, base.length - 1);
  // Endpoint API publik (tanpa CSRF web): /api/mobile/helpdesk/chat
  return '$base/mobile/helpdesk/chat';
}

/// CS AI sebelum login/registrasi (endpoint publik web helpdesk).
Future<void> showGuestHelpdeskChatSheet(
  BuildContext context, {
  required String apiBaseUrl,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: const Color(0xFF121212),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (sheetContext) => _GuestHelpdeskChatSheet(apiBaseUrl: apiBaseUrl),
  );
}

class _GuestHelpMsg {
  const _GuestHelpMsg({required this.role, required this.text});
  final String role;
  final String text;
}

class _GuestHelpdeskChatSheet extends StatefulWidget {
  const _GuestHelpdeskChatSheet({required this.apiBaseUrl});
  final String apiBaseUrl;

  @override
  State<_GuestHelpdeskChatSheet> createState() =>
      _GuestHelpdeskChatSheetState();
}

class _GuestHelpdeskChatSheetState extends State<_GuestHelpdeskChatSheet> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _messages = <_GuestHelpMsg>[
    const _GuestHelpMsg(
      role: 'assistant',
      text:
          'Selamat datang di AI Helpdesk SIMALAS. Saya siap membantu seputar daftar akun, login, OTP, dan cara menggunakan aplikasi.',
    ),
  ];
  bool _busy = false;

  Widget _robotAvatar({bool large = false}) {
    final size = large ? 44.0 : 34.0;
    final icon = large ? 26.0 : 18.0;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: 0.18),
        border: Border.all(color: Colors.white.withValues(alpha: 0.35)),
      ),
      child: Icon(Icons.smart_toy_rounded, color: Colors.white, size: icon),
    );
  }

  Widget _msgAvatar({required bool isUser}) {
    return Container(
      width: 34,
      height: 34,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: isUser
            ? const LinearGradient(
                colors: [Color(0xFF0284C7), Color(0xFF00AED6)],
              )
            : null,
        color: isUser ? null : const Color(0xFF0C4A6E),
        border: Border.all(
          color: isUser ? Colors.transparent : const Color(0xFF38BDF8),
        ),
      ),
      child: Icon(
        isUser ? Icons.person_rounded : Icons.smart_toy_rounded,
        size: 18,
        color: Colors.white,
      ),
    );
  }

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _toEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  Future<void> _send([String? preset]) async {
    final q = (preset ?? _input.text).trim();
    if (q.isEmpty || _busy) return;
    setState(() {
      _messages.add(_GuestHelpMsg(role: 'user', text: q));
      _busy = true;
      if (preset == null) _input.clear();
    });
    _toEnd();

    try {
      final history = _messages
          .where((m) => m.role == 'user' || m.role == 'assistant')
          .toList();
      if (history.isNotEmpty) history.removeLast();
      final histPayload = history
          .reversed
          .take(8)
          .toList()
          .reversed
          .map((m) => {'role': m.role, 'content': m.text})
          .toList();

      final res = await http
          .post(
            Uri.parse(_guestHelpdeskUrl(widget.apiBaseUrl)),
            headers: const {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({
              'message': q,
              'history': histPayload,
            }),
          )
          .timeout(const Duration(seconds: 60));

      final decoded = jsonDecode(res.body);
      final map =
          decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
      final ok = res.statusCode < 400 &&
          (map['ok'] == true || map['success'] == true || map['reply'] != null);
      final reply = ok
          ? (map['reply'] ?? map['message'] ?? 'Maaf, saya belum bisa menjawab.')
              .toString()
          : (map['message']?.toString().isNotEmpty == true
              ? map['message'].toString()
              : 'Helpdesk sedang bermasalah (HTTP ${res.statusCode}). Coba lagi sebentar.');
      if (!mounted) return;
      setState(() {
        _messages.add(_GuestHelpMsg(role: 'assistant', text: reply));
        _busy = false;
      });
      _toEnd();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _messages.add(
          const _GuestHelpMsg(
            role: 'assistant',
            text:
                'Koneksi ke helpdesk gagal. Pastikan server SIMALAS aktif, lalu coba lagi.',
          ),
        );
        _busy = false;
      });
      _toEnd();
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.82,
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.white24,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            Container(
              margin: const EdgeInsets.fromLTRB(16, 14, 16, 8),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF0369A1), Color(0xFF00AED6)],
                ),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  _robotAvatar(large: true),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'AI Helpdesk SIMALAS',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Online · Bantuan daftar, login, dan OTP',
                          style: TextStyle(
                            color: Color(0xFFE0F2FE),
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded, color: Colors.white),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView.builder(
                controller: _scroll,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                itemCount: _messages.length + (_busy ? 1 : 0),
                itemBuilder: (context, i) {
                  if (_busy && i == _messages.length) {
                    return Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Row(
                        children: [
                          _msgAvatar(isUser: false),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 10,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFF1E2328),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: const Color(0xFF334155),
                              ),
                            ),
                            child: const Text(
                              'Asisten mengetik…',
                              style: TextStyle(
                                color: Color(0xFF38BDF8),
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  }
                  final m = _messages[i];
                  final mine = m.role == 'user';
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      mainAxisAlignment: mine
                          ? MainAxisAlignment.end
                          : MainAxisAlignment.start,
                      children: [
                        if (!mine) ...[
                          _msgAvatar(isUser: false),
                          const SizedBox(width: 8),
                        ],
                        Flexible(
                          child: Column(
                            crossAxisAlignment: mine
                                ? CrossAxisAlignment.end
                                : CrossAxisAlignment.start,
                            children: [
                              Text(
                                mine ? 'Anda' : 'Asisten AI',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.4,
                                  color: mine
                                      ? const Color(0xFF38BDF8)
                                      : const Color(0xFF7DD3FC),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 14,
                                  vertical: 11,
                                ),
                                decoration: BoxDecoration(
                                  gradient: mine
                                      ? const LinearGradient(
                                          colors: [
                                            Color(0xFF0284C7),
                                            Color(0xFF00AED6),
                                          ],
                                        )
                                      : null,
                                  color: mine
                                      ? null
                                      : const Color(0xFF1E2328),
                                  borderRadius: BorderRadius.only(
                                    topLeft: const Radius.circular(16),
                                    topRight: const Radius.circular(16),
                                    bottomLeft: Radius.circular(mine ? 16 : 4),
                                    bottomRight: Radius.circular(mine ? 4 : 16),
                                  ),
                                  border: mine
                                      ? null
                                      : Border.all(
                                          color: const Color(0xFF334155),
                                        ),
                                ),
                                child: Text(
                                  m.text,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    height: 1.45,
                                    fontSize: 14,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (mine) ...[
                          const SizedBox(width: 8),
                          _msgAvatar(isUser: true),
                        ],
                      ],
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final s in const [
                    'Cara daftar akun?',
                    'OTP tidak masuk',
                    'Akun belum disetujui',
                  ])
                    ActionChip(
                      label: Text(
                        s,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF0369A1),
                        ),
                      ),
                      backgroundColor: const Color(0xFFE0F2FE),
                      side: const BorderSide(color: Color(0xFF7DD3FC)),
                      onPressed: _busy ? null : () => _send(s),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _input,
                      enabled: !_busy,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        hintText: 'Tulis pertanyaan…',
                        hintStyle: const TextStyle(color: Color(0xFF64748B)),
                        filled: true,
                        fillColor: const Color(0xFF1E2328),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                      ),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _busy ? null : () => _send(),
                    style: IconButton.styleFrom(
                      backgroundColor: const Color(0xFF0284C7),
                    ),
                    icon: _busy
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.send_rounded),
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

/// Lingkaran & garis halus di background auth / landing.
class _AuthSkyDecor extends StatelessWidget {
  const _AuthSkyDecor();

  @override
  Widget build(BuildContext context) {
    return CustomPaint(painter: _AuthSkyDecorPainter());
  }
}

class _AuthSkyDecorPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..style = PaintingStyle.fill;

    paint.color = Colors.white.withValues(alpha: 0.08);
    canvas.drawCircle(Offset(size.width * 0.88, size.height * 0.12), 72, paint);
    canvas.drawCircle(Offset(size.width * 0.1, size.height * 0.28), 48, paint);

    paint.color = Colors.white.withValues(alpha: 0.06);
    canvas.drawCircle(Offset(size.width * 0.72, size.height * 0.42), 110, paint);

    final line = Paint()
      ..color = Colors.white.withValues(alpha: 0.12)
      ..strokeWidth = 1.2;
    for (var i = 0; i < 5; i++) {
      final y = size.height * (0.15 + i * 0.08);
      canvas.drawLine(Offset(0, y), Offset(size.width, y + 24), line);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
