<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartBrail — {{ $subtitle }}</title>
</head>
<body style="margin:0;padding:0;background:#0f1419;font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(180deg,#0f1419 0%,#1a2332 100%);padding:40px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.35);">
    <tr>
        <td style="background:linear-gradient(135deg,{{ $approved ? '#065f46 0%,#10b981 55%,#34d399' : '#7f1d1d 0%,#dc2626 55%,#f87171' }} 100%);padding:32px 28px;text-align:center;">
            <div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:12px;padding:10px 16px;margin-bottom:12px;">
                <span style="font-size:13px;font-weight:700;letter-spacing:1.5px;color:#e0f2fe;text-transform:uppercase;">SmartBrail</span>
            </div>
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;line-height:1.3;">{{ $headline }}</h1>
            <p style="margin:8px 0 0;color:#dbeafe;font-size:14px;line-height:1.5;">{{ $subtitle }}</p>
        </td>
    </tr>
    <tr>
        <td style="padding:28px 28px 8px;">
            <p style="margin:0 0 8px;color:#1e293b;font-size:15px;line-height:1.6;">
                Yth. <strong>{{ $namaLengkap }}</strong>,
            </p>
            @if($approved)
                <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.65;">
                    Selamat! Pendaftaran akun Anda telah <strong>disetujui</strong> oleh admin laboran SmartBrail.
                    {{ $loginHint }}
                </p>
                <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:16px 18px;margin:0 0 8px;">
                    <p style="margin:0;color:#065f46;font-size:13px;font-weight:700;">Langkah selanjutnya</p>
                    <p style="margin:8px 0 0;color:#047857;font-size:12px;line-height:1.6;">{{ $loginHint }}</p>
                </div>
            @else
                <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.65;">
                    Maaf, pendaftaran akun Anda <strong>belum disetujui</strong> oleh admin laboran SmartBrail.
                    @if(!empty($user->approval_note))
                        <br><br><strong>Alasan:</strong> {{ $user->approval_note }}
                    @endif
                </p>
                <div style="background:#fef2f2;border-left:4px solid #ef4444;border-radius:0 8px 8px 0;padding:12px 14px;margin:0 0 8px;">
                    <p style="margin:0;color:#b91c1c;font-size:12px;line-height:1.55;">
                        Jika Anda merasa ini kesalahan, hubungi admin laboran untuk klarifikasi. Anda tidak dapat login sampai akun disetujui.
                    </p>
                </div>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:8px 28px 28px;text-align:center;border-top:1px solid #f1f5f9;">
            <p style="margin:0 0 4px;color:#94a3b8;font-size:11px;">
                Dikirim: {{ $sentAt ?? '' }} &middot; Ref: {{ $refCode ?? '-' }}
            </p>
            <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.5;">
                &copy; {{ date('Y') }} SmartBrail — Smart Laboratory System, Politeknik Negeri Batam
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
