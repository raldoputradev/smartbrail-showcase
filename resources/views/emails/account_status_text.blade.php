SmartBrail — {{ $subtitle }}

Yth. {{ $namaLengkap }},

@if($approved)
Selamat! Pendaftaran akun Anda telah DISETUJUI oleh admin laboran SmartBrail.

{{ $loginHint }}
@else
Maaf, pendaftaran akun Anda BELUM DISETUJUI oleh admin laboran SmartBrail.
@if(!empty($user->approval_note))
Alasan: {{ $user->approval_note }}
@endif

Jika Anda merasa ini kesalahan, hubungi admin laboran untuk klarifikasi.
@endif

---
Dikirim: {{ $sentAt ?? '' }} | Ref: {{ $refCode ?? '-' }}
© {{ date('Y') }} SmartBrail — Politeknik Negeri Batam
