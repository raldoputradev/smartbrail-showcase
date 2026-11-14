part of '../main.dart';

class _MhsAbsensiPayload {
  const _MhsAbsensiPayload({required this.harian});

  final List<Map<String, dynamic>> harian;
}

/// Halaman absensi â€” kalender bulan/tahun + detail kehadiran.

class StudentAttendancePage extends StatefulWidget {
  const StudentAttendancePage({
    super.key,
    required this.api,
    required this.user,
    this.onOpenHistory,
  });

  final ApiClient api;
  final Map<String, dynamic> user;
  final VoidCallback? onOpenHistory;

  @override
  State<StudentAttendancePage> createState() => _StudentAttendancePageState();
}

class _StudentAttendancePageState extends State<StudentAttendancePage>
    with WidgetsBindingObserver {
  late Future<_MhsAbsensiPayload> _future;
  late DateTime _viewMonth;
  DateTime? _selectedDay;
  Timer? _poll;
  int _generation = 0;
  int _fails = 0;
  bool _busy = false;
  bool _hidden = false;
  String? _refreshNote;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final now = DateTime.now();
    _viewMonth = DateTime(now.year, now.month);
    _future = _load();
    _arm();
  }

  @override
  void dispose() {
    _generation++;
    _poll?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final hidden = state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.paused;
    if (hidden) {
      _hidden = true;
      _generation++;
      _poll?.cancel();
      return;
    }
    if (state == AppLifecycleState.resumed) {
      _hidden = false;
      _refresh(reason: 'resume');
    }
  }

  void _arm() {
    _poll?.cancel();
    if (_hidden) return;
    final seconds = _fails == 0 ? 20 : (20 * (1 << _fails.clamp(0, 3)));
    _poll = Timer(Duration(seconds: seconds > 60 ? 60 : seconds), () {
      _refresh(reason: 'interval');
    });
  }

  Future<void> _refresh({required String reason}) async {
    if (_hidden && reason != 'manual') return;
    if (_busy) return;
    _busy = true;
    final generation = ++_generation;
    try {
      final payload = await _load();
      if (!mounted || generation != _generation) return;
      setState(() {
        _future = Future.value(payload);
        _refreshNote = null;
        _fails = 0;
      });
    } catch (_) {
      if (!mounted || generation != _generation) return;
      setState(() {
        _fails += 1;
        _refreshNote = 'Pembaruan gagal — data terakhir tetap';
      });
    } finally {
      _busy = false;
      if (mounted && generation == _generation) _arm();
    }
  }

  Future<_MhsAbsensiPayload> _load() async {
    final response = await widget.api.get(
      '/mobile/me/absensi/snapshot?days=365',
      auth: true,
    );

    return _MhsAbsensiPayload(
      harian: mhsFilterHarianStudentView(
        List<Map<String, dynamic>>.from(
          response['ringkasan_harian'] as List? ?? [],
        ),
      ),
    );
  }

  int _countForMonth(
    List<Map<String, dynamic>> harian,
    DateTime month,
    bool Function(Map<String, dynamic>) pred,
  ) {
    return mhsFilterHarianByMonth(harian, month).where(pred).length;
  }

  void _onMonthChanged(DateTime month) {
    setState(() {
      _viewMonth = DateTime(month.year, month.month);
      _selectedDay = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      bottom: false,
      child: FutureBuilder<_MhsAbsensiPayload>(
        future: _future,
        builder: (context, snapshot) {
          final loading =
              !snapshot.hasData &&
              snapshot.connectionState != ConnectionState.done;
          final harianAll = snapshot.data?.harian ?? [];
          final hadirBulan = _countForMonth(
            harianAll,
            _viewMonth,
            (r) => mhsAttendanceCategory(r) == 'hadir',
          );
          final alfaBulan = _countForMonth(
            harianAll,
            _viewMonth,
            (r) => mhsAttendanceCategory(r) == 'alfa',
          );
          final telatBulan = _countForMonth(
            harianAll,
            _viewMonth,
            (r) => mhsAttendanceCategory(r) == 'terlambat',
          );
          final enrolled = mhsIsFingerprintEnrolled(widget.user);
          final monthLabel = DateFormat(
            'MMMM yyyy',
            'id_ID',
          ).format(_viewMonth);

          return BlurPageLoader(
            loading: loading,
            message: 'Memuat absensiâ€¦',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_refreshNote != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 0),
                    child: Text(
                      _refreshNote!,
                      style: const TextStyle(color: Color(0xFFBE123C), fontSize: 12),
                    ),
                  ),
                if (!enrolled)
                  const Padding(
                    padding: EdgeInsets.fromLTRB(18, 12, 18, 0),
                    child: MhsEnrollWarningBanner(),
                  ),
                Expanded(
                  child: RefreshIndicator(
                    color: AppColors.primary,
                    onRefresh: () async {
                      setState(() => _selectedDay = null);
                      await _refresh(reason: 'manual');
                    },
                    child: ListView(
                      padding: const EdgeInsets.all(18),
                      children: [
                        const PageHeader(
                          title: 'Absensi Saya',
                          subtitle:
                              'Ringkasan, grafik, dan kalender kehadiran lab. Arsip lengkap di Riwayat.',
                          icon: Icons.fact_check_outlined,
                          color: AppColors.primary,
                        ),
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Expanded(
                              child: SummaryCard(
                                label: 'Hadir',
                                value: '$hadirBulan',
                                icon: Icons.check_circle_outline,
                                color: AppColors.success,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: SummaryCard(
                                label: 'Alfa',
                                value: '$alfaBulan',
                                icon: Icons.event_busy_outlined,
                                color: AppColors.rose,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: SummaryCard(
                                label: 'Terlambat',
                                value: '$telatBulan',
                                icon: Icons.schedule_outlined,
                                color: AppColors.amber,
                              ),
                            ),
                          ],
                        ),
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(
                            'Ringkasan: $monthLabel',
                            textAlign: TextAlign.center,
                            style: AppText.tiny.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        GlassCard(
                          accent: AppColors.violet,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Grafik kehadiran',
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 16,
                                  color: AppColors.onSurfaceOf(context),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Rekap $monthLabel Â· Hadir / Alfa / Terlambat',
                                style: AppText.tiny.copyWith(
                                  color: AppColors.teal,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 10),
                              MhsHadirStatusChart(
                                harian: harianAll,
                                period: '30',
                                focusedMonth: _viewMonth,
                                height: 160,
                                emptyHint:
                                    'Belum ada kehadiran di $monthLabel.',
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                        GlassCard(
                          accent: AppColors.primary,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  IconBubble(
                                    icon: Icons.calendar_month_rounded,
                                    color: AppColors.primary,
                                    size: 36,
                                  ),
                                  const SizedBox(width: 10),
                                  const Expanded(
                                    child: Text(
                                      'Kalender kehadiran',
                                      style: TextStyle(
                                        fontWeight: FontWeight.w900,
                                        fontSize: 16,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Text(
                                'Pilih bulan & tahun Â· titik warna = status hari itu',
                                style: AppText.tiny,
                              ),
                              const SizedBox(height: 12),
                              MhsAttendanceDateCalendar(
                                harian: harianAll,
                                focusedMonth: _viewMonth,
                                onMonthChanged: _onMonthChanged,
                                selectedDay: _selectedDay,
                                onDaySelected: (day) =>
                                    setState(() => _selectedDay = day),
                              ),
                            ],
                          ),
                        ),
                        if (snapshot.hasError) ...[
                          const SizedBox(height: 14),
                          GlassCard(
                            child: Text(
                              snapshot.error.toString(),
                              style: const TextStyle(color: AppColors.rose),
                            ),
                          ),
                        ],
                        const SizedBox(height: 14),
                        _OpenHistoryCta(
                          title: 'Arsip absensi lengkap',
                          subtitle:
                              'Filter tanggal, status, dan detail jam masuk/pulang ada di menu Riwayat.',
                          icon: Icons.history_rounded,
                          onTap: widget.onOpenHistory,
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

/// Pintasan ke menu Riwayat (arsip digabung di sana).
class _OpenHistoryCta extends StatelessWidget {
  const _OpenHistoryCta({
    required this.title,
    required this.subtitle,
    required this.icon,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
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
                child: Icon(icon, color: AppColors.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: TextStyle(
                        fontSize: 12.5,
                        height: 1.35,
                        color: AppColors.fade,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                color: AppColors.fade,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
