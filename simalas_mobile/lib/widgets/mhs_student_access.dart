part of '../main.dart';

/// Sidik jari sudah ter-enroll di mesin absensi.
bool mhsIsFingerprintEnrolled(Map<String, dynamic> user) {
  final status = user['fingerprint_status']?.toString().toLowerCase() ?? '';
  return status.contains('sudah enroll');
}

bool mhsIsAccountActive(Map<String, dynamic> user) {
  return user['is_active'] == true;
}

bool mhsNeedsEmailVerification(Map<String, dynamic> user) {
  if (user['email_verification_required'] == true) return true;
  final verified = user['email_verified_at']?.toString().trim() ?? '';
  return verified.isEmpty;
}

bool mhsNeedsEmail(Map<String, dynamic> user) {
  if (user['needs_email'] == true) return true;
  final email = user['email']?.toString().trim() ?? '';
  return email.isEmpty;
}

void mhsShowEnrollRequiredSnack(BuildContext context) {
  ScaffoldMessenger.of(context).showSnackBar(
    const SnackBar(
      content: Text(
        'Sidik jari belum terdaftar di mesin. Silakan hubungi admin laboratorium '
        'untuk proses enroll sebelum menggunakan absensi.',
      ),
      backgroundColor: AppColors.amber,
      duration: Duration(seconds: 4),
    ),
  );
}

class MhsEnrollWarningBanner extends StatelessWidget {
  const MhsEnrollWarningBanner({
    super.key,
    this.onOpenProfile,
  });

  final VoidCallback? onOpenProfile;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.amber.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onOpenProfile,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.fingerprint_outlined, color: AppColors.amber, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Sidik jari belum terdaftar',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                        color: AppColors.amber,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Fitur absensi laboratorium akan aktif setelah sidik jari '
                      'terdaftar di mesin fingerprint. Silakan hubungi admin laboratorium.',
                      style: AppText.tiny.copyWith(height: 1.35),
                    ),
                    if (onOpenProfile != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        'Lihat profil →',
                        style: AppText.tiny.copyWith(
                          color: AppColors.blue,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
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

/// Baris email — sejajar label seperti baris profil lain; font mengecil jika panjang.
class ProfileEmailRow extends StatelessWidget {
  const ProfileEmailRow({
    super.key,
    required this.user,
    required this.api,
    required this.onUserChanged,
  });

  final Map<String, dynamic> user;
  final ApiClient api;
  final void Function(Map<String, dynamic> user) onUserChanged;

  void _openVerification(BuildContext context) {
    showEmailVerificationDialog(
      context: context,
      api: api,
      user: user,
      onUserChanged: onUserChanged,
    );
  }

  double _emailFontSize(String email) {
    final len = email.length;
    if (len > 40) return 11;
    if (len > 32) return 12;
    if (len > 24) return 13;
    return 14;
  }

  @override
  Widget build(BuildContext context) {
    final verified = !mhsNeedsEmailVerification(user);
    final needsEmail = mhsNeedsEmail(user);
    final email = user['email']?.toString().trim() ?? '';
    final displayEmail = email.isNotEmpty ? email : 'Belum diisi';
    final emailStyle = TextStyle(
      fontWeight: FontWeight.w800,
      fontSize: _emailFontSize(displayEmail),
      height: 1.3,
      color: AppColors.ink,
    );

    if (verified) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Text('Email', style: AppText.label),
            const SizedBox(width: 10),
            Expanded(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Flexible(
                    child: Text(
                      displayEmail,
                      textAlign: TextAlign.center,
                      softWrap: true,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: emailStyle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Icon(
                    Icons.check_circle,
                    color: AppColors.success,
                    size: 20,
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    final actionLabel =
        needsEmail ? 'Isi & Verifikasi Email' : 'Verifikasi Email';

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 13, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text('Email', style: AppText.label),
              const SizedBox(width: 10),
              Expanded(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Flexible(
                      child: Text(
                        displayEmail,
                        textAlign: TextAlign.center,
                        softWrap: true,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: emailStyle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    const Icon(
                      Icons.warning_amber_rounded,
                      color: AppColors.amber,
                      size: 20,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 42,
            child: FilledButton.icon(
              onPressed: () => _openVerification(context),
              icon: Icon(
                needsEmail
                    ? Icons.edit_outlined
                    : Icons.verified_user_outlined,
                size: 18,
              ),
              label: Text(
                actionLabel,
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                ),
              ),
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.amber,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ProfileStatusRow extends StatelessWidget {
  const ProfileStatusRow({
    super.key,
    required this.label,
    required this.user,
  });

  final String label;
  final Map<String, dynamic> user;

  @override
  Widget build(BuildContext context) {
    final isActive = mhsIsAccountActive(user);
    final enrolled = mhsIsFingerprintEnrolled(user);

    if (label == 'Status Mahasiswa') {
      return ProfileRow(
        label: label,
        value: isActive ? 'Aktif' : (user['status_mahasiswa']?.toString() ?? 'Nonaktif'),
        trailing: isActive
            ? const Icon(Icons.check_circle, color: AppColors.success, size: 24)
            : const Icon(Icons.warning_amber_rounded, color: AppColors.amber, size: 24),
      );
    }

    if (label == 'Status Fingerprint') {
      return ProfileRow(
        label: label,
        value: enrolled ? 'Sudah Enroll' : 'Belum Enroll',
        trailing: enrolled
            ? const Icon(Icons.check_circle, color: AppColors.success, size: 24)
            : const Icon(Icons.warning_amber_rounded, color: AppColors.amber, size: 24),
      );
    }

    return ProfileRow(label: label, value: '-');
  }
}
