part of '../main.dart';

/// Palet beranda — adaptif terang/gelap.
class MhsHomePalette {
  MhsHomePalette._();

  static const headerBlue = Color(0xFF00AED6);
  static const headerBlueDeep = Color(0xFF0093C4);
  static const darkCard = Color(0xFF1E2328);
  static const darkCardTopRadius = 36.0;
  static const pageBg = Color(0xFF121212);
  static const pageBgLight = Color(0xFFF1F5F9);
  static const menuCardLight = Color(0xFFFFFFFF);

  static bool isLight(BuildContext context) =>
      Theme.of(context).brightness == Brightness.light;

  static Color sheet(BuildContext context) =>
      isLight(context) ? menuCardLight : darkCard;

  static Color body(BuildContext context) =>
      isLight(context) ? pageBgLight : pageBg;
}

const _mhsCoachMarkTooltipBg = Color(0xFF1E1E1E);
const _mhsCoachMarkTitleStyle = TextStyle(
  color: Color(0xFF00AED6),
  fontWeight: FontWeight.w800,
  fontSize: 16,
);
const _mhsCoachMarkDescStyle = TextStyle(
  color: Colors.white,
  fontSize: 13,
  height: 1.35,
);

Widget mhsCoachMarkShowcase({
  required GlobalKey showcaseKey,
  required String title,
  required String description,
  required Widget child,
  BorderRadius? targetBorderRadius,
}) {
  return Showcase(
    key: showcaseKey,
    title: title,
    description: description,
    tooltipBackgroundColor: _mhsCoachMarkTooltipBg,
    titleTextStyle: _mhsCoachMarkTitleStyle,
    descTextStyle: _mhsCoachMarkDescStyle,
    textColor: Colors.white,
    targetBorderRadius: targetBorderRadius ?? BorderRadius.circular(16),
    child: child,
  );
}

/// Header beranda kustom — gradient biru, profil Row, tanpa AppBar/ListTile/Card.
class MhsBcaDashboardHeader extends StatelessWidget {
  const MhsBcaDashboardHeader({
    super.key,
    required this.user,
    required this.unreadCount,
    required this.onNotifications,
    required this.onSuggestion,
    this.onOpenProfile,
    this.compactBottom = false,
    this.profileShowcaseKey,
    this.notifShowcaseKey,
    this.csShowcaseKey,
  });

  final Map<String, dynamic> user;
  final int unreadCount;
  final VoidCallback onNotifications;
  final VoidCallback onSuggestion;
  final VoidCallback? onOpenProfile;
  final bool compactBottom;
  final GlobalKey? profileShowcaseKey;
  final GlobalKey? notifShowcaseKey;
  final GlobalKey? csShowcaseKey;

  static const headerBlue = MhsHomePalette.headerBlue;

  static const _headerGradient = LinearGradient(
    colors: [Color(0xFF0D4F7A), Color(0xFF00AED6)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  @override
  Widget build(BuildContext context) {
    final enrolled = mhsIsFingerprintEnrolled(user);
    final name = formatDisplayName(user['nama_lengkap']?.toString());
    final nim = user['nitk_nidn']?.toString() ?? '-';
    final pbl = (user['tim']?.toString().trim().isNotEmpty ?? false)
        ? user['tim'].toString().trim()
        : '-';
    final photoUrl = user['profile_photo_url']?.toString();
    final initial = name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : 'M';
    final hasPhoto =
        photoUrl != null &&
        photoUrl.isNotEmpty &&
        (photoUrl.startsWith('http://') || photoUrl.startsWith('https://'));

    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(gradient: _headerGradient),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            right: -36,
            top: -28,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.1),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                18,
                12,
                14,
                compactBottom ? (enrolled ? 36 : 30) : (enrolled ? 28 : 22),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: profileShowcaseKey != null
                            ? mhsCoachMarkShowcase(
                                showcaseKey: profileShowcaseKey!,
                                title: 'Header profil',
                                description:
                                    'Ketuk untuk buka halaman Profil. Tahan foto untuk memperbesar gambar (jika sudah upload).',
                                targetBorderRadius: BorderRadius.circular(20),
                                child: _profileTapArea(
                                  onOpenProfile: onOpenProfile,
                                  hasPhoto: hasPhoto,
                                  photoUrl: photoUrl ?? '',
                                  initial: initial,
                                  name: name,
                                  nim: nim,
                                  pbl: pbl,
                                ),
                              )
                            : _profileTapArea(
                                onOpenProfile: onOpenProfile,
                                hasPhoto: hasPhoto,
                                photoUrl: photoUrl ?? '',
                                initial: initial,
                                name: name,
                                nim: nim,
                                pbl: pbl,
                              ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              _wrapHeaderCoachMark(
                                showcaseKey: notifShowcaseKey,
                                title: 'Lonceng notifikasi',
                                description:
                                    'Pengumuman dan pesan penting dari laboran. Badge merah = masih belum dibaca.',
                                child: _HeaderIconBtn(
                                  icon: Icons.notifications_outlined,
                                  badge: unreadCount,
                                  onTap: onNotifications,
                                  plain: true,
                                  iconColor: Colors.white,
                                  badgeBorderColor: const Color(0xFF0D4F7A),
                                ),
                              ),
                              const SizedBox(width: 4),
                              _wrapHeaderCoachMark(
                                showcaseKey: csShowcaseKey,
                                title: 'CS AI',
                                description:
                                    'Tanya asisten AI seputar cara pakai aplikasi. Saran resmi ke admin lewat menu Saran.',
                                child: _HeaderIconBtn(
                                  icon: Icons.smart_toy_rounded,
                                  onTap: onSuggestion,
                                  plain: true,
                                  iconColor: Colors.white,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Material(
                            color: Colors.transparent,
                            child: InkWell(
                              onTap: onOpenProfile,
                              borderRadius: BorderRadius.circular(8),
                              child: Icon(
                                Icons.chevron_right_rounded,
                                color: Colors.white.withValues(alpha: 0.75),
                                size: 26,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  if (!enrolled) ...[
                    const SizedBox(height: 14),
                    MhsFloatingEnrollCard(onOpenProfile: onOpenProfile),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MhsProfileTapArea extends StatelessWidget {
  const _MhsProfileTapArea({
    required this.onOpenProfile,
    required this.hasPhoto,
    required this.photoUrl,
    required this.initial,
    required this.name,
    required this.nim,
    required this.pbl,
  });

  final VoidCallback? onOpenProfile;
  final bool hasPhoto;
  final String photoUrl;
  final String initial;
  final String name;
  final String nim;
  final String pbl;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onOpenProfile,
        onLongPress: hasPhoto
            ? () => showMhsProfilePhotoPreview(context, photoUrl)
            : null,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              GestureDetector(
                onLongPress: hasPhoto
                    ? () => showMhsProfilePhotoPreview(context, photoUrl)
                    : null,
                child: CircleAvatar(
                  radius: 28,
                  backgroundColor: Colors.white.withValues(alpha: 0.22),
                  backgroundImage: hasPhoto ? NetworkImage(photoUrl) : null,
                  child: hasPhoto
                      ? null
                      : Text(
                          initial,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 22,
                          ),
                        ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Selamat datang,',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.82),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      name,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        height: 1.15,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'NIM: $nim',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.88),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'PBL: $pbl',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.88),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

Widget _profileTapArea({
  required VoidCallback? onOpenProfile,
  required bool hasPhoto,
  required String photoUrl,
  required String initial,
  required String name,
  required String nim,
  required String pbl,
}) {
  return _MhsProfileTapArea(
    onOpenProfile: onOpenProfile,
    hasPhoto: hasPhoto,
    photoUrl: photoUrl,
    initial: initial,
    name: name,
    nim: nim,
    pbl: pbl,
  );
}

Widget _wrapHeaderCoachMark({
  required GlobalKey? showcaseKey,
  required String title,
  required String description,
  required Widget child,
}) {
  if (showcaseKey == null) return child;
  return mhsCoachMarkShowcase(
    showcaseKey: showcaseKey,
    title: title,
    description: description,
    targetBorderRadius: BorderRadius.circular(12),
    child: child,
  );
}

/// Bagian atas beranda ala GoPay: header biru + sheet menu gelap (radius atas saja).
class MhsGopayHomeTop extends StatelessWidget {
  const MhsGopayHomeTop({
    super.key,
    required this.user,
    required this.unreadCount,
    required this.onNotifications,
    required this.onSuggestion,
    required this.onNavigateTab,
    this.onBantuan,
    this.onOpenProfile,
    this.onOpenTeam,
    this.onOpenHistory,
    this.profileShowcaseKey,
    this.absensiShowcaseKey,
    this.timShowcaseKey,
    this.riwayatShowcaseKey,
    this.pengumumanShowcaseKey,
    this.profilMenuShowcaseKey,
    this.saranShowcaseKey,
    this.lainnyaShowcaseKey,
    this.notifShowcaseKey,
    this.csShowcaseKey,
    this.onReplayCoachMarks,
    this.onOpenSettings,
  });

  final Map<String, dynamic> user;
  final int unreadCount;
  final VoidCallback onNotifications;
  final VoidCallback onSuggestion;
  final ValueChanged<int> onNavigateTab;
  final VoidCallback? onBantuan;
  final VoidCallback? onOpenProfile;
  final VoidCallback? onOpenTeam;
  final VoidCallback? onOpenHistory;
  final GlobalKey? profileShowcaseKey;
  final GlobalKey? absensiShowcaseKey;
  final GlobalKey? timShowcaseKey;
  final GlobalKey? riwayatShowcaseKey;
  final GlobalKey? pengumumanShowcaseKey;
  final GlobalKey? profilMenuShowcaseKey;
  final GlobalKey? saranShowcaseKey;
  final GlobalKey? lainnyaShowcaseKey;
  final GlobalKey? notifShowcaseKey;
  final GlobalKey? csShowcaseKey;
  final VoidCallback? onReplayCoachMarks;
  final VoidCallback? onOpenSettings;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        MhsBcaDashboardHeader(
          user: user,
          unreadCount: unreadCount,
          onNotifications: onNotifications,
          onSuggestion: onSuggestion,
          onOpenProfile: onOpenProfile,
          compactBottom: true,
          profileShowcaseKey: profileShowcaseKey,
          notifShowcaseKey: notifShowcaseKey,
          csShowcaseKey: csShowcaseKey,
        ),
        Transform.translate(
          offset: const Offset(0, -MhsHomePalette.darkCardTopRadius),
          child: Container(
            width: double.infinity,
            decoration: BoxDecoration(
              color: MhsHomePalette.sheet(context),
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(MhsHomePalette.darkCardTopRadius),
              ),
              boxShadow: MhsHomePalette.isLight(context)
                  ? [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.06),
                        blurRadius: 16,
                        offset: const Offset(0, -2),
                      ),
                    ]
                  : null,
            ),
            child: MhsQuickAccessGrid(
              user: user,
              gopayStyle: true,
              gopaySheet: true,
              onTap: onNavigateTab,
              onBantuan: onBantuan,
              onOpenTeam: onOpenTeam,
              onOpenHistory: onOpenHistory,
              onOpenCsAi: onSuggestion,
              onOpenAnnouncements: onNotifications,
              absensiShowcaseKey: absensiShowcaseKey,
              timShowcaseKey: timShowcaseKey,
              riwayatShowcaseKey: riwayatShowcaseKey,
              pengumumanShowcaseKey: pengumumanShowcaseKey,
              profilMenuShowcaseKey: profilMenuShowcaseKey,
              saranShowcaseKey: saranShowcaseKey,
              lainnyaShowcaseKey: lainnyaShowcaseKey,
              onReplayCoachMarks: onReplayCoachMarks,
              onOpenSettings: onOpenSettings,
            ),
          ),
        ),
      ],
    );
  }
}

/// Kartu peringatan enroll — overlap header, latar putih + bayangan lembut.
class MhsFloatingEnrollCard extends StatelessWidget {
  const MhsFloatingEnrollCard({super.key, this.onOpenProfile});

  final VoidCallback? onOpenProfile;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      elevation: 0,
      shadowColor: Colors.transparent,
      child: InkWell(
        onTap: onOpenProfile,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: const Border(
              left: BorderSide(color: AppColors.amber, width: 4),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.1),
                blurRadius: 36,
                spreadRadius: 0,
                offset: const Offset(0, 12),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.amber.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.fingerprint_outlined,
                  color: AppColors.amber,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Belum enroll sidik jari',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                        color: AppColors.amber,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Absensi aktif setelah sidik jari terdaftar di mesin.',
                      style: AppText.tiny.copyWith(height: 1.35),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: AppColors.amber),
            ],
          ),
        ),
      ),
    );
  }
}

/// Form saran & masukan ke admin laboran (bottom sheet).
Future<void> showMhsHelpdeskChatSheet(
  BuildContext context, {
  required ApiClient api,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: const Color(0xFF121212),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (sheetContext) => _MhsHelpdeskChatSheet(api: api),
  );
}

class _HelpdeskChatMessage {
  const _HelpdeskChatMessage({
    required this.role,
    required this.text,
    this.source,
  });

  final String role;
  final String text;
  final String? source;
}

class _MhsHelpdeskChatSheet extends StatefulWidget {
  const _MhsHelpdeskChatSheet({required this.api});

  final ApiClient api;

  @override
  State<_MhsHelpdeskChatSheet> createState() => _MhsHelpdeskChatSheetState();
}

class _MhsHelpdeskChatSheetState extends State<_MhsHelpdeskChatSheet> {
  final _inputController = TextEditingController();
  final _scrollController = ScrollController();
  final List<_HelpdeskChatMessage> _messages = [
    const _HelpdeskChatMessage(
      role: 'assistant',
      text:
          'Selamat datang di AI Helpdesk SIMALAS. Saya siap membantu seputar absensi, OTP, unduh APK, dan cara menggunakan aplikasi.',
    ),
  ];
  bool _busy = false;

  static const _suggestions = [
    'Apa itu AIoT SIMALAS?',
    'Cara enroll sidik jari?',
    'OTP tidak masuk, bagaimana?',
    'Mahasiswa login di mana?',
  ];

