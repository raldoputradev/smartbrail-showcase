<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartBrail — Ringkasan Harian</title>
</head>
<body style="margin:0;padding:0;background:#0f1419;font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(180deg,#0f1419 0%,#1a2332 100%);padding:40px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.35);">
    <tr>
        <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 55%,#0ea5e9 100%);padding:28px;text-align:center;">
            <div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:12px;padding:8px 14px;margin-bottom:10px;">
                <span style="font-size:12px;font-weight:700;letter-spacing:1.5px;color:#e0f2fe;text-transform:uppercase;">SmartBrail</span>
            </div>
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">Ringkasan Harian</h1>
            <p style="margin:6px 0 0;color:#dbeafe;font-size:13px;">{{ $tanggalLabel }}</p>
        </td>
    </tr>
    <tr>
        <td style="padding:26px 28px 8px;">
            <p style="margin:0 0 16px;color:#1e293b;font-size:14.5px;line-height:1.6;">
                Yth. <strong>{{ $namaLengkap }}</strong>, berikut ringkasan aktivitas Anda hari ini.
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                @foreach($rows as $i => $row)
                <tr>
                    <td style="padding:11px 16px;font-size:12.5px;font-weight:700;color:#64748b;width:42%;background:{{ $i % 2 === 0 ? '#f8fafc' : '#ffffff' }};border-bottom:1px solid #eef2f7;">{{ $row['label'] }}</td>
                    <td style="padding:11px 16px;font-size:13px;color:#1e293b;background:{{ $i % 2 === 0 ? '#f8fafc' : '#ffffff' }};border-bottom:1px solid #eef2f7;">{{ $row['value'] }}</td>
                </tr>
                @endforeach
                <tr>
                    <td style="padding:11px 16px;font-size:12.5px;font-weight:700;color:#64748b;background:{{ count($rows) % 2 === 0 ? '#f8fafc' : '#ffffff' }};">Notifikasi belum dibaca</td>
                    <td style="padding:11px 16px;font-size:13px;font-weight:700;color:{{ $unreadCount > 0 ? '#dc2626' : '#059669' }};background:{{ count($rows) % 2 === 0 ? '#f8fafc' : '#ffffff' }};">{{ $unreadCount > 0 ? $unreadCount . ' notifikasi' : 'Semua sudah dibaca' }}</td>
                </tr>
            </table>
            <div style="text-align:center;margin:24px 0 8px;">
                <a href="{{ $portalUrl }}" style="display:inline-block;background:linear-gradient(135deg,#2563eb,#0ea5e9);color:#ffffff;text-decoration:none;padding:13px 26px;border-radius:10px;font-size:14px;font-weight:700;box-shadow:0 8px 20px rgba(37,99,235,.35);">
                    Buka Portal SmartBrail
                </a>
            </div>
            <p style="margin:20px 0 0;color:#94a3b8;font-size:11.5px;line-height:1.6;text-align:center;">
                Anda menerima email ini karena Ringkasan Email Harian aktif di Profil → Preferensi.
                Matikan kapan saja dari halaman yang sama.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 28px 22px;text-align:center;color:#94a3b8;font-size:11px;border-top:1px solid #f1f5f9;">
            SmartBrail — Sistem Informasi Manajemen BRAIL, Politeknik Negeri Batam
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
