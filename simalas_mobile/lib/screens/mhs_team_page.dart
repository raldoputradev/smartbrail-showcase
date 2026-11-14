part of '../main.dart';

/// Halaman Tim PBL mahasiswa — manpro, ketua, anggota, dan foto profil.
class MhsTeamPage extends StatefulWidget {
  const MhsTeamPage({super.key, required this.api, required this.user});

  final ApiClient api;
  final Map<String, dynamic> user;

  @override
  State<MhsTeamPage> createState() => _MhsTeamPageState();
}

class _MhsTeamPageState extends State<MhsTeamPage> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final teamRes = await widget.api.get('/mobile/me/pbl-team', auth: true);
    final data = Map<String, dynamic>.from(
      (teamRes['data'] as Map?) ?? const {},
    );
    data['tim_label'] = widget.user['tim'];
    return data;
  }

  String _roleLabel(String? role) {
    switch (role) {
      case 'ketua':
        return 'Ketua';
      case 'wakil':
        return 'Wakil';
      case 'anggota':
        return 'Anggota';
      case 'manpro':
        return 'Manpro';
      case 'co_manpro':
        return 'Co-Manpro';
      default:
        return role?.isNotEmpty == true ? role! : '-';
    }
  }

  List<Map<String, dynamic>> _studentMembers(Map<String, dynamic> data) {
    final fromApi = (data['members'] as List?) ?? const [];
    if (fromApi.isNotEmpty) {
      return fromApi
          .whereType<Map>()
          .map((m) => Map<String, dynamic>.from(m))
          .toList();
    }

    final out = <Map<String, dynamic>>[];
    void add(Map<String, dynamic>? m, {String? forceRole}) {
      if (m == null) return;
      final copy = Map<String, dynamic>.from(m);
      if (forceRole != null) copy['role'] = forceRole;
      out.add(copy);
    }

    add(
      data['ketua'] is Map
          ? Map<String, dynamic>.from(data['ketua'] as Map)
          : null,
    );
    add(
      data['wakil'] is Map
          ? Map<String, dynamic>.from(data['wakil'] as Map)
          : null,
    );
    for (final raw in (data['anggota'] as List?) ?? const []) {
      if (raw is Map) add(Map<String, dynamic>.from(raw));
    }
    return out;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MhsHomePalette.body(context),
      appBar: AppBar(
        backgroundColor: MhsHomePalette.headerBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Tim PBL',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(
              child: CircularProgressIndicator(color: MhsHomePalette.headerBlue),
            );
          }
          if (snapshot.hasError) {
            return _TeamErrorPane(
              message: snapshot.error is ApiException
                  ? snapshot.error.toString()
                  : 'Gagal memuat data tim.',
              onRetry: () => setState(() => _future = _load()),
            );
          }

          final data = snapshot.data ?? {};
          final hasTeam = data['has_pbl_team'] == true;
          final project = data['project'] is Map
              ? Map<String, dynamic>.from(data['project'] as Map)
              : null;
          final manpro = data['manpro_user'] is Map
              ? Map<String, dynamic>.from(data['manpro_user'] as Map)
              : null;
          final coManpro = data['co_manpro_user'] is Map
              ? Map<String, dynamic>.from(data['co_manpro_user'] as Map)
              : null;
          final members = _studentMembers(data);
          final myRole = data['role']?.toString();
          final timLabel =
              data['tim_label']?.toString() ??
              widget.user['tim']?.toString() ??
              '-';

          return RefreshIndicator(
            color: MhsHomePalette.headerBlue,
            onRefresh: () async {
              setState(() => _future = _load());
              await _future;
            },
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
              children: [
                if (!hasTeam || project == null) ...[
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: AppCardDecoration.box(context, radius: 16),
                    child: Column(
                      children: [
                        const Icon(
                          Icons.groups_outlined,
                          size: 42,
                          color: AppColors.muted,
                        ),
                        const SizedBox(height: 12),
                        const Text(
                          'Belum terhubung ke tim PBL',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 16,
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Tim di profil: $timLabel\n'
                          'Pastikan proyek PBL sudah dipilih saat daftar '
                          'atau hubungi admin/laboran.',
                          style: AppText.muted.copyWith(height: 1.45),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),
                ] else ...[
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF0369A1), Color(0xFF00AED6)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          project['nama']?.toString() ?? 'Proyek PBL',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 18,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          [
                            if ((project['kode']?.toString() ?? '').isNotEmpty)
                              'Kode ${project['kode']}',
                            if ((project['shift_sesi']?.toString() ?? '')
                                .isNotEmpty)
                              'Shift ${project['shift_sesi']}',
                            if (myRole != null)
                              'Peran Anda: ${_roleLabel(myRole)}',
                          ].join(' · '),
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.9),
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Manajer Proyek',
                    style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                  ),
                  const SizedBox(height: 10),
                  if (manpro == null &&
                      (project['manpro']?.toString() ?? '').isEmpty)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: AppCardDecoration.box(context, radius: 14),
                      child: Text(
                        'Manajer proyek belum ditetapkan.',
                        style: AppText.muted,
                        textAlign: TextAlign.center,
                      ),
                    )
                  else
                    Container(
                      decoration: AppCardDecoration.box(context, radius: 14),
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        children: [
                          _TeamPersonTile(
                            nama:
                                manpro?['nama_lengkap']?.toString() ??
                                project['manpro']?.toString() ??
                                '-',
                            subtitle: manpro?['nitk_nidn'] != null
                                ? 'NIDN ${manpro!['nitk_nidn']}'
                                : 'Dosen pengampu',
                            roleLabel: 'Manpro',
                            photoUrl: manpro?['profile_photo_url']?.toString(),
                            accent: const Color(0xFF7C3AED),
                          ),
                          if (coManpro != null) ...[
                            const Divider(height: 1, indent: 16, endIndent: 16),
                            _TeamPersonTile(
                              nama: coManpro['nama_lengkap']?.toString() ?? '-',
                              subtitle: coManpro['nitk_nidn'] != null
                                  ? 'NIDN ${coManpro['nitk_nidn']}'
                                  : 'Co-manajer proyek',
                              roleLabel: 'Co-Manpro',
                              photoUrl:
                                  coManpro['profile_photo_url']?.toString(),
                              accent: const Color(0xFF6366F1),
                            ),
                          ],
                        ],
                      ),
                    ),
                  const SizedBox(height: 18),
                  Text(
                    'Anggota Tim (${members.length})',
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (members.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: AppCardDecoration.box(context, radius: 14),
                      child: Text(
                        'Belum ada anggota terdaftar di struktur tim.',
                        style: AppText.muted,
                        textAlign: TextAlign.center,
                      ),
                    )
                  else
                    Container(
                      decoration: AppCardDecoration.box(context, radius: 14),
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        children: [
                          for (var i = 0; i < members.length; i++) ...[
                            if (i > 0)
                              const Divider(
                                height: 1,
                                indent: 16,
                                endIndent: 16,
                              ),
                            _TeamPersonTile(
                              nama:
                                  members[i]['nama_lengkap']?.toString() ?? '-',
                              subtitle: [
                                if ((members[i]['nitk_nidn']?.toString() ?? '')
                                    .isNotEmpty)
                                  'NIM ${members[i]['nitk_nidn']}',
                                if ((members[i]['kelas']?.toString() ?? '')
                                    .isNotEmpty)
                                  'Kelas ${members[i]['kelas']}',
                              ].join(' · '),
                              roleLabel: _roleLabel(
                                members[i]['role']?.toString(),
                              ),
                              photoUrl:
                                  members[i]['profile_photo_url']?.toString(),
                            ),
                          ],
                        ],
                      ),
                    ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _TeamPersonTile extends StatelessWidget {
  const _TeamPersonTile({
    required this.nama,
    required this.subtitle,
    required this.roleLabel,
    this.photoUrl,
    this.accent = AppColors.primary,
  });

  final String nama;
  final String subtitle;
  final String roleLabel;
  final String? photoUrl;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final initial = nama.isNotEmpty ? nama.characters.first.toUpperCase() : '?';
    final hasPhoto = photoUrl != null && photoUrl!.trim().isNotEmpty;

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      leading: CircleAvatar(
        radius: 22,
        backgroundColor: accent.withValues(alpha: 0.15),
        backgroundImage: hasPhoto ? NetworkImage(photoUrl!) : null,
        onBackgroundImageError: hasPhoto ? (_, _) {} : null,
        child: hasPhoto
            ? null
            : Text(
                initial,
                style: TextStyle(
                  color: accent,
                  fontWeight: FontWeight.w900,
                  fontSize: 16,
                ),
              ),
      ),
      title: Text(nama, style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle: subtitle.isEmpty
          ? null
          : Text(subtitle, style: AppText.tiny),
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: accent.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          roleLabel,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            color: accent,
          ),
        ),
      ),
    );
  }
}

class _TeamErrorPane extends StatelessWidget {
  const _TeamErrorPane({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center, style: AppText.muted),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: onRetry,
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
}
