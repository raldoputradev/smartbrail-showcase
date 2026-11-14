/// Validasi format email mahasiswa (selaras dengan backend Laravel).
class StudentEmailValidation {
  const StudentEmailValidation({
    required this.isValid,
    this.message,
  });

  final bool isValid;
  final String? message;
}

const _blockedDisposableDomains = [
  'mailinator.com',
  'guerrillamail.com',
  'tempmail.com',
  '10minutemail.com',
  'yopmail.com',
  'throwaway.email',
  'trashmail.com',
];

final RegExp _studentEmailPattern = RegExp(
  r'^[a-z0-9._%+\-]+@[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?)+$',
);

StudentEmailValidation validateStudentEmail(String raw) {
  final email = raw.trim().toLowerCase();

  if (email.isEmpty) {
    return const StudentEmailValidation(
      isValid: false,
      message: 'Email wajib diisi.',
    );
  }

  if (!_studentEmailPattern.hasMatch(email)) {
    return const StudentEmailValidation(
      isValid: false,
      message: 'Format email tidak valid.',
    );
  }

  final parts = email.split('@');
  if (parts.length != 2) {
    return const StudentEmailValidation(
      isValid: false,
      message: 'Format email tidak valid.',
    );
  }

  final local = parts[0];
  final domain = parts[1];

  if (local.length < 2) {
    return const StudentEmailValidation(
      isValid: false,
      message: 'Email terlalu pendek atau tidak wajar.',
    );
  }

  if (!domain.contains('.') || domain.endsWith('.')) {
    return const StudentEmailValidation(
      isValid: false,
      message: 'Domain email tidak valid.',
    );
  }

  final tld = domain.split('.').last;
  if (tld.length < 2) {
    return const StudentEmailValidation(
      isValid: false,
      message: 'Domain email tidak valid.',
    );
  }

  for (final blocked in _blockedDisposableDomains) {
    if (domain == blocked || domain.endsWith('.$blocked')) {
      return const StudentEmailValidation(
        isValid: false,
        message: 'Gunakan email aktif (bukan layanan sementara).',
      );
    }
  }

  return const StudentEmailValidation(isValid: true);
}
