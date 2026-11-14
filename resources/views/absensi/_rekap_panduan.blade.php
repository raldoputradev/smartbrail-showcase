@php
    $pagi = $jadwalSesi['pagi'] ?? null;
    $malam = $jadwalSesi['malam'] ?? null;
    $fmt = fn ($t) => $t ? \Illuminate\Support\Str::of($t)->substr(0, 5) : '—';
    $tutupPagi = $pagi ? $fmt($pagi->jam_pulang) . ' (alfa tanpa tap) · tidak lengkap +' . (int) $pagi->grace_alfa_menit . ' menit' : '17:00 alfa · tidak lengkap +120 menit';
    $reportsUrl = $reportsUrl ?? null;
    $panel = $panel ?? [];
    if (!$reportsUrl && !empty($panel['is_laboran']) && Route::has('admin.reports')) {
        $reportsUrl = route('admin.reports');
    }
    if (!$reportsUrl && empty($panel['is_laboran']) && Route::has('dosen.reports')) {
        $reportsUrl = route('dosen.reports');
    }
@endphp
<details class="absensi-panduan-panel animate-in" open data-panduan-key="rekap-harian">
    <summary>
        <span>📘 Panduan Rekap Harian — konsep, status &amp; koreksi</span>
        <span style="display:flex;align-items:center;gap:8px;">
            <span class="absensi-panduan-hint">klik untuk tutup / buka</span>
            <span class="absensi-panduan-chevron" aria-hidden="true">▾</span>
        </span>
    </summary>
    <div class="card-body" style="padding-top:0;font-size:13px;line-height:1.6;color:var(--text-main);">
        @include('absensi._panduan_konsep_sistem', [
            'rekapUrl' => null,
            'reportsUrl' => $reportsUrl,
            'kebijakanLabel' => $kebijakanLabel ?? null,
        ])

        <div class="panduan-section-title">Untuk apa rekap harian?</div>
        <p class="panduan-lead">
            Menjawab: <em>“Pada tanggal ini, apakah mahasiswa X sudah hadir lengkap, alfa, atau perlu koreksi?”</em>
            Satu baris = satu mahasiswa × tanggal filter.
            @if($reportsUrl)
                Untuk tren banyak hari (jumlah tap, grafik), buka <a href="{{ $reportsUrl }}">Laporan Periodik</a> — angka total tap di sana <strong>berbeda</strong> dengan status di sini.
            @endif
        </p>

        <div class="panduan-section-title">Alur sistem (hari wajib)</div>
        <ol class="panduan-bullets" style="list-style:decimal;">
            <li>Mahasiswa <strong>enroll sidik jari</strong> di mesin → bisa tap.</li>
            <li><strong>Tap masuk</strong> (pagi: mulai {{ $fmt($pagi?->jam_mulai_tap_masuk ?? '06:30') }}) → status <em>masuk</em>, slot lab terisi.</li>
            <li><strong>Tap pulang</strong> (mulai {{ $fmt($pagi?->jam_pulang ?? '17:00') }}) → status <em>lengkap</em>; setelah tutup sesi = badge <em>lembur</em>.</li>
            <li>Hanya masuk, tidak pulang sampai sesi tutup → <em>tidak lengkap</em>.</li>
            <li>Tanpa tap sama sekali sejak <strong>jam pulang</strong> → <em>alfa</em> (otomatis, tanpa menunggu toleransi).</li>
            <li>Hanya masuk, tidak pulang sampai <strong>jam pulang + toleransi</strong> → <em>tidak lengkap</em>.</li>
            <li>Admin/dosen bisa <strong>koreksi</strong> status + catatan (surat sakit, mesin error).</li>
        </ol>

        <div class="panduan-section-title">Kartu ringkasan (atas tabel)</div>
        <ul class="panduan-bullets">
            <li><strong>Hadir</strong> — ada tap masuk (masuk atau lengkap).</li>
            <li><strong>Terlambat</strong> — masuk dengan menit telat &gt; 0 (dari jam standar, bukan dari 06:30).</li>
            <li><strong>Lengkap</strong> — masuk + pulang tercatat.</li>
            <li><strong>Tidak lengkap / Alfa / Belum</strong> — lihat tabel status di bawah.</li>
            <li><strong>% Lengkap</strong> — proporsi terhadap mahasiswa <em>hari wajib</em> pada filter (bukan semua baris).</li>
        </ul>

        <div class="panduan-section-title">Status di kolom Status</div>
        <table class="absensi-table" style="font-size:12px;margin-bottom:16px;">
            <thead><tr><th>Status</th><th>Artinya</th><th>Kapan koreksi?</th></tr></thead>
            <tbody>
                <tr><td><span class="absensi-badge belum">belum</span></td><td>Belum ada tap masuk di hari wajib (sesi masih berjalan atau belum tutup).</td><td>—</td></tr>
                <tr><td><span class="absensi-badge masuk">masuk</span></td><td>Sudah tap masuk, belum tap pulang.</td><td>Setelah sesi tutup → otomatis <em>tidak lengkap</em> jika tidak pulang.</td></tr>
                <tr><td><span class="absensi-badge lengkap">lengkap</span></td><td>Sudah masuk + pulang. Badge <em>lembur</em> = pulang setelah jam tutup sesi (tetap lengkap).</td><td>Jika salah jam/status, koreksi + catatan.</td></tr>
                <tr><td><span class="absensi-badge tidak_lengkap">tidak lengkap</span></td><td>Hanya masuk, tidak pulang sampai sesi ditutup.</td><td>Ubah ke <em>lengkap</em> jika ada bukti izin/surat.</td></tr>
                <tr><td><span class="absensi-badge alfa">alfa</span></td><td>Tidak ada tap sama sekali setelah sesi tutup.</td><td>Batalkan alfa / koreksi jika ada kendala teknis atau izin resmi.</td></tr>
                <tr><td><span class="absensi-badge libur">libur / tidak wajib</span></td><td>Hari libur kalender atau bukan hari wajib (mis. bukan Kamis).</td><td>—</td></tr>
            </tbody>
        </table>

        <div class="panduan-section-title">Kolom jam, telat, slot</div>
        <ul class="panduan-bullets">
            <li><strong>Slot (S1–S10)</strong> — hanya shift <strong>pagi</strong>: blok lab dari waktu tap <strong>masuk</strong> (10 × 55 menit sejak jam standar masuk). Tap sebelum jam standar = S1. Kosong pada data lama sebelum pembaruan sistem.</li>
            <li><strong>Jam masuk / pulang</strong> — waktu tap di mesin (WIB).</li>
            <li><strong>Telat M</strong> — 0 jika masuk ≤ jam standar + <em>toleransi telat masuk</em>; setelah itu baru dihitung menit telat.</li>
            <li><strong>Telat P</strong> — 0 jika pulang ≤ jam pulang + <em>toleransi telat pulang</em>; setelah itu telat pulang; setelah <em>tutup sesi</em> = badge <em>lembur</em> (bukan telat P).</li>
        </ul>

        <div class="panduan-section-title">Jadwal tap (default, shift pagi)</div>
        <ul class="panduan-bullets">
            <li>Tap <strong>masuk</strong>: mulai {{ $fmt($pagi?->jam_mulai_tap_masuk ?? '06:30') }}, standar {{ $fmt($pagi?->jam_masuk ?? '07:50') }}, terakhir sebelum {{ $fmt($pagi?->jam_pulang ?? '17:00') }}.</li>
            <li>Tap <strong>pulang</strong>: mulai {{ $fmt($pagi?->jam_pulang ?? '17:00') }}; setelah tutup sesi ({{ $tutupPagi }}) tetap dicatat sebagai <strong>pulang lembur</strong>.</li>
            <li>Tap pulang <strong>sebelum</strong> jam pulang → <strong>ditolak mesin</strong> (tidak ada log); bukan kasus “dulu ditolak karena lembur”.</li>
        </ul>
        @if($malam)
        <p style="margin:0 0 12px;font-size:12px;color:var(--text-muted);">
            <strong>Shift malam:</strong> masuk {{ $fmt($malam->jam_masuk) }}–{{ $fmt($malam->jam_pulang) }} (± toleransi masuk), tanpa slot lab.
        </p>
        @endif

        <div class="panduan-section-title">Belum enroll sidik jari</div>
        <p class="panduan-lead">
            Bukan status terpisah di rekap: mahasiswa tidak bisa tap. Jika tidak tap di hari wajib, setelah sesi tutup tetap bisa tercatat <strong>alfa</strong> (sama seperti tidak hadir).
        </p>

        @if($canKoreksi ?? false)
        <div class="panduan-section-title">Tombol Koreksi</div>
        <ul class="panduan-bullets">
            <li><strong>Hadir lengkap</strong> — izin/surat setara hadir penuh tanpa tap mesin.</li>
            <li><strong>Hanya masuk / tidak lengkap / alfa</strong> — sesuaikan dengan bukti administrasi.</li>
            <li>Selalu isi <strong>catatan</strong> (contoh: surat sakit, mesin error, izin praktikum).</li>
            <li>Koreksi <strong>tidak mengubah</strong> jam tap di log mesin; hanya status harian untuk rekap.</li>
        </ul>
        @endif
    </div>
</details>
