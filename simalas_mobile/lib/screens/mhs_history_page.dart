part of '../main.dart';

/// Arsip kehadiran lab — Menu Utama → Riwayat.
class MhsHistoryPage extends StatefulWidget {
  const MhsHistoryPage({
    super.key,
    required this.api,
    required this.user,
    this.initialTab = 0,
  });

  final ApiClient api;
  final Map<String, dynamic> user;
  final int initialTab;

  @override
  State<MhsHistoryPage> createState() => _MhsHistoryPageState();
}

class _MhsHistoryPageState extends State<MhsHistoryPage>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late final TabController _tabs;
  late Future<_MhsHistoryPayload> _future;

  String _absensiFilter = 'semua';
  DateTimeRange? _dateRange;
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
    _tabs = TabController(
      length: 1,
      vsync: this,
      initialIndex: 0,
    );
    _future = _load();
    _arm();
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
      _refresh();
    }
  }

  void _arm() {
    _poll?.cancel();
    if (_hidden) return;
    final seconds = _fails == 0 ? 20 : (20 * (1 << _fails.clamp(0, 3)));
    _poll = Timer(Duration(seconds: seconds > 60 ? 60 : seconds), _refresh);
  }

  Future<void> _refresh() async {
    if (_hidden || _busy) return;
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

  @override
  void dispose() {
    _generation++;
    _poll?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _tabs.dispose();
    super.dispose();
  }

  Future<_MhsHistoryPayload> _load() async {
    final absensi = await widget.api.get(
      '/mobile/me/absensi/snapshot?days=365',
      auth: true,
    );

    return _MhsHistoryPayload(
      harian: mhsFilterHarianStudentView(
        List<Map<String, dynamic>>.from(
          absensi['ringkasan_harian'] as List? ?? [],
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _filterAbsensi(List<Map<String, dynamic>> rows) {
    var out = rows;
    if (_dateRange != null) {
      final start = DateTime(
        _dateRange!.start.year,
        _dateRange!.start.month,
        _dateRange!.start.day,
      );
      final end = DateTime(
        _dateRange!.end.year,
        _dateRange!.end.month,
        _dateRange!.end.day,
        23,
        59,
        59,
      );
      out = out.where((row) {
        final d = mhsParseDate(row['tanggal']?.toString());
        if (d == null) return false;
        return !d.isBefore(start) && !d.isAfter(end);
      }).toList();
    }
    if (_absensiFilter == 'izin') {
      return out
          .where((r) => r['status']?.toString().toLowerCase() == 'izin')
          .toList();
    }
    if (_absensiFilter == 'hadir') {
      return out.where((r) {
        final st = r['status']?.toString().toLowerCase();
        if (st == 'izin' || st == 'sakit') return false;
        return mhsAttendanceCategory(r) == 'hadir';
      }).toList();
    }
    return mhsFilterHarianByStatus(out, _absensiFilter);
  }

  Future<void> _pickDateRange() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 1),
      initialDateRange:
          _dateRange ??
          DateTimeRange(start: now.subtract(const Duration(days: 30)), end: now),
      helpText: 'Filter tanggal absensi',
      saveText: 'Terapkan',
    );
    if (picked != null) {
      setState(() => _dateRange = picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MhsHomePalette.body(context),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const PageHeader(
                    title: 'Arsip',
                    subtitle: 'Arsip kehadiran lab, lengkap dengan filter tanggal.',
                    icon: Icons.history_rounded,
                    color: AppColors.primary,
                  ),
                  if (_refreshNote != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      _refreshNote!,
                      style: const TextStyle(color: Color(0xFFBE123C), fontSize: 12),
                    ),
                  ],
                ],
              ),
            ),
          ),
          Expanded(
            child: FutureBuilder<_MhsHistoryPayload>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(
                    child: CircularProgressIndicator(
                      color: MhsHomePalette.headerBlue,
                    ),
                  );
                }
                if (snapshot.hasError) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            snapshot.error is ApiException
                                ? snapshot.error.toString()
                                : 'Gagal memuat riwayat.',
                            textAlign: TextAlign.center,
                            style: AppText.muted,
                          ),
                          const SizedBox(height: 14),
                          FilledButton(
                            onPressed: () {
                                  _refresh();
                                },
                            style: FilledButton.styleFrom(
                              backgroundColor: MhsHomePalette.headerBlue,
                            ),
                            child: const Text('Coba lagi'),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                final data = snapshot.data!;
                final absensiRows = _filterAbsensi(data.harian);

                return RefreshIndicator(
                  color: MhsHomePalette.headerBlue,
                  onRefresh: _refresh,
                  child: TabBarView(
                    controller: _tabs,
                    children: [
                      _HistoryAbsensiTab(
                        rows: absensiRows,
                        filter: _absensiFilter,
                        dateRange: _dateRange,
                        onFilterChanged: (v) =>
                            setState(() => _absensiFilter = v),
                        onPickDateRange: _pickDateRange,
                        onClearDateRange: () =>
                            setState(() => _dateRange = null),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _MhsHistoryPayload {
  const _MhsHistoryPayload({required this.harian});

  final List<Map<String, dynamic>> harian;
}

class _HistoryAbsensiTab extends StatelessWidget {
  const _HistoryAbsensiTab({
    required this.rows,
    required this.filter,
    required this.dateRange,
    required this.onFilterChanged,
    required this.onPickDateRange,
    required this.onClearDateRange,
  });

  final List<Map<String, dynamic>> rows;
  final String filter;
  final DateTimeRange? dateRange;
  final ValueChanged<String> onFilterChanged;
  final VoidCallback onPickDateRange;
  final VoidCallback onClearDateRange;

  static const _filters = <String, String>{
    'semua': 'Semua',
    'hadir': 'Hadir',
    'izin': 'Izin',
    'alfa': 'Alfa',
    'terlambat': 'Terlambat',
  };

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (final e in _filters.entries)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(e.value),
                    selected: filter == e.key,
                    onSelected: (_) => onFilterChanged(e.key),
                    selectedColor: const Color(0xFFBAE6FD),
                    labelStyle: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 12.5,
                      color: filter == e.key
                          ? const Color(0xFF0369A1)
                          : AppColors.ink,
                    ),
                    backgroundColor: AppColors.panel,
                    side: BorderSide(color: AppColors.line),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            TextButton.icon(
              onPressed: onPickDateRange,
              icon: const Icon(Icons.date_range_outlined, size: 18),
              label: Text(
                dateRange == null
                    ? 'Filter tanggal'
                    : '${DateFormat('d MMM y', 'id').format(dateRange!.start)}'
                          ' – '
                          '${DateFormat('d MMM y', 'id').format(dateRange!.end)}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            if (dateRange != null)
              TextButton(
                onPressed: onClearDateRange,
                child: const Text('Hapus'),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          '${rows.length} catatan',
          style: AppText.tiny.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 12),
        if (rows.isEmpty)
          Container(
            padding: const EdgeInsets.all(20),
            decoration: AppCardDecoration.box(context, radius: 14),
            child: Text(
              'Tidak ada data absensi untuk filter ini.',
              style: AppText.muted,
              textAlign: TextAlign.center,
            ),
          )
        else
          ...rows.map((row) {
            final status = row['status']?.toString();
            final label = mhsStatusLabel(status, row: row);
            final color = mhsStatusColor(status, row: row);
            final tanggal = row['tanggal']?.toString();
            final sesi = row['sesi']?.toString() ?? row['shift']?.toString();
            final masuk = _prettyClock(row['jam_masuk']?.toString());
            final pulang = _prettyClock(row['jam_pulang']?.toString());

            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Container(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
                decoration: AppCardDecoration.box(context, radius: 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.14),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(Icons.fact_check_outlined, color: color),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                tanggal != null
                                    ? formatDateFull(tanggal)
                                    : 'Absensi',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 15,
                                ),
                              ),
                              if (sesi != null && sesi.isNotEmpty) ...[
                                const SizedBox(height: 3),
                                Text('Sesi $sesi', style: AppText.tiny),
                              ],
                            ],
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            label,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: color,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (masuk != null || pulang != null) ...[
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _HistoryTimeChip(
                              label: 'Masuk',
                              value: masuk ?? '—',
                              icon: Icons.login_rounded,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _HistoryTimeChip(
                              label: 'Pulang',
                              value: pulang ?? '—',
                              icon: Icons.logout_rounded,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            );
          }),
      ],
    );
  }
}

String? _prettyClock(String? raw) {
  if (raw == null) return null;
  final t = raw.trim();
  if (t.isEmpty || t == '-' || t == '00:00' || t.startsWith('00:00')) {
    return null;
  }
  // Ambil HH:mm saja jika ada detik.
  final m = RegExp(r'^(\d{1,2}:\d{2})').firstMatch(t);
  return m?.group(1) ?? t;
}

class _HistoryTimeChip extends StatelessWidget {
  const _HistoryTimeChip({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.softPanelOf(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.borderOf(context)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.primary),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: AppText.tiny.copyWith(
                    fontWeight: FontWeight.w700,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  value,
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 14,
                    color: AppColors.ink,
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

