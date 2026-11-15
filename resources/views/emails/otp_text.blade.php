SmartBrail — {{ $headline }}@if(!empty($isResend)) [PENGIRIMAN ULANG]@endif

Yth. {{ $namaLengkap }},

@if(!empty($isResend))
Sesuai permintaan Anda, berikut kode verifikasi (OTP) terbaru untuk melanjutkan proses registrasi. Kode sebelumnya tidak berlaku lagi.
@else
{{ $desc }}
@endif

KODE VERIFIKASI (OTP): {{ $otpCode }}

Berlaku selama {{ $expiry }} sejak email ini dikirim.
@if(!empty($verifyUrl))

Lanjutkan verifikasi di browser:
{{ $verifyUrl }}
@endif
@if(!empty($mobileResumeHint))

Melanjutkan di aplikasi mobile:
Buka SmartBrail Mobile > tab Daftar > "Lanjutkan verifikasi OTP", masukkan email ini, lalu ketik kode OTP di atas.
@endif
@if($warning)

{{ $warning }}
@endif

Dikirim: {{ $sentAt ?? '' }}
Referensi: {{ $refCode ?? '-' }}

(c) {{ date('Y') }} SmartBrail — Smart Laboratory System, Politeknik Negeri Batam
