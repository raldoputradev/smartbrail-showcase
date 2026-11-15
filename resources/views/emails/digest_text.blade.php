SmartBrail — Ringkasan Harian {{ $tanggalLabel }}

Yth. {{ $namaLengkap }}, berikut ringkasan aktivitas Anda hari ini:

@foreach($rows as $row)
- {{ $row['label'] }}: {{ $row['value'] }}
@endforeach
- Notifikasi belum dibaca: {{ $unreadCount > 0 ? $unreadCount . ' notifikasi' : 'Semua sudah dibaca' }}

Buka portal: {{ $portalUrl }}

Anda menerima email ini karena Ringkasan Email Harian aktif di Profil → Preferensi.
