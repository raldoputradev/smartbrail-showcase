<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIoT SIMALAS — Atur Ulang Password</title>
</head>
<body style="margin:0;padding:0;background:#0f1419;font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(180deg,#0f1419 0%,#1a2332 100%);padding:40px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.35);">
    <tr>
        <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 55%,#0ea5e9 100%);padding:32px 28px;text-align:center;">
            <div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:12px;padding:10px 16px;margin-bottom:12px;">
                <span style="font-size:13px;font-weight:700;letter-spacing:1.5px;color:#e0f2fe;text-transform:uppercase;">AIoT SIMALAS</span>
            </div>
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;line-height:1.3;">Atur Ulang Password</h1>
            <p style="margin:8px 0 0;color:#dbeafe;font-size:14px;line-height:1.5;">Permintaan pemulihan password akun Anda</p>
        </td>
    </tr>
    <tr>
        <td style="padding:28px 28px 8px;">
            <p style="margin:0 0 8px;color:#1e293b;font-size:15px;line-height:1.6;">
                Yth. <strong>{{ $namaLengkap }}</strong>,
            </p>
            <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.65;">
                Kami menerima permintaan untuk mengatur ulang password akun SIMALAS Anda. Klik tombol di bawah untuk membuat password baru:
            </p>
            <div style="text-align:center;margin:24px 0 20px;">
                <a href="{{ $resetUrl }}" style="display:inline-block;background:linear-gradient(135deg,#2563eb,#0ea5e9);color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:10px;font-size:15px;font-weight:700;box-shadow:0 8px 20px rgba(37,99,235,.35);">
                    Atur Ulang Password
                </a>
                <p style="margin:14px 0 0;color:#64748b;font-size:12px;line-height:1.5;">
                    Tautan berlaku selama <strong style="color:#1e293b;">{{ $ttlMinutes }} menit</strong> dan hanya dapat dipakai satu kali.
                </p>
            </div>
            <div style="background:#f1f5f9;border-radius:10px;padding:12px 14px;margin:16px 0;">
                <p style="margin:0 0 6px;color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Jika tombol tidak berfungsi, salin tautan ini:</p>
                <p style="margin:0;color:#2563eb;font-size:11px;word-break:break-all;line-height:1.5;">{{ $resetUrl }}</p>
            </div>
            <div style="background:#fef2f2;border-left:4px solid #ef4444;border-radius:0 8px 8px 0;padding:12px 14px;margin:16px 0 0;">
                <p style="margin:0;color:#b91c1c;font-size:12px;line-height:1.55;">Apabila Anda tidak mengajukan permintaan ini, abaikan email ini — password akun Anda tidak berubah. Jangan bagikan tautan ini kepada siapa pun.</p>
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding:8px 28px 28px;text-align:center;border-top:1px solid #f1f5f9;">
            <p style="margin:0 0 4px;color:#94a3b8;font-size:11px;">
                Dikirim: {{ $sentAt ?? '' }} &middot; Ref: {{ $refCode ?? '-' }}
            </p>
            <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.5;">
                &copy; {{ date('Y') }} AIoT SIMALAS — Smart Lab System, Politeknik Negeri Batam
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