  @override
  void dispose() {
    _inputController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOut,
      );
    });
  }

  Future<void> _send([String? preset]) async {
    final q = (preset ?? _inputController.text).trim();
    if (q.isEmpty || _busy) return;

    setState(() {
      _messages.add(_HelpdeskChatMessage(role: 'user', text: q));
      _busy = true;
      if (preset == null) _inputController.clear();
    });
    _scrollToEnd();

    try {
      final history = _messages
          .where((m) => m.role == 'user' || m.role == 'assistant')
          .take(_messages.length - 1)
          .toList()
          .reversed
          .take(8)
          .toList()
          .reversed
          .map((m) => {'role': m.role, 'content': m.text})
          .toList();

      final data = await widget.api.post(
        '/mobile/helpdesk/chat',
        auth: true,
        timeout: const Duration(seconds: 60),
        body: {
          'message': q,
          'history': history,
        },
      );

      final reply = (data['reply'] ?? data['message'] ?? 'Maaf, saya belum bisa menjawab.').toString();
      if (!mounted) return;
      setState(() {
        _messages.add(
          _HelpdeskChatMessage(
            role: 'assistant',
            text: reply,
            source: data['source']?.toString(),
          ),
        );
        _busy = false;
      });
      _scrollToEnd();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _messages.add(
          _HelpdeskChatMessage(
            role: 'assistant',
            text: e is ApiException
                ? e.message
                : 'Koneksi gagal. Periksa internet lalu coba lagi.',
          ),
        );
        _busy = false;
      });
      _scrollToEnd();
    }
  }

  Widget _avatar({required bool isUser}) {
    return Container(
      width: 32,
      height: 32,
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
        size: 16,
        color: Colors.white,
      ),
    );
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
                  _avatar(isUser: false),
                  const SizedBox(width: 10),
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
                          'Customer service · beda dari Saran & Masukan',
                          style: TextStyle(
                            color: Color(0xFFE0F2FE),
                            fontSize: 11,
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
                controller: _scrollController,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                itemCount: _messages.length + (_busy ? 1 : 0),
                itemBuilder: (context, index) {
                  if (_busy && index == _messages.length) {
                    return Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Row(
                        children: [
                          _avatar(isUser: false),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 10,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFF1E2328),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Text(
                              'Asisten mengetik…',
                              style: TextStyle(
                                color: Color(0xFF38BDF8),
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  }

                  final m = _messages[index];
                  final isUser = m.role == 'user';
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      mainAxisAlignment: isUser
                          ? MainAxisAlignment.end
                          : MainAxisAlignment.start,
                      children: [
                        if (!isUser) ...[
                          _avatar(isUser: false),
                          const SizedBox(width: 8),
                        ],
                        Flexible(
                          child: Column(
                            crossAxisAlignment: isUser
                                ? CrossAxisAlignment.end
                                : CrossAxisAlignment.start,
                            children: [
                              Text(
                                isUser ? 'Anda' : 'Asisten AI',
                                style: TextStyle(
                                  color: isUser
                                      ? const Color(0xFF38BDF8)
                                      : const Color(0xFF7DD3FC),
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 10,
                                ),
                                decoration: BoxDecoration(
                                  gradient: isUser
                                      ? const LinearGradient(
                                          colors: [
                                            Color(0xFF0284C7),
                                            Color(0xFF00AED6),
                                          ],
                                        )
                                      : null,
                                  color: isUser
                                      ? null
                                      : const Color(0xFF1E2328),
                                  borderRadius: BorderRadius.only(
                                    topLeft: const Radius.circular(16),
                                    topRight: const Radius.circular(16),
                                    bottomLeft: Radius.circular(
                                      isUser ? 16 : 4,
                                    ),
                                    bottomRight: Radius.circular(
                                      isUser ? 4 : 16,
                                    ),
                                  ),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      m.text,
                                      style: TextStyle(
                                        color: isUser
                                            ? Colors.white
                                            : const Color(0xFFE5E7EB),
                                        fontSize: 13.5,
                                        height: 1.4,
                                      ),
                                    ),
                                    if (!isUser && m.source == 'gemini') ...[
                                      const SizedBox(height: 6),
                                      const Text(
                                        'Gemini AI',
                                        style: TextStyle(
                                          color: Color(0xFF38BDF8),
                                          fontSize: 10,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (isUser) ...[
                          const SizedBox(width: 8),
                          _avatar(isUser: true),
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
                spacing: 6,
                runSpacing: 6,
                children: _suggestions.map((s) {
                  return ActionChip(
                    label: Text(s, style: const TextStyle(fontSize: 11)),
                    onPressed: _busy ? null : () => _send(s),
                    backgroundColor: const Color(0xFFE0F2FE),
                    side: const BorderSide(color: Color(0xFF7DD3FC)),
                    labelStyle: const TextStyle(
                      color: Color(0xFF0369A1),
                      fontWeight: FontWeight.w700,
                    ),
                  );
                }).toList(),
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _inputController,
                        enabled: !_busy,
                        maxLength: 500,
                        minLines: 1,
                        maxLines: 4,
                        style: const TextStyle(color: Colors.white),
                        decoration: InputDecoration(
                          counterText: '',
                          hintText: 'Tulis pertanyaan…',
                          hintStyle: const TextStyle(color: Color(0xFF9CA3AF)),
                          filled: true,
                          fillColor: const Color(0xFF1E2328),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 12,
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(14),
                            borderSide: BorderSide.none,
                          ),
                        ),
                        onSubmitted: (_) => _send(),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Material(
                      color: const Color(0xFF0284C7),
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        onTap: _busy ? null : () => _send(),
                        borderRadius: BorderRadius.circular(14),
                        child: const SizedBox(
                          width: 48,
                          height: 48,
                          child: Icon(Icons.send_rounded, color: Colors.white),
                        ),
                      ),
                    ),
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

Future<void> showMhsSuggestionSheet(
  BuildContext context, {
  required ApiClient api,
}) {
  final messenger = ScaffoldMessenger.of(context);

  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.card,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (sheetContext) =>
        _MhsSuggestionSheet(api: api, messenger: messenger),
  );
}

class _MhsSuggestionSheet extends StatefulWidget {
  const _MhsSuggestionSheet({required this.api, required this.messenger});

  final ApiClient api;
  final ScaffoldMessengerState messenger;

  @override
  State<_MhsSuggestionSheet> createState() => _MhsSuggestionSheetState();
}

class _MhsSuggestionSheetState extends State<_MhsSuggestionSheet> {
  static const _fieldTextStyle = TextStyle(
    color: Color(0xFF111827),
    fontSize: 15,
    fontWeight: FontWeight.w500,
  );
  static const _fieldHintStyle = TextStyle(
    color: Color(0xFF6B7280),
    fontSize: 14,
  );

  late final TextEditingController _subjectController;
  late final TextEditingController _messageController;
  bool _loading = false;
  String? _bannerMessage;
  bool _bannerIsError = true;

  @override
  void initState() {
    super.initState();
    _subjectController = TextEditingController();
    _messageController = TextEditingController();
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  void _showBanner(String message, {required bool isError}) {
    setState(() {
      _bannerMessage = message;
      _bannerIsError = isError;
    });
  }

  void _clearBanner() {
    if (_bannerMessage == null) return;
    setState(() => _bannerMessage = null);
  }

  Future<void> _submit() async {
    final pesan = _messageController.text.trim();
    if (pesan.length < 10) {
      _showBanner(
        'Pesan terlalu singkat. Minimal 10 karakter (saat ini ${pesan.length}).',
        isError: true,
      );
      return;
    }

    _clearBanner();
    setState(() => _loading = true);
    try {
      final subjek = _subjectController.text.trim();
      await widget.api.post(
        '/mobile/suggestions',
        auth: true,
        body: {if (subjek.isNotEmpty) 'subjek': subjek, 'pesan': pesan},
      );
      if (!mounted) return;
      Navigator.pop(context);
      widget.messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Saran terkirim. Admin laboran akan meninjau masukan Anda.',
          ),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      _showBanner(e.message, isError: true);
    } catch (e) {
      if (!mounted) return;
      _showBanner('Gagal mengirim saran. Coba lagi.', isError: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.muted.withValues(alpha: 0.35),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            if (_bannerMessage != null) ...[
              const SizedBox(height: 12),
              Material(
                elevation: 2,
                shadowColor: Colors.black26,
                borderRadius: BorderRadius.circular(14),
                color: _bannerIsError
                    ? AppColors.rose.withValues(alpha: 0.1)
                    : AppColors.success.withValues(alpha: 0.12),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: _bannerIsError
                          ? AppColors.rose.withValues(alpha: 0.35)
                          : AppColors.success.withValues(alpha: 0.35),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        _bannerIsError
                            ? Icons.error_outline_rounded
                            : Icons.check_circle_outline_rounded,
                        color: _bannerIsError
                            ? AppColors.rose
                            : AppColors.success,
                        size: 20,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _bannerMessage!,
                          style: TextStyle(
                            color: _bannerIsError
                                ? AppColors.rose
                                : AppColors.success,
                            fontWeight: FontWeight.w700,
                            height: 1.35,
                          ),
                        ),
                      ),
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(
                          minWidth: 28,
                          minHeight: 28,
                        ),
                        onPressed: _clearBanner,
                        icon: Icon(
                          Icons.close_rounded,
                          size: 18,
                          color: AppColors.muted.withValues(alpha: 0.8),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: 20),
            Center(
              child: Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.14),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.support_agent_rounded,
                  color: AppColors.skyDark,
                  size: 40,
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Saran & Masukan',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              'Kirim pesan ke admin laboran SIMALAS.',
              textAlign: TextAlign.center,
              style: AppText.muted,
            ),
            const SizedBox(height: 20),
            TextField(
              controller: _subjectController,
              onChanged: (_) => _clearBanner(),
              style: _fieldTextStyle,
              cursorColor: AppColors.primary,
              decoration: InputDecoration(
                hintText: 'Subjek (opsional)',
                hintStyle: _fieldHintStyle,
                prefixIcon: const Icon(
                  Icons.title_outlined,
                  color: Color(0xFF6B7280),
                ),
                filled: true,
                fillColor: Colors.grey.shade100,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide(
                    color: AppColors.primary.withValues(alpha: 0.35),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _messageController,
              minLines: 4,
              maxLines: 6,
              maxLength: 4000,
              onChanged: (_) => _clearBanner(),
              style: _fieldTextStyle,
              cursorColor: AppColors.primary,
              decoration: InputDecoration(
                hintText: 'Tulis saran atau masukan Anda…',
                hintStyle: _fieldHintStyle,
                helperText: 'Minimal 10 karakter',
                helperStyle: const TextStyle(color: Color(0xFF6B7280)),
                counterStyle: const TextStyle(color: Color(0xFF6B7280)),
                filled: true,
                fillColor: Colors.grey.shade100,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide(
                    color: AppColors.primary.withValues(alpha: 0.35),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: Material(
                color: const Color(0xFF0F172A),
                shape: const StadiumBorder(),
                child: InkWell(
                  customBorder: const StadiumBorder(),
                  onTap: _loading ? null : _submit,
                  child: Center(
                    child: _loading
                        ? const SizedBox.square(
                            dimension: 22,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.send_rounded,
                                color: Colors.white,
                                size: 20,
                              ),
                              SizedBox(width: 8),
                              Text(
                                'Kirim',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 15,
                                ),
                              ),
                            ],
                          ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoBannerSlide {
  const _InfoBannerSlide({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final IconData icon;
}

const _defaultBannerSlides = [
  _InfoBannerSlide(
    title: 'Absensi sidik jari',
    subtitle:
        'Buka menu Absensi untuk grafik & kalender. Arsip lengkap dari Absensi → Riwayat.',
    icon: Icons.fingerprint,
  ),
  _InfoBannerSlide(
    title: 'Tim & anggota',
    subtitle:
        'Menu Tim menampilkan manajer proyek dan anggota PBL yang terdaftar.',
    icon: Icons.groups_outlined,
  ),
  _InfoBannerSlide(
    title: 'Pengumuman lab',
    subtitle:
        'Info resmi dari laboran lewat lonceng notifikasi atau menu Pengumuman.',
    icon: Icons.campaign_outlined,
  ),
  _InfoBannerSlide(
    title: 'Profil & sidik jari',
    subtitle:
        'Lengkapi data di Profil. Absensi aktif setelah sidik jari di-enroll admin.',
    icon: Icons.person_outline,
  ),
];

class _CarouselSlideData {
  const _CarouselSlideData({required this.slide});

  final _InfoBannerSlide slide;
}

/// Banner info fitur beranda — carousel bacaan (bukan notifikasi).
class MhsInfoBannerCarousel extends StatefulWidget {
  const MhsInfoBannerCarousel({
    super.key,
    this.announcements = const [],
    this.onAnnouncementTap,
  });

  /// Disimpan untuk kompatibilitas pemanggilan lama; tidak ditampilkan di carousel.
  final List<Map<String, dynamic>> announcements;
  final ValueChanged<Map<String, dynamic>>? onAnnouncementTap;

  @override
  State<MhsInfoBannerCarousel> createState() => _MhsInfoBannerCarouselState();
}

class _MhsInfoBannerCarouselState extends State<MhsInfoBannerCarousel> {
  int _currentIndex = 0;

  List<_CarouselSlideData> get _slides => _defaultBannerSlides
      .map((s) => _CarouselSlideData(slide: s))
      .toList();

  @override
  Widget build(BuildContext context) {
    final slides = _slides;
    final loop = slides.length > 1;

    return Column(
      children: [
        CarouselSlider.builder(
          itemCount: slides.length,
          options: CarouselOptions(
            height: 118,
            viewportFraction: 0.88,
            enlargeCenterPage: false,
            enableInfiniteScroll: loop,
            autoPlay: loop,
            autoPlayInterval: const Duration(seconds: 4),
            autoPlayAnimationDuration: const Duration(milliseconds: 750),
            autoPlayCurve: Curves.easeInOutCubic,
            onPageChanged: (index, reason) {
              setState(() => _currentIndex = index % slides.length);
            },
          ),
          itemBuilder: (context, itemIndex, pageViewIndex) {
            final safeIndex = itemIndex % slides.length;
            final slide = slides[safeIndex].slide;

            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Material(
                color: Colors.transparent,
                child: Ink(
                  decoration: BoxDecoration(
                    color: AppColors.surfaceOf(context),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: AppColors.borderOf(context)),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                    child: Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            slide.icon,
                            color: AppColors.primary,
                            size: 24,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                slide.title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 14,
                                  color: AppColors.ink,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                slide.subtitle,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 12,
                                  height: 1.35,
                                  color: AppColors.fade,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(slides.length, (i) {
            final active = i == _currentIndex;
            return AnimatedContainer(
              duration: const Duration(milliseconds: 220),
              margin: const EdgeInsets.symmetric(horizontal: 3),
              width: active ? 18 : 7,
              height: 7,
              decoration: BoxDecoration(
                color: active
                    ? AppColors.primary
                    : AppColors.fade.withValues(alpha: 0.35),
                borderRadius: BorderRadius.circular(99),
              ),
            );
          }),
        ),
      ],
    );
  }
}

/// Header beranda legacy (flat) — gunakan [MhsBcaDashboardHeader].
class MhsHomeLogoHeader extends StatelessWidget {
  const MhsHomeLogoHeader({
    super.key,
    required this.user,
    required this.unreadCount,
    required this.onNotifications,
    required this.onLogout,
  });

  final Map<String, dynamic> user;
  final int unreadCount;
  final VoidCallback onNotifications;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.bg,
      padding: const EdgeInsets.fromLTRB(14, 10, 10, 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          const LogoMark(size: 42, plain: true),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Smart Lab',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: AppColors.skyDark,
                letterSpacing: 0.5,
                height: 1.1,
              ),
            ),
          ),
          _HeaderIconBtn(
            icon: Icons.notifications_outlined,
            badge: unreadCount,
            onTap: onNotifications,
            onLight: true,
            iconColor: AppColors.notifYellow,
          ),
          const SizedBox(width: 4),
          _HeaderIconBtn(
            icon: Icons.workspace_premium_outlined,
            onTap: () => showStudentAccessDialog(context, user),
            onLight: true,
          ),
          const SizedBox(width: 4),
          _HeaderIconBtn(
            icon: Icons.logout_rounded,
            onTap: onLogout,
            iconColor: AppColors.rose,
            onLight: true,
          ),
        ],
      ),
    );
  }
}

@Deprecated('Gunakan MhsHomeLogoHeader')
typedef MhsMyBcaHeader = MhsHomeLogoHeader;

/// Dialog sambutan setelah login berhasil.
Future<void> showMhsLoginWelcomeDialog(
  BuildContext context,
  Map<String, dynamic> user,
) {
  final name = user['nama_lengkap']?.toString().trim();
  final greeting = (name != null && name.isNotEmpty) ? name : 'Mahasiswa';
  final ink = AppColors.onSurfaceOf(context);
  final muted = AppColors.mutedOf(context);

  return showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (ctx) {
      return Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: AppColors.success,
                  size: 48,
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Login Berhasil',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: ink,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Selamat datang, $greeting!',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: muted,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Anda sudah masuk ke aplikasi mahasiswa SIMALAS.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 12,
                  height: 1.35,
                  color: muted,
                ),
              ),
              const SizedBox(height: 22),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: FilledButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text(
                    'Mulai',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
}

/// Dialog sukses registrasi: animasi centang Lottie, tanpa tombol, lalu auto ke login.
Future<void> showMhsRegisterSuccessDialog(
  BuildContext context, {
  required String message,
  String? fingerId,
  Duration displayDuration = const Duration(seconds: 4),
  VoidCallback? onFinished,
}) {
  final fingerHint = (fingerId != null && fingerId.isNotEmpty)
      ? 'Finger ID Anda: $fingerId — siap enroll di mesin absensi setelah admin menyetujui akun.'
      : 'Setelah admin menyetujui, Anda bisa login dan enroll sidik jari di mesin absensi.';

  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) {
      return _MhsRegisterSuccessDialog(
        message: message,
        fingerHint: fingerHint,
        displayDuration: displayDuration,
        onFinished: () {
          if (Navigator.of(ctx).canPop()) {
            Navigator.of(ctx).pop();
          }
          onFinished?.call();
        },
      );
    },
  );
}

class _MhsRegisterSuccessDialog extends StatefulWidget {
  const _MhsRegisterSuccessDialog({
    required this.message,
    required this.fingerHint,
    required this.displayDuration,
    required this.onFinished,
  });

  final String message;
  final String fingerHint;
  final Duration displayDuration;
  final VoidCallback onFinished;

  @override
  State<_MhsRegisterSuccessDialog> createState() =>
      _MhsRegisterSuccessDialogState();
}

class _MhsRegisterSuccessDialogState extends State<_MhsRegisterSuccessDialog> {
  @override
  void initState() {
    super.initState();
    Future<void>.delayed(widget.displayDuration, () {
      if (!mounted) return;
      widget.onFinished();
    });
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 120,
                height: 120,
                child: Lottie.asset(
                  'assets/animations/success_check.json',
                  repeat: false,
                  fit: BoxFit.contain,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Akun Berhasil Dibuat',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: AppColors.ink,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                widget.message,
                textAlign: TextAlign.center,
                style: AppText.muted.copyWith(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                widget.fingerHint,
                textAlign: TextAlign.center,
                style: AppText.tiny.copyWith(height: 1.4),
              ),
              const SizedBox(height: 14),
              Text(
                'Mengalihkan ke halaman login…',
                textAlign: TextAlign.center,
                style: AppText.tiny.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeaderIconBtn extends StatefulWidget {
  const _HeaderIconBtn({
    required this.icon,
    required this.onTap,
    this.badge = 0,
    this.label,
    this.iconColor,
    this.onLight = false,
    this.plain = false,
    this.badgeBorderColor,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int badge;
  final String? label;
  final Color? iconColor;
  final bool onLight;
  final bool plain;
  final Color? badgeBorderColor;

  @override
  State<_HeaderIconBtn> createState() => _HeaderIconBtnState();
}

class _HeaderIconBtnState extends State<_HeaderIconBtn>
    with SingleTickerProviderStateMixin {
  late final AnimationController _shakeCtrl;
  late final Animation<double> _shake;

  @override
  void initState() {
    super.initState();
    _shakeCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 620),
    );
    _shake = Tween<double>(
      begin: -0.08,
      end: 0.08,
    ).animate(CurvedAnimation(parent: _shakeCtrl, curve: Curves.easeInOut));
    if (widget.badge > 0) {
      _shakeCtrl.repeat(reverse: true);
    }
  }

  @override
  void didUpdateWidget(covariant _HeaderIconBtn oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.badge > 0 && !_shakeCtrl.isAnimating) {
      _shakeCtrl.repeat(reverse: true);
    } else if (widget.badge <= 0 && _shakeCtrl.isAnimating) {
      _shakeCtrl.stop();
      _shakeCtrl.value = 0;
    }
  }

  @override
  void dispose() {
    _shakeCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final badgeLabel = widget.badge > 99 ? '99+' : widget.badge.toString();
    final iconView = Icon(
      widget.icon,
      color:
          widget.iconColor ??
          (widget.onLight ? AppColors.skyDark : Colors.white),
      size: 22,
    );
    final shouldShake =
        widget.badge > 0 && widget.icon == Icons.notifications_outlined;

    return Material(
      color: widget.plain
          ? Colors.transparent
          : widget.onLight
          ? AppColors.card
          : Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(14),
      elevation: 0,
      shadowColor: Colors.transparent,
      child: InkWell(
        onTap: widget.onTap,
        borderRadius: BorderRadius.circular(14),
        splashColor: Colors.white.withValues(alpha: 0.12),
        highlightColor: Colors.white.withValues(alpha: 0.06),
        child: Padding(
          padding: EdgeInsets.symmetric(
            horizontal: widget.label != null ? 10 : 6,
            vertical: 8,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  shouldShake
                      ? AnimatedBuilder(
                          animation: _shake,
                          builder: (_, child) => Transform.rotate(
                            angle: _shake.value,
                            child: child,
                          ),
                          child: iconView,
                        )
                      : iconView,
                  if (widget.badge > 0)
                    Positioned(
                      right: -10,
                      top: -8,
                      child: Container(
                        constraints: const BoxConstraints(minWidth: 16),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 4,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.rose,
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color:
                                widget.badgeBorderColor ??
                                MhsBcaDashboardHeader.headerBlue,
                            width: 1.5,
                          ),
                        ),
                        child: Text(
                          badgeLabel,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w900,
                            height: 1.1,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              if (widget.label != null) ...[
                const SizedBox(width: 4),
                Text(
                  widget.label!,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class MhsAccountCard extends StatelessWidget {
  const MhsAccountCard({super.key, required this.user, this.onDetail});

  final Map<String, dynamic> user;
  final VoidCallback? onDetail;

  @override
  Widget build(BuildContext context) {
    final name = formatDisplayName(user['nama_lengkap']?.toString());
    final nim = user['nitk_nidn']?.toString() ?? '-';
    final photoUrl = user['profile_photo_url']?.toString();
    final initial = name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : 'M';
    final hasPhoto =
        photoUrl != null &&
        photoUrl.isNotEmpty &&
        (photoUrl.startsWith('http://') || photoUrl.startsWith('https://'));

    return Container(
      margin: const EdgeInsets.fromLTRB(18, 12, 18, 0),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: AppCardDecoration.box(context, radius: 20),
      child: Row(
        children: [
          CircleAvatar(
            radius: 30,
            backgroundColor: AppColors.softPanel,
            backgroundImage: hasPhoto ? NetworkImage(photoUrl) : null,
            child: !hasPhoto
                ? Text(
                    initial,
                    style: const TextStyle(
                      color: AppColors.skyDark,
                      fontWeight: FontWeight.w900,
                      fontSize: 22,
                    ),
                  )
                : null,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Selamat datang,',
                  style: AppText.tiny.copyWith(
                    color: AppColors.muted,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  name,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: AppColors.ink,
                    height: 1.15,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Mahasiswa • $nim',
                  style: AppText.muted.copyWith(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          if (onDetail != null)
            IconButton(
              onPressed: onDetail,
              icon: const Icon(Icons.chevron_right_rounded),
              color: AppColors.primary,
              tooltip: 'Profil lengkap',
            ),
        ],
      ),
    );
  }
}

class _RecentActivityEntry {
  const _RecentActivityEntry({
    required this.at,
    required this.title,
    required this.subtitle,
    required this.timeLabel,
    this.accentColor,
  });

  final DateTime? at;
  final String title;
  final String subtitle;
  final String timeLabel;
  final Color? accentColor;
}

List<_RecentActivityEntry> mhsBuildRecentActivities({
  required List<Map<String, dynamic>> harian,
  int limit = 6,
}) {
  final entries = <_RecentActivityEntry>[];

  for (final item in harian) {
    final at = mhsParseDate(item['tanggal']?.toString());
    final st = item['status']?.toString();
    final label = mhsStatusLabel(st, row: item);
    entries.add(
      _RecentActivityEntry(
        at: at,
        title: label,
        subtitle: item['sesi'] != null
            ? 'Sesi ${item['sesi']} · Kehadiran lab'
            : 'Kehadiran harian',
        timeLabel: at != null
            ? DateFormat('dd MMMM', 'id_ID').format(at)
            : formatDateShortFull(item['tanggal']?.toString()),
        accentColor: mhsStatusColor(st, row: item),
      ),
    );
  }

  entries.sort((a, b) {
    final da = a.at;
    final db = b.at;
    if (da == null && db == null) return 0;
    if (da == null) return 1;
    if (db == null) return -1;
    return db.compareTo(da);
  });

  if (entries.length > limit) {
    return entries.sublist(0, limit);
  }
  return entries;
}

/// Pintasan beranda: tim PBL + ajakan buka Absensi (rekap detail di tab Absensi).
class MhsHomeQuickStats extends StatelessWidget {
  const MhsHomeQuickStats({
    super.key,
    this.tim,
    this.onOpenTeam,
    this.onOpenAbsensi,
  });

  final String? tim;
  final VoidCallback? onOpenTeam;
  final VoidCallback? onOpenAbsensi;

  @override
  Widget build(BuildContext context) {
    final timLabel = (tim != null && tim!.trim().isNotEmpty)
        ? tim!.trim()
        : 'Belum ada tim';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onOpenAbsensi,
            borderRadius: BorderRadius.circular(16),
            child: Ink(
              padding: const EdgeInsets.all(14),
              decoration: AppCardDecoration.box(context, radius: 16),
              child: Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.fingerprint,
                      color: AppColors.success,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Kehadiran lab',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            color: AppColors.onSurfaceOf(context),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Grafik & kalender ada di menu Absensi',
                          style: TextStyle(
                            color: AppColors.mutedOf(context),
                            fontSize: 12.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    Icons.chevron_right_rounded,
                    color: AppColors.mutedOf(context),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onOpenTeam,
            borderRadius: BorderRadius.circular(16),
            child: Ink(
              padding: const EdgeInsets.all(14),
              decoration: AppCardDecoration.box(context, radius: 16),
              child: Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.groups_outlined,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Tim PBL',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            color: AppColors.onSurfaceOf(context),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          timLabel,
                          style: TextStyle(
                            color: AppColors.mutedOf(context),
                            fontSize: 12.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    Icons.chevron_right_rounded,
                    color: AppColors.mutedOf(context),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Cuplikan kehadiran lab terbaru.
class MhsRecentActivityFeed extends StatelessWidget {
  const MhsRecentActivityFeed({
    super.key,
    required this.harian,
    this.limit = 8,
    this.showTitle = true,
    this.onOpenAbsensi,
    this.onOpenAll,
    this.showcaseKey,
  });

  final List<Map<String, dynamic>> harian;
  final int limit;
  final bool showTitle;
  final VoidCallback? onOpenAbsensi;
  final VoidCallback? onOpenAll;
  final GlobalKey? showcaseKey;

  @override
  Widget build(BuildContext context) {
    final items = mhsBuildRecentActivities(harian: harian, limit: limit);

    final feed = items.isEmpty
        ? Container(
            padding: const EdgeInsets.all(20),
            decoration: AppCardDecoration.box(context, radius: 16),
            child: Text(
              'Belum ada aktivitas tercatat.',
              textAlign: TextAlign.center,
              style: AppText.muted.copyWith(fontWeight: FontWeight.w600),
            ),
          )
        : Container(
            decoration: AppCardDecoration.box(context, radius: 16),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < items.length; i++) ...[
                  if (i > 0)
                    Divider(
                      height: 1,
                      thickness: 1,
                      indent: 16,
                      endIndent: 16,
                      color: AppColors.borderOf(context),
                    ),
                  _RecentActivityListTile(
                    entry: items[i],
                    onOpenAbsensi: onOpenAbsensi,
                  ),
                ],
              ],
            ),
          );

    if (!showTitle) return feed;

    final section = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Riwayat Aktivitas Terakhir',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                  color: AppColors.onSurfaceOf(context),
                ),
              ),
            ),
            if (onOpenAll != null || onOpenAbsensi != null)
              TextButton(
                onPressed: onOpenAll ?? onOpenAbsensi,
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: Text(
                  'Lihat semua',
                  style: AppText.tiny.copyWith(
                    color: MhsHomePalette.headerBlueDeep,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 10),
        feed,
      ],
    );

    if (showcaseKey == null) return section;

    return mhsCoachMarkShowcase(
      showcaseKey: showcaseKey!,
      title: 'Aktivitas terbaru',
      description:
          'Cuplikan kehadiran lab terbaru. Ketuk “Lihat semua” untuk buka riwayat lengkap.',
      targetBorderRadius: BorderRadius.circular(16),
      child: section,
    );
  }
}

class _RecentActivityListTile extends StatelessWidget {
  const _RecentActivityListTile({required this.entry, this.onOpenAbsensi});

  final _RecentActivityEntry entry;
  final VoidCallback? onOpenAbsensi;

  @override
  Widget build(BuildContext context) {
    final iconColor = entry.accentColor ?? AppColors.muted;
    final iconBg = iconColor.withValues(alpha: 0.14);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onOpenAbsensi,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: iconBg,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  Icons.fingerprint_rounded,
                  color: iconColor,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      entry.title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      entry.subtitle,
                      style: AppText.tiny.copyWith(height: 1.3),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Text(
                entry.timeLabel,
                style: AppText.tiny.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.muted,
                ),
                textAlign: TextAlign.right,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class MhsQuickAccessGrid extends StatelessWidget {
  const MhsQuickAccessGrid({
    super.key,
    required this.user,
    required this.onTap,
    this.onBantuan,
    this.onOpenTeam,
    this.onOpenHistory,
    this.onOpenCsAi,
    this.onOpenAnnouncements,
    this.gopayStyle = false,
    this.gopaySheet = false,
    this.absensiShowcaseKey,
    this.timShowcaseKey,
    this.riwayatShowcaseKey,
    this.pengumumanShowcaseKey,
    this.profilMenuShowcaseKey,
    this.saranShowcaseKey,
    this.lainnyaShowcaseKey,
    this.onReplayCoachMarks,
    this.onOpenSettings,
  });

  final Map<String, dynamic> user;
  final ValueChanged<int> onTap;
  final VoidCallback? onBantuan;
  final VoidCallback? onOpenTeam;
  final VoidCallback? onOpenHistory;
  final VoidCallback? onOpenCsAi;
  final VoidCallback? onOpenAnnouncements;
  final bool gopayStyle;
  final bool gopaySheet;
  final GlobalKey? absensiShowcaseKey;
  final GlobalKey? timShowcaseKey;
  final GlobalKey? riwayatShowcaseKey;
  final GlobalKey? pengumumanShowcaseKey;
  final GlobalKey? profilMenuShowcaseKey;
  final GlobalKey? saranShowcaseKey;
  final GlobalKey? lainnyaShowcaseKey;
  final VoidCallback? onReplayCoachMarks;
  final VoidCallback? onOpenSettings;

  static const _items = [
    _QuickItem(Icons.fingerprint, 'Absensi', 1),
    _QuickItem(Icons.groups_outlined, 'Tim', null, action: 'tim'),
    _QuickItem(
      Icons.campaign_outlined,
      'Pengumuman',
      null,
      action: 'pengumuman',
    ),
    _QuickItem(Icons.history_rounded, 'Riwayat', null, action: 'riwayat'),
    _QuickItem(Icons.person_outline, 'Profil', 2),
    _QuickItem(Icons.feedback_outlined, 'Saran', null, action: 'saran'),
    _QuickItem(Icons.apps_rounded, 'Lainnya', null, isMore: true),
  ];

  @override
  Widget build(BuildContext context) {
    final enrolled = mhsIsFingerprintEnrolled(user);
    const cols = 4;
    const spacing = 10.0;
    final light = MhsHomePalette.isLight(context);
    final titleColor = light
        ? AppColors.textPrimaryLight
        : (gopayStyle ? Colors.white : AppColors.textPrimary);

    return Container(
      margin: gopaySheet
          ? EdgeInsets.zero
          : EdgeInsets.fromLTRB(
              gopayStyle ? 16 : 14,
              gopayStyle ? 0 : 18,
              gopayStyle ? 16 : 14,
              0,
            ),
      decoration: gopaySheet
          ? null
          : BoxDecoration(
              color: gopayStyle
                  ? (light ? MhsHomePalette.menuCardLight : MhsHomePalette.darkCard)
                  : AppColors.surfaceOf(context),
              borderRadius: BorderRadius.circular(gopayStyle ? 24 : 22),
              border: gopayStyle && !light
                  ? Border.all(color: Colors.white.withValues(alpha: 0.08))
                  : (light
                        ? Border.all(color: const Color(0xFFE2E8F0))
                        : null),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(
                    alpha: light ? 0.06 : (gopayStyle ? 0.22 : 0.08),
                  ),
                  blurRadius: gopayStyle ? 24 : 28,
                  offset: Offset(0, gopayStyle ? 12 : 10),
                ),
              ],
            ),
      padding: EdgeInsets.fromLTRB(
        18,
        gopaySheet ? 22 : 18,
        18,
        gopaySheet ? 24 : 20,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Menu Utama',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: titleColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final cellW =
                  (constraints.maxWidth - spacing * (cols - 1)) / cols;
              return Wrap(
                spacing: spacing,
                runSpacing: 16,
                children: [
                  for (final item in _items)
                    Builder(
                      builder: (context) {
                        Widget tile = SizedBox(
                          width: cellW,
                          child: _BcaQuickTile(
                            item: item,
                            gopayStyle: gopayStyle,
                            locked:
                                !enrolled &&
                                item.tabIndex != null &&
                                !item.isMore &&
                                (item.tabIndex == 1 || item.tabIndex == 3),
                            onTap: () {
                              if (item.isMore) {
                                showMhsMoreMenuSheet(
                                  context,
                                  onReplayCoachMarks: onReplayCoachMarks,
                                  onSettings: onOpenSettings,
                                  onCsAi: onOpenCsAi,
                                  onBantuan: onBantuan,
                                );
                                return;
                              }
                              if (item.action == 'tim') {
                                onOpenTeam?.call();
                                return;
                              }
                              if (item.action == 'riwayat') {
                                onOpenHistory?.call();
                                return;
                              }
                              if (item.action == 'pengumuman') {
                                onOpenAnnouncements?.call();
                                return;
                              }
                              if (item.action == 'saran') {
                                onBantuan?.call();
                                return;
                              }
                              if (item.tabIndex == null) {
                                onBantuan?.call();
                                return;
                              }
                              if (!enrolled &&
                                  (item.tabIndex == 1 || item.tabIndex == 3)) {
                                mhsShowEnrollRequiredSnack(context);
                                return;
                              }
                              onTap(item.tabIndex!);
                            },
                          ),
                        );
                        if (absensiShowcaseKey != null &&
                            item.label == 'Absensi') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: absensiShowcaseKey!,
                            title: '1. Absensi',
                            description:
                                'Ringkasan & grafik kehadiran lab. Absen di mesin sidik jari. Arsip detail ada di menu Riwayat.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        if (timShowcaseKey != null && item.label == 'Tim') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: timShowcaseKey!,
                            title: '3. Tim PBL',
                            description:
                                'Lihat manajer proyek dan daftar anggota kelompok PBL Anda.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        if (riwayatShowcaseKey != null &&
                            item.label == 'Riwayat') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: riwayatShowcaseKey!,
                            title: '4. Riwayat',
                            description:
                                'Arsip kehadiran lab. Ketuk untuk membuka riwayat absensi.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        if (pengumumanShowcaseKey != null &&
                            item.label == 'Pengumuman') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: pengumumanShowcaseKey!,
                            title: '5. Pengumuman',
                            description:
                                'Baca info resmi dari laboran. Notifikasi baru juga muncul di lonceng pojok kanan atas.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        if (profilMenuShowcaseKey != null &&
                            item.label == 'Profil') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: profilMenuShowcaseKey!,
                            title: '6. Profil',
                            description:
                                'Data diri, email, Finger ID, dan status enroll sidik jari. Tahan foto profil untuk memperbesar gambar.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        if (saranShowcaseKey != null && item.label == 'Saran') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: saranShowcaseKey!,
                            title: '7. Saran',
                            description:
                                'Kirim masukan atau laporan ke admin laboran SIMALAS.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        if (lainnyaShowcaseKey != null &&
                            item.label == 'Lainnya') {
                          tile = mhsCoachMarkShowcase(
                            showcaseKey: lainnyaShowcaseKey!,
                            title: '8. Lainnya',
                            description:
                                'CS AI, unduh APK, pengaturan tema, dan fitur tambahan. Bisa putar ulang panduan dari sini.',
                            targetBorderRadius: BorderRadius.circular(14),
                            child: tile,
                          );
                        }
                        return tile;
                      },
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

/// Dialog coming soon yang lebih informatif daripada snackbar.
Future<void> showMhsComingSoonDialog(
  BuildContext context, {
  required String featureName,
  String? description,
}) {
  return showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.rocket_launch_rounded,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              featureName,
              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
            ),
          ),
        ],
      ),
      content: Text(
        description ??
            'Fitur ini sedang kami kembangkan untuk SIMALAS v1.4. '
                'Nantikan pembaruan berikutnya!',
        style: AppText.muted.copyWith(height: 1.5),
      ),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(ctx),
          style: FilledButton.styleFrom(
            backgroundColor: MhsHomePalette.headerBlue,
            foregroundColor: Colors.white,
          ),
          child: const Text('Mengerti'),
        ),
      ],
    ),
  );
}

/// Sheet pengaturan aplikasi.
void showMhsSettingsSheet(
  BuildContext context,
  ThemeStore themeStore, {
  ApiClient? api,
  Map<String, dynamic>? user,
  VoidCallback? onReplayCoachMarks,
  VoidCallback? onOpenCsAi,
  VoidCallback? onOpenProfile,
}) {
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceOf(context),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (ctx) {
      return AnimatedBuilder(
        animation: themeStore,
        builder: (context, _) {
          final enrolled =
              user != null && mhsIsFingerprintEnrolled(user);
          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Container(
                        width: 42,
                        height: 4,
                        decoration: BoxDecoration(
                          color: AppColors.mutedOf(context).withValues(alpha: 0.35),
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Pengaturan',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: AppColors.onSurfaceOf(context),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Sesuaikan tampilan dan pintasan aplikasi.',
                      style: AppText.muted.copyWith(
                        color: AppColors.mutedOf(context),
                      ),
                    ),
                    const SizedBox(height: 12),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        themeStore.isLight
                            ? Icons.light_mode_rounded
                            : Icons.dark_mode_rounded,
                        color: AppColors.primary,
                      ),
                      title: Text(
                        'Tema tampilan',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AppColors.onSurfaceOf(context),
                        ),
                      ),
                      subtitle: Text(
                        themeStore.isLight ? 'Mode terang' : 'Mode gelap',
                        style: TextStyle(color: AppColors.mutedOf(context)),
                      ),
                      trailing: Switch.adaptive(
                        value: themeStore.isLight,
                        activeThumbColor: AppColors.primary,
                        onChanged: (value) => themeStore.setLight(value),
                      ),
                    ),
                    const Divider(height: 20),
                    _SettingsActionTile(
                      icon: Icons.fingerprint_rounded,
                      title: 'Status sidik jari',
                      subtitle: enrolled
                          ? 'Sudah terdaftar di mesin absensi'
                          : 'Belum enroll — absensi terkunci',
                      onTap: () {
                        Navigator.pop(ctx);
                        onOpenProfile?.call();
                      },
                    ),
                    _SettingsActionTile(
                      icon: Icons.explore_outlined,
                      title: 'Panduan aplikasi',
                      subtitle: 'Tampilkan ulang coach marks di beranda',
                      onTap: () {
                        Navigator.pop(ctx);
                        onReplayCoachMarks?.call();
                      },
                    ),
                    _SettingsActionTile(
                      icon: Icons.smart_toy_outlined,
                      title: 'CS AI Helpdesk',
                      subtitle: 'Tanya seputar absensi dan akun',
                      onTap: () {
                        Navigator.pop(ctx);
                        onOpenCsAi?.call();
                      },
                    ),
                    _SettingsActionTile(
                      icon: Icons.download_rounded,
                      title: 'Unduh APK terbaru',
                      subtitle: 'Buka halaman unduhan resmi SIMALAS',
                      onTap: () async {
                        Navigator.pop(ctx);
                        await openSimalasApkDownload(context);
                      },
                    ),
                    _SettingsActionTile(
                      icon: Icons.notifications_outlined,
                      title: 'Notifikasi',
                      subtitle: 'Lihat & tandai semua notifikasi baru',
                      onTap: () {
                        Navigator.pop(ctx);
                        if (api != null) {
                          showAnnouncementsPopup(context, api: api);
                        }
                      },
                    ),
                    const SizedBox(height: 8),
                    FutureBuilder<PackageInfo>(
                      future: PackageInfo.fromPlatform(),
                      builder: (context, snap) {
                        final v = snap.data;
                        final label = v == null
                            ? 'Memuat versi…'
                            : 'SIMALAS ${v.version} (${v.buildNumber})';
                        return Text(
                          label,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: AppColors.mutedOf(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      );
    },
  );
}

class _SettingsActionTile extends StatelessWidget {
  const _SettingsActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: AppColors.primary),
      title: Text(
        title,
        style: TextStyle(
          fontWeight: FontWeight.w700,
          color: AppColors.onSurfaceOf(context),
        ),
      ),
      subtitle: Text(
        subtitle,
        style: TextStyle(color: AppColors.mutedOf(context), fontSize: 12.5),
      ),
      trailing: Icon(
        Icons.chevron_right_rounded,
        color: AppColors.mutedOf(context),
      ),
      onTap: onTap,
    );
  }
}

/// Popup menu fitur tambahan (sebagian coming soon).
void showMhsMoreMenuSheet(
  BuildContext context, {
  VoidCallback? onReplayCoachMarks,
  VoidCallback? onSettings,
  VoidCallback? onCsAi,
  VoidCallback? onBantuan,
}) {
  const extras = [
    _QuickItem(
      Icons.smart_toy_outlined,
      'CS AI',
      null,
      action: 'cs_ai',
    ),
    _QuickItem(
      Icons.download_rounded,
      'Unduh APK',
      null,
      action: 'unduh_apk',
    ),
    _QuickItem(
      Icons.calendar_month_outlined,
      'Kalender Lab',
      null,
      comingSoon: true,
    ),
    _QuickItem(Icons.bar_chart_rounded, 'Statistik', null, comingSoon: true),
    _QuickItem(Icons.menu_book_outlined, 'Modul PBL', null, comingSoon: true),
    _QuickItem(
      Icons.qr_code_scanner_rounded,
      'Scan QR',
      null,
      comingSoon: true,
    ),
    _QuickItem(Icons.feedback_outlined, 'Saran', null, action: 'saran'),
    _QuickItem(Icons.settings_outlined, 'Pengaturan', null, action: 'settings'),
  ];

  showModalBottomSheet<void>(
    context: context,
    backgroundColor: AppColors.card,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (ctx) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.muted.withValues(alpha: 0.35),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Menu Lainnya',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              'CS AI, unduh APK, panduan, dan fitur tambahan SIMALAS.',
              style: AppText.muted,
            ),
            const SizedBox(height: 18),
            if (onReplayCoachMarks != null) ...[
              FilledButton.icon(
                onPressed: () {
                  Navigator.pop(ctx);
                  onReplayCoachMarks();
                },
                style: FilledButton.styleFrom(
                  backgroundColor: MhsHomePalette.headerBlue,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                icon: const Icon(Icons.explore_outlined),
                label: const Text(
                  'Tampilkan panduan lagi',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 18),
            ],
            LayoutBuilder(
              builder: (context, constraints) {
                const cols = 3;
                const spacing = 12.0;
                final cellW =
                    (constraints.maxWidth - spacing * (cols - 1)) / cols;
                return Wrap(
                  spacing: spacing,
                  runSpacing: 18,
                  children: [
                    for (final item in extras)
                      SizedBox(
                        width: cellW,
                        child: _BcaQuickTile(
                          item: item,
                          comingSoon: item.comingSoon,
                          onTap: () async {
                            if (item.comingSoon) {
                              Navigator.pop(ctx);
                              showMhsComingSoonDialog(
                                context,
                                featureName: item.label,
                              );
                              return;
                            }
                            if (item.action == 'settings' ||
                                item.label == 'Pengaturan') {
                              Navigator.pop(ctx);
                              onSettings?.call();
                              return;
                            }
                            if (item.action == 'cs_ai') {
                              Navigator.pop(ctx);
                              onCsAi?.call();
                              return;
                            }
                            if (item.action == 'unduh_apk') {
                              Navigator.pop(ctx);
                              await openSimalasApkDownload(context);
                              return;
                            }
                            if (item.action == 'saran') {
                              Navigator.pop(ctx);
                              onBantuan?.call();
                              return;
                            }
                          },
                        ),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      );
    },
  );
}

/// Buka halaman unduh APK resmi SIMALAS.
Future<void> openSimalasApkDownload(BuildContext context) async {
  final url = Uri.parse(
    '${publicOriginFromApiBase(kProductionApiBaseUrl)}/download',
  );
  try {
    final launched = await launchUrl(url, mode: LaunchMode.externalApplication);
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Browser tidak dapat dibuka. Kunjungi iotsimalas.my.id/download.',
          ),
        ),
      );
    }
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Gagal membuka unduhan. Kunjungi iotsimalas.my.id/download.',
          ),
        ),
      );
    }
  }
}

class _QuickItem {
  const _QuickItem(
    this.icon,
    this.label,
    this.tabIndex, {
    this.isMore = false,
    this.comingSoon = false,
    this.action,
  });

  final IconData icon;
  final String label;
  final int? tabIndex;
  final bool isMore;
  final bool comingSoon;
  final String? action;
}

class _BcaQuickTile extends StatelessWidget {
  const _BcaQuickTile({
    required this.item,
    required this.onTap,
    this.locked = false,
    this.comingSoon = false,
    this.gopayStyle = false,
  });

  final _QuickItem item;
  final VoidCallback onTap;
  final bool locked;
  final bool comingSoon;
  final bool gopayStyle;

  @override
  Widget build(BuildContext context) {
    final light = MhsHomePalette.isLight(context);
    final dimmed = locked || comingSoon;
    final iconColor = dimmed
        ? (gopayStyle
              ? (light
                    ? AppColors.mutedLight
                    : Colors.white.withValues(alpha: 0.38))
              : AppColors.mutedOf(context))
        : (gopayStyle ? MhsHomePalette.headerBlue : AppColors.primary);
    final iconBg = gopayStyle
        ? (light ? const Color(0xFFE0F2FE) : const Color(0xFF2A3038))
        : Colors.blue.shade50;
    final labelColor = dimmed
        ? (gopayStyle
              ? (light
                    ? AppColors.mutedLight
                    : Colors.white.withValues(alpha: 0.55))
              : AppColors.mutedOf(context))
        : (gopayStyle
              ? (light ? AppColors.textPrimaryLight : Colors.white.withValues(alpha: 0.92))
              : AppColors.onSurfaceOf(context));
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: iconBg,
                    borderRadius: gopayStyle ? BorderRadius.circular(14) : null,
                    shape: gopayStyle ? BoxShape.rectangle : BoxShape.circle,
                  ),
                  child: Icon(
                    item.icon,
                    color: iconColor,
                    size: 26,
                  ),
                ),
                if (locked)
                  Positioned(
                    right: -2,
                    bottom: -2,
                    child: Container(
                      width: 18,
                      height: 18,
                      decoration: BoxDecoration(
                        color: light ? Colors.white : const Color(0xFF1E293B),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: light
                              ? const Color(0xFFE2E8F0)
                              : Colors.white.withValues(alpha: 0.12),
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.12),
                            blurRadius: 4,
                            offset: const Offset(0, 1),
                          ),
                        ],
                      ),
                      child: Icon(
                        Icons.lock_rounded,
                        size: 11,
                        color: light ? AppColors.mutedLight : Colors.white70,
                      ),
                    ),
                  ),
                if (comingSoon)
                  Positioned(
                    top: -4,
                    right: -6,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.amber,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Text(
                        'Soon',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 8,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              item.label,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: labelColor,
                height: 1.2,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

DateTime? mhsParseDate(String? raw) {
  if (raw == null || raw.isEmpty) return null;
  try {
    final n = raw.contains('T') ? raw : raw.replaceFirst(' ', 'T');
    return DateTime.parse(n).toLocal();
  } catch (_) {
    return null;
  }
}

bool mhsStatusHadir(String? status) {
  const ok = {'lengkap', 'masuk', 'libur', 'sakit', 'izin'};
  return ok.contains(status?.toLowerCase());
}

bool mhsStatusAlfa(String? status) {
  final v = status?.toLowerCase();
  if (v == 'tidak_wajib') return false;
  return v == 'alfa' || v == 'tidak_lengkap' || v == 'belum';
}

bool mhsStatusTerlambat(Map<String, dynamic> row) {
  if (!mhsStatusHadir(row['status']?.toString())) return false;
  final m = row['menit_terlambat_masuk'];
  final late = m is int ? m : int.tryParse(m?.toString() ?? '') ?? 0;
  return late > 0;
}

String mhsStatusLabel(String? status, {Map<String, dynamic>? row}) {
  if (row != null && (mhsStatusTerlambat(row) || mhsTelatPulang(row))) {
    return 'Terlambat';
  }
  final v = status?.toLowerCase();
  switch (v) {
    case 'lengkap':
    case 'masuk':
      return 'Hadir';
    case 'libur':
      return 'Libur';
    case 'sakit':
      return 'Sakit';
    case 'izin':
      return 'Izin';
    case 'alfa':
      return 'Alfa';
    case 'tidak_lengkap':
      return 'Tidak lengkap';
    case 'tidak_wajib':
      return 'Tidak wajib';
    case 'belum':
      return 'Belum hadir';
    default:
      return status?.toString() ?? '—';
  }
}

bool mhsTelatPulang(Map<String, dynamic> row) {
  final p = row['menit_terlambat_pulang'];
  final late = p is int ? p : int.tryParse(p?.toString() ?? '') ?? 0;
  return late > 0;
}

/// Kategori untuk filter tabel: hadir | alfa | terlambat
String mhsAttendanceCategory(Map<String, dynamic> row) {
  if (mhsStatusTerlambat(row) || mhsTelatPulang(row)) return 'terlambat';
  if (mhsStatusAlfa(row['status']?.toString())) return 'alfa';
  if (mhsStatusHadir(row['status']?.toString())) return 'hadir';
  return 'lain';
}

List<Map<String, dynamic>> mhsFilterHarianByStatus(
  List<Map<String, dynamic>> harian,
  String statusFilter,
) {
  if (statusFilter == 'semua') return harian;
  return harian
      .where((row) => mhsAttendanceCategory(row) == statusFilter)
      .toList();
}

Color mhsStatusColor(String? status, {Map<String, dynamic>? row}) {
  if (row != null && (mhsStatusTerlambat(row) || mhsTelatPulang(row))) {
    return AppColors.amberInk;
  }
  final v = status?.toLowerCase();
  if (mhsStatusHadir(status)) return AppColors.successInk;
  if (v == 'tidak_wajib') return AppColors.fade;
  if (v == 'tidak_lengkap') {
    return ThemeStore.light
        ? const Color(0xFFC2410C)
        : const Color(0xFFFB923C);
  }
  if (v == 'alfa' || v == 'belum') return AppColors.roseInk;
  return AppColors.fade;
}

({int hadir, int alfa, int terlambat}) mhsStatsHarian(
  List<Map<String, dynamic>> rows,
) {
  var hadir = 0;
  var alfa = 0;
  var terlambat = 0;
  for (final row in rows) {
    final st = row['status']?.toString();
    if (mhsStatusHadir(st)) {
      hadir++;
      if (mhsStatusTerlambat(row)) terlambat++;
    } else if (mhsStatusAlfa(st)) {
      alfa++;
    }
  }
  return (hadir: hadir, alfa: alfa, terlambat: terlambat);
}

int mhsCountHadirBulanIni(List<Map<String, dynamic>> harian) {
  final now = DateTime.now();
  return harian.where((row) {
    final d = mhsParseDate(row['tanggal']?.toString());
    return d != null &&
        d.year == now.year &&
        d.month == now.month &&
        mhsStatusHadir(row['status']?.toString());
  }).length;
}

List<Map<String, dynamic>> mhsFilterHarianStudentView(
  List<Map<String, dynamic>> harian,
) {
  return harian
      .where((row) => row['status']?.toString().toLowerCase() != 'tidak_wajib')
      .toList();
}

List<Map<String, dynamic>> mhsFilterHarianByMonth(
  List<Map<String, dynamic>> harian,
  DateTime month,
) {
  final y = month.year;
  final m = month.month;
  return harian.where((row) {
    final d = mhsParseDate(row['tanggal']?.toString());
    return d != null && d.year == y && d.month == m;
  }).toList()..sort((a, b) {
    final da = mhsParseDate(a['tanggal']?.toString());
    final db = mhsParseDate(b['tanggal']?.toString());
    if (da == null || db == null) return 0;
    return db.compareTo(da);
  });
}

List<Map<String, dynamic>> mhsFilterHarianByDay(
  List<Map<String, dynamic>> harian,
  DateTime day,
) {
  final key = DateTime(day.year, day.month, day.day);
  return harian.where((row) {
    final d = mhsParseDate(row['tanggal']?.toString());
    if (d == null) return false;
    return DateTime(d.year, d.month, d.day) == key;
  }).toList();
}

List<Map<String, dynamic>> mhsFilterHarianByPeriod(
  List<Map<String, dynamic>> harian,
  String filter,
) {
  final now = DateTime.now();
  return harian.where((row) {
    final d = mhsParseDate(row['tanggal']?.toString());
    if (d == null) return false;
    if (filter == '7') {
      return !d.isBefore(now.subtract(const Duration(days: 7)));
    }
    if (filter == '30') {
      return !d.isBefore(now.subtract(const Duration(days: 30)));
    }
    if (filter == 'bulan') {
      return d.year == now.year && d.month == now.month;
    }
    return true;
  }).toList()..sort((a, b) {
    final da = mhsParseDate(a['tanggal']?.toString());
    final db = mhsParseDate(b['tanggal']?.toString());
    if (da == null || db == null) return 0;
    return db.compareTo(da);
  });
}

/// Status kehadiran pada satu tanggal (null = tidak ada data).
String? mhsDayAttendanceKind(List<Map<String, dynamic>> harian, DateTime day) {
  final key = DateTime(day.year, day.month, day.day);
  final rows = harian.where((row) {
    final d = mhsParseDate(row['tanggal']?.toString());
    if (d == null) return false;
    final dk = DateTime(d.year, d.month, d.day);
    return dk == key;
  }).toList();
  if (rows.isEmpty) return null;
  if (rows.any((r) => mhsAttendanceCategory(r) == 'terlambat')) {
    return 'terlambat';
  }
  if (rows.any((r) => mhsAttendanceCategory(r) == 'hadir')) return 'hadir';
  if (rows.any((r) => mhsAttendanceCategory(r) == 'alfa')) return 'alfa';
  return 'lain';
}

Color mhsDayAttendanceColor(String? kind) {
  switch (kind) {
    case 'hadir':
      return AppColors.success;
    case 'terlambat':
      return AppColors.amber;
    case 'alfa':
      return AppColors.rose;
    default:
      return AppColors.muted;
  }
}

const _monthNamesId = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
];

/// Kalender bulan — pilih bulan/tahun + ketuk tanggal untuk filter detail.
class MhsAttendanceDateCalendar extends StatelessWidget {
  const MhsAttendanceDateCalendar({
    super.key,
    required this.harian,
    required this.focusedMonth,
    required this.onMonthChanged,
    this.selectedDay,
    this.onDaySelected,
    this.emptyHint = 'Belum ada data kehadiran untuk bulan ini.',
  });

  final List<Map<String, dynamic>> harian;
  final DateTime focusedMonth;
  final ValueChanged<DateTime> onMonthChanged;
  final DateTime? selectedDay;
  final ValueChanged<DateTime?>? onDaySelected;
  final String emptyHint;

  static List<int> yearOptionsFor(DateTime focusedMonth) {
    final now = DateTime.now().year;
    final years = <int>{now, now - 1, now - 2, focusedMonth.year};
    final list = years.toList()..sort((a, b) => b.compareTo(a));
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final monthData = mhsFilterHarianByMonth(harian, focusedMonth);
    final hasData = monthData.isNotEmpty;

    final monthStart = DateTime(focusedMonth.year, focusedMonth.month, 1);
    final daysInMonth = DateTime(
      focusedMonth.year,
      focusedMonth.month + 1,
      0,
    ).day;
    final firstWeekday = monthStart.weekday;
    final now = DateTime.now();
    const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: _MonthYearDropdown<int>(
                label: 'Bulan',
                value: focusedMonth.month,
                items: List.generate(12, (i) {
                  final m = i + 1;
                  return DropdownMenuItem(
                    value: m,
                    child: Text(
                      _monthNamesId[i],
                      overflow: TextOverflow.ellipsis,
                    ),
                  );
                }),
                onChanged: (m) {
                  if (m == null) return;
                  onMonthChanged(DateTime(focusedMonth.year, m));
                },
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _MonthYearDropdown<int>(
                label: 'Tahun',
                value: focusedMonth.year,
                items: MhsAttendanceDateCalendar.yearOptionsFor(focusedMonth)
                    .map((y) => DropdownMenuItem(value: y, child: Text('$y')))
                    .toList(),
                onChanged: (y) {
                  if (y == null) return;
                  onMonthChanged(DateTime(y, focusedMonth.month));
                },
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton(
              visualDensity: VisualDensity.compact,
              tooltip: 'Bulan sebelumnya',
              icon: const Icon(Icons.chevron_left, size: 22),
              onPressed: () => onMonthChanged(
                DateTime(focusedMonth.year, focusedMonth.month - 1),
              ),
            ),
            Text(
              DateFormat('MMMM yyyy', 'id_ID').format(focusedMonth),
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 14,
                color: AppColors.ink,
              ),
            ),
            IconButton(
              visualDensity: VisualDensity.compact,
              tooltip: 'Bulan berikutnya',
              icon: const Icon(Icons.chevron_right, size: 22),
              onPressed: () {
                final next = DateTime(
                  focusedMonth.year,
                  focusedMonth.month + 1,
                );
                if (next.isAfter(DateTime(now.year, now.month + 1))) return;
                onMonthChanged(next);
              },
            ),
          ],
        ),
        if (!hasData)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Text(
              emptyHint,
              textAlign: TextAlign.center,
              style: AppText.muted.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
        const SizedBox(height: 4),
        Row(
          children: weekdays
              .map(
                (w) => Expanded(
                  child: Center(
                    child: Text(
                      w,
                      style: AppText.tiny.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.skyDark,
                      ),
                    ),
                  ),
                ),
              )
              .toList(),
        ),
        const SizedBox(height: 6),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 7,
            mainAxisSpacing: 5,
            crossAxisSpacing: 5,
            childAspectRatio: 1.05,
          ),
          itemCount: firstWeekday - 1 + daysInMonth,
          itemBuilder: (context, index) {
            if (index < firstWeekday - 1) return const SizedBox.shrink();
            final day = index - (firstWeekday - 1) + 1;
            final date = DateTime(focusedMonth.year, focusedMonth.month, day);
            final kind = mhsDayAttendanceKind(harian, date);
            final color = mhsDayAttendanceColor(kind);
            final isToday =
                date.year == now.year &&
                date.month == now.month &&
                date.day == now.day;
            final hasKind = kind != null;
            final dayKey = DateTime(date.year, date.month, date.day);
            final isSelected = selectedDay != null && selectedDay == dayKey;

            return Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: onDaySelected == null
                    ? null
                    : () {
                        if (isSelected) {
                          onDaySelected!(null);
                        } else {
                          onDaySelected!(dayKey);
                        }
                      },
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: isSelected
                        ? AppColors.primary.withValues(alpha: 0.1)
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(8),
                    border: isSelected
                        ? Border.all(
                            color: AppColors.primary.withValues(alpha: 0.45),
                            width: 1,
                          )
                        : (isToday
                              ? Border.all(
                                  color: AppColors.primary.withValues(
                                    alpha: 0.25,
                                  ),
                                  width: 1,
                                )
                              : null),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        '$day',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: isSelected || isToday || hasKind
                              ? FontWeight.w800
                              : FontWeight.w600,
                          color: isSelected
                              ? AppColors.primary
                              : AppColors.ink,
                        ),
                      ),
                      if (hasKind) ...[
                        const SizedBox(height: 3),
                        Container(
                          width: 5,
                          height: 5,
                          decoration: BoxDecoration(
                            color: color,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            );
          },
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 12,
          runSpacing: 6,
          alignment: WrapAlignment.center,
          children: [
            _CalendarLegendDot(color: AppColors.success, label: 'Hadir'),
            _CalendarLegendDot(color: AppColors.amber, label: 'Terlambat'),
            _CalendarLegendDot(color: AppColors.rose, label: 'Alfa'),
          ],
        ),
      ],
    );
  }
}

class _MonthYearDropdown<T> extends StatelessWidget {
  const _MonthYearDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;

  @override
  Widget build(BuildContext context) {
    final panel = AppColors.softPanelOf(context);
    final border = AppColors.borderOf(context);
    final ink = AppColors.ink;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: AppText.tiny.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(
            color: panel,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: border),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<T>(
              value: value,
              isExpanded: true,
              isDense: true,
              borderRadius: BorderRadius.circular(10),
              dropdownColor: AppColors.surfaceOf(context),
              iconEnabledColor: AppColors.fade,
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 13,
                color: ink,
              ),
              items: items
                  .map(
                    (item) => DropdownMenuItem<T>(
                      value: item.value,
                      child: DefaultTextStyle(
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                          color: ink,
                        ),
                        child: item.child ?? const SizedBox.shrink(),
                      ),
                    ),
                  )
                  .toList(),
              onChanged: onChanged,
            ),
          ),
        ),
      ],
    );
  }
}

class _CalendarLegendDot extends StatelessWidget {
  const _CalendarLegendDot({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(label, style: AppText.tiny.copyWith(fontWeight: FontWeight.w700)),
      ],
    );
  }
}

String _plainAnnouncementText(String? raw) {
  if (raw == null || raw.isEmpty) return '-';
  return raw.replaceAll(RegExp(r'<[^>]*>'), '').trim();
}

/// Hapus prefix internal [LOKER:MESIN:SLOT] dari judul pengumuman loker.
String stripLockerAnnouncementRef(String? raw) {
  if (raw == null || raw.isEmpty) return '';
  return raw.replaceFirst(RegExp(r'^\[LOKER:[^\]]+\]\s*'), '').trim();
}

bool _isLockerAnnouncement(Map<String, dynamic> item) {
  final type = item['type']?.toString() ?? '';
  if (type == 'locker_notification') return true;
  final judul = item['judul']?.toString() ?? '';
  return judul.startsWith('[LOKER:');
}

bool isNotificationExpired(Map<String, dynamic> item) {
  final raw = item['expires_at']?.toString();
  if (raw == null || raw.isEmpty) return false;
  final expires = DateTime.tryParse(raw);
  if (expires == null) return false;
  return !expires.isAfter(DateTime.now());
}

DateTime? _notificationSortAt(Map<String, dynamic> item) {
  final raw = item['created_at']?.toString();
  if (raw == null || raw.isEmpty) return null;
  return DateTime.tryParse(raw);
}

/// Hanya notifikasi absensi yang belum dibaca dan belum kedaluwarsa.
bool isRelevantNotification(Map<String, dynamic> item) {
  if (_isLockerAnnouncement(item)) return false;
  if (isNotificationExpired(item)) return false;
  return item['is_read'] != true;
}

List<Map<String, dynamic>> filterRelevantNotifications(
  Iterable<Map<String, dynamic>> items,
) {
  final rows = items
      .where(isRelevantNotification)
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
  rows.sort((a, b) {
    final bt = _notificationSortAt(b);
    final at = _notificationSortAt(a);
    if (bt == null && at == null) return 0;
    if (bt == null) return 1;
    if (at == null) return -1;
    return bt.compareTo(at);
  });
  return rows;
}

class _NotificationGroupMeta {
  const _NotificationGroupMeta({
    required this.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
  });

  final String key;
  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
}

_NotificationGroupMeta _notificationGroupMeta(Map<String, dynamic> item) {
  final type = item['type']?.toString() ?? 'announcement';
  if (type == 'suggestion_reply') {
    return const _NotificationGroupMeta(
      key: 'reply',
      title: 'Balasan Saran',
      subtitle: 'Respons admin atas masukan Anda',
      icon: Icons.support_agent_rounded,
      color: AppColors.teal,
    );
  }
  return const _NotificationGroupMeta(
    key: 'announcement',
    title: 'Pengumuman Admin',
    subtitle: 'Info resmi dari laboran/admin',
    icon: Icons.campaign_rounded,
    color: AppColors.blue,
  );
}

String formatAnnouncementTitle(Map<String, dynamic> item) {
  final stripped = stripLockerAnnouncementRef(item['judul']?.toString());
  return stripped.isEmpty ? 'Pengumuman' : stripped;
}

String formatAnnouncementBody(Map<String, dynamic> item) {
  final raw = _plainAnnouncementText(item['isi']?.toString());
  if (raw == '-' || raw.isEmpty) return '-';

  if (raw.toLowerCase().startsWith('catatan:')) {
    return raw;
  }

  final catatanMatch = RegExp(
    r'[Cc]atatan:\s*"?([^"\n]+)"?',
    caseSensitive: false,
  ).firstMatch(raw);
  if (catatanMatch != null) {
    final note = catatanMatch.group(1)?.trim();
    if (note != null && note.isNotEmpty) {
      return 'catatan: "$note"';
    }
  }

  return raw;
}

Color _prioColor(String? prioritas) {
  switch (prioritas?.toLowerCase()) {
    case 'urgent':
      return AppColors.rose;
    case 'penting':
      return AppColors.amber;
    case 'balasan':
      return AppColors.teal;
    default:
      return AppColors.violet;
  }
}

Color mhsChartColorForScore(double y) {
  if (y >= 1.5) return AppColors.amber;
  if (y >= 0.5) return AppColors.success;
  return AppColors.rose;
}

String mhsChartLabelForScore(double y) {
  if (y >= 1.5) return 'Terlambat';
  if (y >= 0.5) return 'Hadir';
  return 'Alfa';
}

/// Grafik area halus — titik & area hijau / kuning / merah per status.
class MhsModernAttendanceLineChart extends StatelessWidget {
  const MhsModernAttendanceLineChart({
    super.key,
    required this.harian,
    this.period = '7',
    this.height = 200,
    this.emptyHint = 'Belum ada data kehadiran untuk periode ini.',
  });

  final List<Map<String, dynamic>> harian;
  final String period;
  final double height;
  final String emptyHint;

  int get _dayWindow {
    if (period == 'bulan' || period == '30') return 30;
    if (period == '7') return 7;
    return 7;
  }

  double _dayScore(DateTime day) {
    final rows = harian.where((row) {
      final d = mhsParseDate(row['tanggal']?.toString());
      return d != null &&
          d.year == day.year &&
          d.month == day.month &&
          d.day == day.day;
    });
    if (rows.isEmpty) return 0;
    if (rows.any(mhsStatusTerlambat)) return 2;
    if (rows.any((r) => mhsStatusHadir(r['status']?.toString()))) return 1;
    if (rows.any((r) => mhsStatusAlfa(r['status']?.toString()))) return 0;
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final days = _dayWindow;
    final now = DateTime.now();
    final start = DateTime(
      now.year,
      now.month,
      now.day,
    ).subtract(Duration(days: days - 1));
    final labels = <String>[];
    final spots = <FlSpot>[];

    for (var i = 0; i < days; i++) {
      final day = start.add(Duration(days: i));
      spots.add(FlSpot(i.toDouble(), _dayScore(day)));
      if (days <= 7) {
        labels.add(DateFormat('E', 'id_ID').format(day).substring(0, 3));
      } else if (i == 0 || i == days - 1 || i % 5 == 0) {
        labels.add('${day.day}');
      } else {
        labels.add('');
      }
    }

    final hasData = harian.any(
      (r) => mhsParseDate(r['tanggal']?.toString()) != null,
    );
    if (!hasData) {
      return SizedBox(
        height: height,
        child: Center(
          child: Text(
            emptyHint,
            textAlign: TextAlign.center,
            style: AppText.muted.copyWith(fontWeight: FontWeight.w600),
          ),
        ),
      );
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          height: height,
          child: LineChart(
            LineChartData(
              minX: 0,
              maxX: (labels.length - 1).toDouble(),
              minY: -0.15,
              maxY: 2.15,
              gridData: FlGridData(
                show: true,
                drawVerticalLine: false,
                horizontalInterval: 1,
                getDrawingHorizontalLine: (_) => FlLine(
                  color: AppColors.skyLight.withValues(alpha: 0.85),
                  strokeWidth: 1,
                ),
              ),
              borderData: FlBorderData(show: false),
              titlesData: FlTitlesData(
                topTitles: const AxisTitles(
                  sideTitles: SideTitles(showTitles: false),
                ),
                rightTitles: const AxisTitles(
                  sideTitles: SideTitles(showTitles: false),
                ),
                leftTitles: AxisTitles(
                  sideTitles: SideTitles(
                    showTitles: true,
                    reservedSize: 36,
                    interval: 1,
                    getTitlesWidget: (v, _) {
                      if (v < 0.3) {
                        return Text(
                          'Alfa',
                          style: AppText.tiny.copyWith(
                            fontSize: 9,
                            color: AppColors.rose,
                            fontWeight: FontWeight.w800,
                          ),
                        );
                      }
                      if (v > 0.7 && v < 1.3) {
                        return Text(
                          'Hadir',
                          style: AppText.tiny.copyWith(
                            fontSize: 9,
                            color: AppColors.success,
                            fontWeight: FontWeight.w800,
                          ),
                        );
                      }
                      if (v > 1.7) {
                        return Text(
                          'Telat',
                          style: AppText.tiny.copyWith(
                            fontSize: 9,
                            color: AppColors.amber,
                            fontWeight: FontWeight.w800,
                          ),
                        );
                      }
                      return const SizedBox.shrink();
                    },
                  ),
                ),
                bottomTitles: AxisTitles(
                  sideTitles: SideTitles(
                    showTitles: true,
                    interval: 1,
                    getTitlesWidget: (v, meta) {
                      final i = v.round();
                      if (i < 0 || i >= labels.length || labels[i].isEmpty) {
                        return const SizedBox.shrink();
                      }
                      return Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          labels[i],
                          style: AppText.tiny.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.muted,
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
              lineTouchData: LineTouchData(
                handleBuiltInTouches: true,
                touchTooltipData: LineTouchTooltipData(
                  getTooltipColor: (_) => AppColors.card,
                  tooltipBorder: BorderSide(color: AppColors.skyLight),
                  getTooltipItems: (touched) {
                    return touched.map((bar) {
                      final x = bar.x.round();
                      final day = start.add(Duration(days: x));
                      final dateStr = DateFormat(
                        'dd/MM/yyyy',
                        'id_ID',
                      ).format(day);
                      final status = mhsChartLabelForScore(bar.y);
                      final c = mhsChartColorForScore(bar.y);
                      return LineTooltipItem(
                        '$dateStr\n$status',
                        TextStyle(
                          color: c,
                          fontWeight: FontWeight.w900,
                          fontSize: 11,
                        ),
                      );
                    }).toList();
                  },
                ),
              ),
              lineBarsData: [
                for (var i = 1; i < spots.length; i++)
                  LineChartBarData(
                    spots: [spots[i - 1], spots[i]],
                    isCurved: true,
                    curveSmoothness: 0.42,
                    color: mhsChartColorForScore(spots[i].y),
                    barWidth: 3,
                    isStrokeCapRound: true,
                    dotData: const FlDotData(show: false),
                    belowBarData: BarAreaData(
                      show: true,
                      gradient: LinearGradient(
                        colors: [
                          mhsChartColorForScore(
                            spots[i].y,
                          ).withValues(alpha: 0.3),
                          mhsChartColorForScore(
                            spots[i].y,
                          ).withValues(alpha: 0.02),
                        ],
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                      ),
                    ),
                  ),
                LineChartBarData(
                  spots: spots,
                  isCurved: true,
                  curveSmoothness: 0.42,
                  color: Colors.transparent,
                  barWidth: 0,
                  dotData: FlDotData(
                    show: true,
                    getDotPainter: (spot, percent, bar, index) {
                      final c = mhsChartColorForScore(spot.y);
                      return FlDotCirclePainter(
                        radius: 6,
                        color: c,
                        strokeWidth: 2.5,
                        strokeColor: Colors.white,
                      );
                    },
                  ),
                  belowBarData: BarAreaData(show: false),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _ChartLegendDot(color: AppColors.success, label: 'Hadir'),
            const SizedBox(width: 14),
            _ChartLegendDot(color: AppColors.amber, label: 'Terlambat'),
            const SizedBox(width: 14),
            _ChartLegendDot(color: AppColors.rose, label: 'Alfa'),
          ],
        ),
      ],
    );
  }
}

class _ChartLegendDot extends StatelessWidget {
  const _ChartLegendDot({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(label, style: AppText.tiny.copyWith(fontWeight: FontWeight.w800)),
      ],
    );
  }
}

/// Grafik batang rekap status: Hadir / Alfa / Terlambat.
class MhsHadirStatusChart extends StatelessWidget {
  const MhsHadirStatusChart({
    super.key,
    required this.harian,
    this.period = '7',
    this.focusedMonth,
    this.height = 156,
    this.emptyHint = 'Belum ada data kehadiran untuk periode ini.',
  });

  final List<Map<String, dynamic>> harian;
  final String period;
  final DateTime? focusedMonth;
  final double height;
  final String emptyHint;

  bool get _isMonth => period == 'bulan' || period == '30';

  /// Hanya data di scope periode + bulan kalender. Tidak menarik bulan lain.
  List<Map<String, dynamic>> _rowsInScope() {
    final month = focusedMonth ?? DateTime.now();

    if (_isMonth) {
      return mhsFilterHarianByMonth(harian, month);
    }

    final now = DateTime.now();
    final sameMonth = month.year == now.year && month.month == now.month;

    if (sameMonth) {
      // Minggu berjalan di bulan ini (7 hari terakhir).
      final start = DateTime(
        now.year,
        now.month,
        now.day,
      ).subtract(const Duration(days: 6));
      return harian.where((row) {
        final d = mhsParseDate(row['tanggal']?.toString());
        if (d == null) return false;
        final day = DateTime(d.year, d.month, d.day);
        return !day.isBefore(start) && !day.isAfter(now);
      }).toList();
    }

    // Kalender di bulan lain + filter Minggu → 7 hari terakhir di bulan itu saja.
    final lastDay = DateTime(month.year, month.month + 1, 0);
    final rawStart = DateTime(
      lastDay.year,
      lastDay.month,
      lastDay.day,
    ).subtract(const Duration(days: 6));
    final monthStart = DateTime(month.year, month.month, 1);
    final rangeStart = rawStart.isBefore(monthStart) ? monthStart : rawStart;

    return harian.where((row) {
      final d = mhsParseDate(row['tanggal']?.toString());
      if (d == null) return false;
      final day = DateTime(d.year, d.month, d.day);
      return !day.isBefore(rangeStart) && !day.isAfter(lastDay);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final rows = _rowsInScope();

    var hadir = 0;
    var alfa = 0;
    var terlambat = 0;
    for (final row in rows) {
      final cat = mhsAttendanceCategory(row);
      if (cat == 'terlambat') {
        terlambat++;
      } else if (cat == 'hadir') {
        hadir++;
      } else if (cat == 'alfa') {
        alfa++;
      }
    }
    final values = [hadir.toDouble(), alfa.toDouble(), terlambat.toDouble()];
    final maxY = values.fold<double>(0, (a, b) => a > b ? a : b);
    final chartMax = maxY <= 0 ? 4.0 : (maxY + 1).clamp(4.0, 99.0);

    if (rows.isEmpty) {
      return SizedBox(
        height: height,
        child: Center(
          child: Text(
            emptyHint,
            textAlign: TextAlign.center,
            style: AppText.muted.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.mutedOf(context),
            ),
          ),
        ),
      );
    }

    const labels = ['Hadir', 'Alfa', 'Terlambat'];
    const colors = [AppColors.success, AppColors.rose, AppColors.amber];

    return SizedBox(
      height: height,
      child: BarChart(
        BarChartData(
          maxY: chartMax,
          minY: 0,
          alignment: BarChartAlignment.spaceAround,
          gridData: FlGridData(
            show: true,
            drawVerticalLine: false,
            horizontalInterval:
                chartMax <= 4 ? 1 : (chartMax / 4).ceilToDouble(),
            getDrawingHorizontalLine: (_) => FlLine(
              color: AppColors.mutedOf(context).withValues(alpha: 0.18),
              strokeWidth: 1,
            ),
          ),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            topTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
            rightTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
            leftTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 28,
                interval: chartMax <= 4 ? 1 : (chartMax / 4).ceilToDouble(),
                getTitlesWidget: (v, _) {
                  if (v < 0 || v > chartMax) return const SizedBox.shrink();
                  if (v != v.roundToDouble()) return const SizedBox.shrink();
                  return Text(
                    '${v.toInt()}',
                    style: AppText.tiny.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.mutedOf(context),
                    ),
                  );
                },
              ),
            ),
            bottomTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 28,
                getTitlesWidget: (v, _) {
                  final i = v.toInt();
                  if (i < 0 || i > 2) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      labels[i],
                      style: AppText.tiny.copyWith(
                        fontWeight: FontWeight.w800,
                        color: colors[i],
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
          barTouchData: BarTouchData(
            touchTooltipData: BarTouchTooltipData(
              getTooltipColor: (_) => AppColors.surfaceOf(context),
              getTooltipItem: (group, groupIndex, rod, rodIndex) {
                final i = group.x;
                final raw = values[i].toInt();
                return BarTooltipItem(
                  '${labels[i]}\n$raw hari',
                  TextStyle(
                    color: AppColors.onSurfaceOf(context),
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                );
              },
            ),
          ),
          barGroups: [
            for (var i = 0; i < 3; i++)
              BarChartGroupData(
                x: i,
                barRods: [
                  BarChartRodData(
                    toY: values[i] <= 0 ? 0.15 : values[i],
                    width: 36,
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(10),
                    ),
                    color: values[i] <= 0
                        ? colors[i].withValues(alpha: 0.28)
                        : colors[i],
                    backDrawRodData: BackgroundBarChartRodData(
                      show: true,
                      toY: chartMax,
                      color: AppColors.softPanelOf(context),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

/// Bubble pengumuman — ikon speaker di dalam kartu, margin seragam.
class AnnouncementBubble extends StatelessWidget {
  const AnnouncementBubble({
    super.key,
    required this.item,
    this.onTap,
    this.compact = false,
  });

  final Map<String, dynamic> item;
  final VoidCallback? onTap;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    if (_isLockerAnnouncement(item)) return const SizedBox.shrink();
    final unread = item['is_read'] != true;
    final type = item['type']?.toString() ?? 'announcement';
    final isReply = type == 'suggestion_reply';
    final prio = item['prioritas']?.toString();
    final prioClr = _prioColor(prio);
    final icon = isReply
        ? Icons.support_agent_rounded
        : Icons.campaign_rounded;
    final pesanAsli = item['pesan_asli']?.toString();

    return Padding(
      padding: EdgeInsets.only(bottom: compact ? 10 : 12),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Ink(
            decoration: BoxDecoration(
              color: unread
                  ? AppColors.softPanelOf(context)
                  : AppColors.surfaceOf(context),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: unread
                    ? AppColors.blue.withValues(alpha: 0.35)
                    : AppCardDecoration.borderColor,
              ),
              boxShadow: AppCardDecoration.shadow,
            ),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          prioClr.withValues(alpha: 0.9),
                          AppColors.purple.withValues(alpha: 0.75),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(icon, color: Colors.white, size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                formatAnnouncementTitle(item),
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 14,
                                  color: AppColors.onSurfaceOf(context),
                                ),
                              ),
                            ),
                            if (unread)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 3,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.rose.withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: const Text(
                                  'Baru',
                                  style: TextStyle(
                                    color: AppColors.rose,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                          ],
                        ),
                        if (prio != null && prio.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: prioClr.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              isReply ? 'BALASAN' : prio.toUpperCase(),
                              style: TextStyle(
                                color: prioClr,
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ),
                        ],
                        if (isReply &&
                            pesanAsli != null &&
                            pesanAsli.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text(
                            'Saran Anda:',
                            style: TextStyle(
                              color: AppColors.muted.withValues(alpha: 0.95),
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            _plainAnnouncementText(pesanAsli),
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: AppColors.textPrimary.withValues(
                                alpha: 0.78,
                              ),
                              fontSize: 12.5,
                              height: 1.35,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                            decoration: BoxDecoration(
                              color: AppColors.teal.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: AppColors.teal.withValues(alpha: 0.25),
                              ),
                            ),
                            child: RichText(
                              text: TextSpan(
                                style: TextStyle(
                                  color: AppColors.textPrimary.withValues(
                                    alpha: 0.92,
                                  ),
                                  fontSize: compact ? 12.5 : 13.5,
                                  height: 1.45,
                                ),
                                children: [
                                  TextSpan(
                                    text: 'Respons admin: ',
                                    style: TextStyle(
                                      color: AppColors.teal,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  TextSpan(
                                    text: _plainAnnouncementText(
                                      item['isi']?.toString(),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ] else ...[
                          const SizedBox(height: 8),
                          Text(
                            formatAnnouncementBody(item),
                            style: TextStyle(
                              color: AppColors.onSurfaceOf(context)
                                  .withValues(alpha: 0.88),
                              height: 1.45,
                              fontSize: compact ? 12.5 : 13.5,
                            ),
                          ),
                        ],
                        const SizedBox(height: 8),
                        RichText(
                          text: TextSpan(
                            style: AppText.tiny.copyWith(height: 1.3),
                            children: [
                              TextSpan(
                                text: item['penulis']?.toString() ?? 'Admin',
                                style: TextStyle(
                                  color: AppColors.isLight(context)
                                      ? AppColors.primary
                                      : AppColors.skyDark,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 12,
                                ),
                              ),
                              TextSpan(
                                text:
                                    ' · ${formatDateTime(item['created_at']?.toString())}',
                                style: TextStyle(
                                  color: AppColors.mutedOf(context),
                                  fontWeight: FontWeight.w600,
                                  fontSize: 11,
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
          ),
        ),
      ),
    );
  }
}

class MhsAttendanceInsight extends StatefulWidget {
  const MhsAttendanceInsight({super.key, required this.harian});

  final List<Map<String, dynamic>> harian;

  @override
  State<MhsAttendanceInsight> createState() => _MhsAttendanceInsightState();
}

class _MhsAttendanceInsightState extends State<MhsAttendanceInsight> {
  /// Default bulan agar grafik selaras kalender yang dibuka.
  String _period = 'bulan';
  DateTime _focusedMonth = DateTime.now();

  Set<DateTime> get _hadirDays {
    final set = <DateTime>{};
    for (final row in widget.harian) {
      if (!mhsStatusHadir(row['status']?.toString())) continue;
      final d = mhsParseDate(row['tanggal']?.toString());
      if (d == null) continue;
      set.add(DateTime(d.year, d.month, d.day));
    }
    return set;
  }

  Set<DateTime> get _alfaDays {
    final set = <DateTime>{};
    for (final row in widget.harian) {
      if (!mhsStatusAlfa(row['status']?.toString())) continue;
      final d = mhsParseDate(row['tanggal']?.toString());
      if (d == null) continue;
      set.add(DateTime(d.year, d.month, d.day));
    }
    return set;
  }

  String get _chartPeriod => _period == 'bulan' ? '30' : '7';

  @override
  Widget build(BuildContext context) {
    final monthStart = DateTime(_focusedMonth.year, _focusedMonth.month, 1);
    final daysInMonth = DateTime(
      _focusedMonth.year,
      _focusedMonth.month + 1,
      0,
    ).day;
    final firstWeekday = monthStart.weekday;
    final now = DateTime.now();

    return GlassCard(
      variant: 2,
      accent: AppColors.violet,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Kehadiran & Kalender',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: AppColors.onSurfaceOf(context),
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(
                  color: AppColors.softPanelOf(context),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: _period,
                    isDense: true,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AppColors.violet,
                    ),
                    items: const [
                      DropdownMenuItem(value: 'minggu', child: Text('Minggu')),
                      DropdownMenuItem(value: 'bulan', child: Text('Bulan')),
                    ],
                    onChanged: (v) {
                      if (v != null) setState(() => _period = v);
                    },
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            _period == 'bulan'
                ? 'Rekap ${DateFormat('MMMM yyyy', 'id_ID').format(_focusedMonth)}'
                : 'Rekap minggu · ${DateFormat('MMMM yyyy', 'id_ID').format(_focusedMonth)}',
            style: AppText.tiny.copyWith(
              color: AppColors.teal,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          MhsHadirStatusChart(
            harian: widget.harian,
            period: _chartPeriod,
            focusedMonth: _focusedMonth,
            height: 148,
            emptyHint: _period == 'bulan'
                ? 'Belum ada kehadiran di ${DateFormat('MMMM yyyy', 'id_ID').format(_focusedMonth)}.'
                : 'Belum ada kehadiran di minggu periode ini.',
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(
                icon: const Icon(Icons.chevron_left),
                onPressed: () => setState(() {
                  _focusedMonth = DateTime(
                    _focusedMonth.year,
                    _focusedMonth.month - 1,
                  );
                }),
              ),
              Text(
                DateFormat('MMMM yyyy', 'id_ID').format(_focusedMonth),
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14,
                ),
              ),
              IconButton(
                icon: const Icon(Icons.chevron_right),
                onPressed: () => setState(() {
                  _focusedMonth = DateTime(
                    _focusedMonth.year,
                    _focusedMonth.month + 1,
                  );
                }),
              ),
            ],
          ),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 7,
              mainAxisSpacing: 4,
              crossAxisSpacing: 4,
              childAspectRatio: 1.15,
            ),
            itemCount: firstWeekday - 1 + daysInMonth,
            itemBuilder: (context, index) {
              if (index < firstWeekday - 1) return const SizedBox.shrink();
              final day = index - (firstWeekday - 1) + 1;
              final date = DateTime(
                _focusedMonth.year,
                _focusedMonth.month,
                day,
              );
              final dayKey = DateTime(date.year, date.month, date.day);
              final hadir = _hadirDays.contains(dayKey);
              final alfa = _alfaDays.contains(dayKey);
              final isToday =
                  date.year == now.year &&
                  date.month == now.month &&
                  date.day == now.day;
              return Container(
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: hadir
                      ? AppColors.teal.withValues(alpha: 0.22)
                      : (alfa
                            ? AppColors.rose.withValues(alpha: 0.18)
                            : (isToday
                                  ? AppColors.blue.withValues(alpha: 0.12)
                                  : Colors.transparent)),
                  borderRadius: BorderRadius.circular(8),
                  border: isToday
                      ? Border.all(color: AppColors.blue.withValues(alpha: 0.5))
                      : null,
                ),
                child: Text(
                  '$day',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: hadir || alfa
                        ? FontWeight.w900
                        : FontWeight.w500,
                    color: hadir
                        ? AppColors.teal
                        : (alfa ? AppColors.rose : AppColors.muted),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

void showStudentAccessDialog(BuildContext context, Map<String, dynamic> user) {
  final items = <_AccessRow>[
    _AccessRow(
      'Peran',
      'Mahasiswa · Portal mobile',
      Icons.school_outlined,
      true,
    ),
    _AccessRow(
      'Status akun',
      user['is_active'] == true
          ? 'Aktif & terverifikasi'
          : 'Menunggu verifikasi',
      Icons.verified_user_outlined,
      user['is_active'] == true,
    ),
    _AccessRow(
      'Absensi lab',
      'Absensi lab (hadir / alfa / terlambat)',
      Icons.fingerprint,
      user['finger_id'] != null,
    ),
    _AccessRow(
      'Pengumuman',
      'Baca info dari admin laboratorium',
      Icons.campaign_outlined,
      true,
    ),
    _AccessRow(
      'Profil',
      user['profile_completed'] == true
          ? 'Kelola foto & data diri'
          : 'Lengkapi profil terlebih dahulu',
      Icons.person_outline,
      user['profile_completed'] == true,
    ),
  ];

  showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (ctx) {
      return Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        backgroundColor: AppColors.card,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 22, 22, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: AppColors.blue.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.workspace_premium_rounded,
                    color: AppColors.blue,
                    size: 30,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  'Hak Akses Mahasiswa',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Ringkasan fitur yang boleh Anda gunakan di aplikasi mobile.',
                  textAlign: TextAlign.center,
                  style: AppText.muted,
                ),
                const SizedBox(height: 16),
                ...items.map(
                  (row) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        Icon(
                          row.ok ? Icons.check_circle : Icons.lock_outline,
                          color: row.ok ? AppColors.teal : AppColors.muted,
                          size: 20,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                row.title,
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13,
                                  color: AppColors.ink,
                                ),
                              ),
                              Text(row.subtitle, style: AppText.tiny),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () => Navigator.pop(ctx),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.blue,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text('Mengerti'),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}

void showStudentSubscriptionSheet(
  BuildContext context,
  Map<String, dynamic> user,
) {
  showStudentAccessDialog(context, user);
}


class _AccessRow {
  const _AccessRow(this.title, this.subtitle, this.icon, this.ok);
  final String title;
  final String subtitle;
  final IconData icon;
  final bool ok;
}

void showAnnouncementsPopup(
  BuildContext context, {
  required ApiClient api,
  ValueChanged<int>? onUnreadChanged,
  VoidCallback? onOpenAll,
}) {
  showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (ctx) {
      return _NotificationsPopupSheet(
        api: api,
        onUnreadChanged: onUnreadChanged,
        onOpenAll: onOpenAll,
      );
    },
  );
}

class _NotificationsPopupSheet extends StatefulWidget {
  const _NotificationsPopupSheet({
    required this.api,
    this.onUnreadChanged,
    this.onOpenAll,
  });

  final ApiClient api;
  final ValueChanged<int>? onUnreadChanged;
  final VoidCallback? onOpenAll;

  @override
  State<_NotificationsPopupSheet> createState() =>
      _NotificationsPopupSheetState();
}

class _NotificationsPopupSheetState extends State<_NotificationsPopupSheet> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.get(
        '/mobile/notifications?per_page=30&page=1&unread_only=1',
        auth: true,
      );
      if (!mounted) return;
      final rows = List<Map<String, dynamic>>.from(
        response['data'] as List? ?? [],
      );
      setState(() {
        _items = filterRelevantNotifications(rows);
        _loading = false;
      });
      widget.onUnreadChanged?.call(
        (response['unread_count'] as num?)?.toInt() ?? 0,
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _markOne(Map<String, dynamic> item) async {
    if (item['is_read'] == true || item['id'] == null) return;
    final type = item['type']?.toString() ?? 'announcement';
    try {
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
      if (!mounted) return;
      setState(() {
        _items = _items
            .map((e) => e['id'] == item['id'] ? {...e, 'is_read': true} : e)
            .where((e) => isRelevantNotification(e))
            .toList();
      });
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.rose),
      );
    }
  }

  Future<void> _markAll() async {
    try {
      await Future.wait([
        widget.api.post('/mobile/announcements/read-all', auth: true),
        widget.api.post('/mobile/suggestions/replies/read-all', auth: true),
      ]);
      if (!mounted) return;
      setState(() => _items = []);
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.rose),
      );
    }
  }

  List<(_NotificationGroupMeta meta, List<Map<String, dynamic>> items)>
  _groupedItems() {
    final map = <String, List<Map<String, dynamic>>>{};
    final order = <String>[];
    for (final item in _items) {
      final meta = _notificationGroupMeta(item);
      map.putIfAbsent(meta.key, () {
        order.add(meta.key);
        return <Map<String, dynamic>>[];
      });
      map[meta.key]!.add(item);
    }
    return order.map((key) {
      final sample = map[key]!.first;
      return (_notificationGroupMeta(sample), map[key]!);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final unread = _items.length;
    final groups = _groupedItems();

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: unread == 0 ? 0.42 : 0.68,
      minChildSize: 0.34,
      maxChildSize: 0.92,
      builder: (_, controller) {
        final light = AppColors.isLight(context);
        return Container(
          decoration: BoxDecoration(
            color: (light ? AppColors.cardLight : AppColors.card)
                .withValues(alpha: 0.98),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
            border: Border(
              top: BorderSide(
                color: light
                    ? AppColors.borderLight
                    : Colors.white.withValues(alpha: 0.1),
              ),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: light ? 0.18 : 0.55),
                blurRadius: 32,
                offset: const Offset(0, -10),
              ),
            ],
          ),
          child: Column(
            children: [
              const SizedBox(height: 10),
              Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: light
                      ? AppColors.borderLight
                      : Colors.white.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 16, 10, 10),
                child: Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            AppColors.blue.withValues(alpha: 0.9),
                            AppColors.purple.withValues(alpha: 0.75),
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(
                        Icons.notifications_active_rounded,
                        color: Colors.white,
                        size: 24,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Notifikasi Baru',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              color: AppColors.onSurfaceOf(context),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            unread > 0
                                ? '$unread info belum dibaca'
                                : 'Semua sudah Anda baca',
                            style: TextStyle(
                              color: AppColors.mutedOf(context),
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (unread > 0)
                      Container(
                        margin: const EdgeInsets.only(right: 4),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.rose.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: AppColors.rose.withValues(alpha: 0.35),
                          ),
                        ),
                        child: Text(
                          '$unread',
                          style: const TextStyle(
                            color: AppColors.rose,
                            fontWeight: FontWeight.w900,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
              ),
              if (unread > 0)
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 0, 18, 8),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Hanya menampilkan info aktif & belum dibaca',
                          style: AppText.muted.copyWith(fontSize: 11.5),
                        ),
                      ),
                      TextButton(
                        onPressed: _markAll,
                        child: const Text(
                          'Tandai semua',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.blue,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              Expanded(
                child: _loading
                    ? const Center(
                        child: CircularProgressIndicator(
                          color: AppColors.blue,
                          strokeWidth: 2.5,
                        ),
                      )
                    : _error != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                _error!,
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: AppColors.rose),
                              ),
                              const SizedBox(height: 12),
                              TextButton(
                                onPressed: _load,
                                child: const Text('Coba lagi'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : groups.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(28),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 64,
                                height: 64,
                                decoration: BoxDecoration(
                                  color: AppColors.softPanel,
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                    color: AppCardDecoration.borderColor,
                                  ),
                                ),
                                child: Icon(
                                  Icons.notifications_off_outlined,
                                  color: AppColors.muted.withValues(alpha: 0.8),
                                  size: 30,
                                ),
                              ),
                              const SizedBox(height: 14),
                              const Text(
                                'Tidak ada notifikasi baru',
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 16,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                'Pengumuman lama atau yang sudah dibaca tidak ditampilkan di sini.',
                                textAlign: TextAlign.center,
                                style: AppText.muted.copyWith(height: 1.4),
                              ),
                            ],
                          ),
                        ),
                      )
                    : ListView(
                        controller: controller,
                        padding: const EdgeInsets.fromLTRB(14, 4, 14, 12),
                        children: [
                          for (final group in groups) ...[
                            _NotificationGroupHeader(
                              meta: group.$1,
                              count: group.$2.length,
                            ),
                            ...group.$2.map(
                              (item) => AnnouncementBubble(
                                item: item,
                                compact: true,
                                onTap: () => _markOne(item),
                              ),
                            ),
                            const SizedBox(height: 8),
                          ],
                        ],
                      ),
              ),
              if (widget.onOpenAll != null)
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
                    child: SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () {
                          Navigator.pop(context);
                          widget.onOpenAll!();
                        },
                        icon: const Icon(Icons.history_rounded, size: 18),
                        label: const Text('Lihat semua notifikasi'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.blue,
                          side: BorderSide(
                            color: AppColors.blue.withValues(alpha: 0.45),
                          ),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _NotificationGroupHeader extends StatelessWidget {
  const _NotificationGroupHeader({required this.meta, required this.count});

  final _NotificationGroupMeta meta;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 10, 4, 8),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: meta.color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(meta.icon, color: meta.color, size: 17),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  meta.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 13.5,
                  ),
                ),
                Text(
                  meta.subtitle,
                  style: AppText.muted.copyWith(fontSize: 11),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: meta.color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              '$count',
              style: TextStyle(
                color: meta.color,
                fontWeight: FontWeight.w900,
                fontSize: 11,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Bar navigasi bawah: Beranda, Absensi, Profil.
class StitchBottomNav extends StatelessWidget {
  const StitchBottomNav({
    super.key,
    required this.selectedIndex,
    required this.unreadCount,
    required this.onSelected,
    this.profilePhotoUrl,
    this.profileInitial = 'M',
  });

  final int selectedIndex;
  final int unreadCount;
  final ValueChanged<int> onSelected;
  final String? profilePhotoUrl;
  final String profileInitial;

  @override
  Widget build(BuildContext context) {
    final navTheme = Theme.of(context).bottomNavigationBarTheme;
    final selectedColor = navTheme.selectedItemColor ?? AppColors.primary;
    final unselectedColor = navTheme.unselectedItemColor ?? AppColors.muted;
    final bg = navTheme.backgroundColor ??
        (AppColors.isLight(context) ? Colors.white : AppColors.navBar);

    // Tinggi bar tetap; isi tidak boleh terpotong.
    return Material(
      color: bg,
      elevation: 8,
      clipBehavior: Clip.none,
      shadowColor: Colors.black.withValues(
        alpha: AppColors.isLight(context) ? 0.12 : 0.5,
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 64,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(2, 6, 2, 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: _StitchNavItem(
                    icon: Icons.home_outlined,
                    selectedIcon: Icons.home_rounded,
                    label: 'Home',
                    selectedColor: selectedColor,
                    unselectedColor: unselectedColor,
                    selected: selectedIndex == 0,
                    onTap: () => onSelected(0),
                  ),
                ),
                Expanded(
                  child: _StitchNavItem(
                    icon: Icons.fingerprint,
                    selectedIcon: Icons.fingerprint,
                    label: 'Absensi',
                    selectedColor: selectedColor,
                    unselectedColor: unselectedColor,
                    selected: selectedIndex == 1,
                    onTap: () => onSelected(1),
                  ),
                ),
                Expanded(
                  child: _ProfileNavItem(
                    selected: selectedIndex == 2,
                    selectedColor: selectedColor,
                    unselectedColor: unselectedColor,
                    photoUrl: profilePhotoUrl,
                    initial: profileInitial,
                    onTap: () => onSelected(2),
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

class _ProfileNavItem extends StatelessWidget {
  const _ProfileNavItem({
    required this.selected,
    required this.selectedColor,
    required this.unselectedColor,
    required this.onTap,
    this.photoUrl,
    this.initial = 'M',
  });

  final bool selected;
  final Color selectedColor;
  final Color unselectedColor;
  final VoidCallback onTap;
  final String? photoUrl;
  final String initial;

  @override
  Widget build(BuildContext context) {
    final hasPhoto = photoUrl != null && photoUrl!.isNotEmpty;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        height: 54,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.end,
          children: [
            Container(
              width: 28,
              height: 28,
              padding: const EdgeInsets.all(1.5),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: selected ? selectedColor : Colors.transparent,
                  width: 1.5,
                ),
              ),
              child: CircleAvatar(
                backgroundColor: selectedColor.withValues(alpha: 0.15),
                backgroundImage: hasPhoto ? NetworkImage(photoUrl!) : null,
                child: hasPhoto
                    ? null
                    : Text(
                        initial,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w900,
                          color: selected ? selectedColor : unselectedColor,
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Profil',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                height: 1.1,
                color: selected ? selectedColor : unselectedColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StitchNavItem extends StatelessWidget {
  const _StitchNavItem({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.selectedColor,
    required this.unselectedColor,
    required this.selected,
    required this.onTap,
    this.badge = 0,
  });

  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final Color selectedColor;
  final Color unselectedColor;
  final bool selected;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        height: 54,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.end,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(
                  selected ? selectedIcon : icon,
                  color: selected ? selectedColor : unselectedColor,
                  size: 24,
                ),
                if (badge > 0)
                  Positioned(
                    right: -4,
                    top: -2,
                    child: Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: AppColors.rose,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                height: 1.1,
                color: selected ? selectedColor : unselectedColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Header halaman dalam app (logo mini + judul + notifikasi).
class AppTopBar extends StatelessWidget {
  const AppTopBar({
    super.key,
    required this.user,
    this.unreadCount = 0,
    this.onNotifications,
  });

  final Map<String, dynamic> user;
  final int unreadCount;
  final VoidCallback? onNotifications;

  @override
  Widget build(BuildContext context) {
    final photoUrl = user['profile_photo_url']?.toString();
    final hasPhoto =
        photoUrl != null &&
        photoUrl.isNotEmpty &&
        (photoUrl.startsWith('http://') || photoUrl.startsWith('https://'));
    final initial =
        (user['nama_lengkap']?.toString().trim().isNotEmpty ?? false)
        ? user['nama_lengkap'].toString().trim()[0].toUpperCase()
        : 'M';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
      decoration: BoxDecoration(
        gradient: AppGradients.aurora,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(20)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: Colors.white.withValues(alpha: 0.12),
            backgroundImage: hasPhoto ? NetworkImage(photoUrl) : null,
            child: !hasPhoto
                ? Text(
                    initial,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  )
                : null,
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Smart Lab',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
                fontSize: 16,
                letterSpacing: 0.4,
              ),
            ),
          ),
          if (onNotifications != null)
            Material(
              color: Colors.white.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
              child: InkWell(
                onTap: onNotifications,
                borderRadius: BorderRadius.circular(12),
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: BadgeIcon(
                    icon: Icons.notifications_outlined,
                    count: unreadCount,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
