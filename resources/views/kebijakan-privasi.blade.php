<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi — SmartBrail</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; background: #0b1220; color: #d5dbe7; line-height: 1.7; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
        h1 { color: #fff; font-size: 1.8rem; margin-bottom: 6px; }
        h2 { color: #fff; font-size: 1.15rem; margin: 32px 0 10px; }
        p, li { font-size: .95rem; color: #b7c0d1; }
        ul { padding-left: 22px; }
        li { margin: 4px 0; }
        .meta { color: #7f8aa0; font-size: .85rem; margin-bottom: 28px; }
        .back { display: inline-block; margin-top: 40px; color: #7aa2ff; text-decoration: none; font-size: .9rem; }
        .back:hover { text-decoration: underline; }
        .card { background: #111a2e; border: 1px solid #1e2a44; border-radius: 12px; padding: 14px 18px; margin-top: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Kebijakan Privasi &amp; Perlindungan Data</h1>
    <p class="meta">SmartBrail — Sistem Informasi Absensi &amp; Akses Laboratorium · Pembaruan terakhir: November 2026</p>

    <p>Halaman ini menjelaskan data apa yang diproses SmartBrail, untuk apa, berapa lama disimpan, dan hak Anda sebagai subjek data sesuai Undang-Undang Perlindungan Data Pribadi (UU PDP) No. 27 Tahun 2022.</p>

    <h2>Data yang diproses</h2>
    <ul>
        <li><b>Data akun</b> — nama lengkap, NITK/NIM, email, peran (mahasiswa/dosen/laboran/admin), kelas &amp; tim PBL.</li>
        <li><b>Data kehadiran</b> — waktu tap masuk/pulang, status kelengkapan &amp; ketepatan, identitas mesin.</li>
        <li><b>Data akses ruangan</b> — pengajuan &amp; persetujuan pemakaian ruangan, riwayat buka/tolak pintu.</li>
        <li><b>Kredensial akses</b> — nomor slot sidik jari (finger_id), UID kartu RFID, dan PIN (disimpan sebagai hash).</li>
        <li><b>Template sidik jari</b> — disimpan di sensor pada mesin, disinkronkan antar-mesin melalui jalur terproteksi; <b>bukan</b> gambar sidik jari dan tidak diekspos di portal web.</li>
        <li><b>Data teknis</b> — log aplikasi, sesi login, preferensi tampilan &amp; notifikasi.</li>
    </ul>

    <h2>Tujuan pemrosesan</h2>
    <ul>
        <li>Pencatatan kehadiran perkuliahan/praktikum di laboratorium.</li>
        <li>Kontrol akses pintu ruangan sesuai persetujuan booking dan jadwal petugas.</li>
        <li>Pelaporan &amp; rekapitulasi untuk dosen, laboran, dan administrasi.</li>
        <li>Keamanan sistem: audit akses, pencegahan penyalahgunaan akun.</li>
    </ul>

    <h2>Dasar pemrosesan</h2>
    <ul>
        <li>Pelaksanaan tugas/kepentingan sah institusi pendidikan (administrasi akademik &amp; keamanan fasilitas).</li>
        <li><b>Persetujuan eksplisit</b> untuk data biometrik (template sidik jari) — data kategori sensitif menurut UU PDP. Persetujuan diminta saat perekaman sidik jari dan dapat dicabut; konsekuensinya akses berbasis sidik jari dinonaktifkan.</li>
    </ul>

    <h2>Berapa lama data disimpan</h2>
    <ul>
        <li>Data akun &amp; kehadiran: selama masa studi/penugasan aktif ditambah periode retensi akademik yang ditetapkan institusi.</li>
        <li>Log akses pintu: sesuai kebutuhan audit keamanan.</li>
        <li>Cadangan (backup): maksimal 14 hari, lalu dihapus bergilir.</li>
        <li>Template sidik jari: dihapus dari sensor saat akun dinonaktifkan atau persetujuan dicabut.</li>
    </ul>

    <h2>Hak Anda</h2>
    <div class="card">
        <ul>
            <li>Meminta salinan/akses atas data pribadi Anda.</li>
            <li>Meminta koreksi data yang keliru.</li>
            <li>Meminta penghapusan data (sepanjang tidak diwajibkan disimpan oleh aturan akademik).</li>
            <li>Mencabut persetujuan pemrosesan data biometrik.</li>
            <li>Mengajukan keberatan atas pemrosesan tertentu.</li>
        </ul>
        <p style="margin-top:8px">Ajukan melalui laboran/admin prodi — permintaan diverifikasi identitasnya lalu diproses sesuai ketentuan.</p>
    </div>

    <h2>Keamanan</h2>
    <ul>
        <li>Sandi &amp; PIN disimpan sebagai hash (tidak dapat dibaca balik).</li>
        <li>Akses data dibatasi peran (role-based) dan dicatat.</li>
        <li>Komunikasi situs memakai HTTPS; komunikasi mesin memakai API key per-perangkat.</li>
        <li>Cadangan data disimpan di tiga lokasi terpisah dengan retensi terbatas.</li>
    </ul>

    <h2>Pihak ketiga</h2>
    <ul>
        <li>Notifikasi cadangan melalui bot Telegram internal (tidak memuat data pengguna).</li>
        <li>Layanan monitoring uptime/error — hanya status teknis sistem, tanpa data pribadi.</li>
        <li>Backup terenkripsi kanal ke Google Drive milik institusi.</li>
        <li>Kecuali diwajibkan hukum, data tidak dijual atau dibagikan ke pihak luar.</li>
    </ul>

    <a class="back" href="{{ route('beranda') }}">&larr; Kembali ke beranda</a>
</div>
</body>
</html>
