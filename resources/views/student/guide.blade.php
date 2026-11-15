@extends('layouts.student')

@section('title', 'Panduan Penggunaan')
@section('page-title', 'Panduan Penggunaan')

@section('content')
    <div class="sp-page sp-page--narrow">
        <section class="sp-card">
            <header class="sp-card__head" style="margin-bottom:14px">
                <div>
                    <p class="sp-card__kicker">PUSAT INFORMASI &amp; ATURAN</p>
                    <h2 class="sp-card__title">Panduan Penggunaan Portal Smart BRAIL</h2>
                    <p class="sp-card__sub">Panduan ini menjelaskan fungsi resmi yang sudah aktif dan tersedia di portal mahasiswa Smart BRAIL.</p>
                </div>
            </header>

            <div class="sp-guide">
                <details class="sp-guide__item" open>
                    <summary>Masuk dan password pertama</summary>
                    <ul>
                        <li>Masuk melalui halaman login dengan memilih role <strong>Mahasiswa</strong> dan memasukkan NIM serta kata sandi.</li>
                        <li>Akun dari data impor memakai password sementara dari laboran. Setelah masuk pertama kali, Anda wajib membuat password pribadi baru.</li>
                        <li>Password dapat diganti kapan saja melalui menu avatar → <a href="{{ route('mahasiswa.password.change') }}" class="sp-link">Keamanan Akun</a> dengan memasukkan password saat ini.</li>
                    </ul>
                </details>

                <details class="sp-guide__item">
                    <summary>Absensi dengan sidik jari di laboratorium</summary>
                    <ul>
                        <li>Absensi dilakukan dengan menempelkan jari pada mesin sidik jari di lab: satu tap saat datang (masuk) dan satu tap saat meninggalkan lab (pulang).</li>
                        <li>Hari wajib saat ini: <strong>{{ $scheduleMeta['active_label'] }}</strong>. Rincian jam ada di halaman <a href="{{ route('mahasiswa.calendar') }}" class="sp-link">Jadwal &amp; Kalender</a>.</li>
                        <li>Data sidik jari direkam oleh laboran. Jika profil Anda berstatus "belum rekam sidik jari", hubungi petugas laboran sebelum memakai mesin.</li>
                    </ul>
                </details>

                <details class="sp-guide__item">
                    <summary>Arti status absensi</summary>
                    <dl class="sp-guide__dl">
                        <div><dt><span class="sp-badge sp-badge--ok">Lengkap</span></dt><dd>Tap masuk dan tap pulang keduanya berhasil tercatat.</dd></div>
                        <div><dt><span class="sp-badge sp-badge--info">Hanya masuk</span></dt><dd>Tap masuk tercatat, tap pulang belum ada (sesi lab masih berjalan).</dd></div>
                        <div><dt><span class="sp-badge sp-badge--warn">Tidak lengkap</span></dt><dd>Tap pulang tidak tercatat sampai sesi ditutup sistem.</dd></div>
                        <div><dt><span class="sp-badge sp-badge--danger">Alfa</span></dt><dd>Tidak ada rekaman tap sama sekali pada hari wajib absensi.</dd></div>
                    </dl>
                    <p>Catatan penting: Kelengkapan berbeda dengan ketepatan waktu. Rekaman berstatus <em>Lengkap</em> tetap dapat memiliki catatan <em>Terlambat</em> atau <em>Pulang lebih awal</em> sesuai waktu aktual tap.</p>
                </details>

                <details class="sp-guide__item">
                    <summary>Melaporkan kendala absensi atau akun</summary>
                    <ul>
                        <li>Gunakan tombol <strong>Lapor</strong> pada baris sesi di halaman <a href="{{ route('mahasiswa.absensi') }}" class="sp-link">Riwayat Absensi</a> atau buka widget <strong>Bantuan</strong> di kanan bawah.</li>
                        <li>Laporan resmi langsung menghasilkan nomor tiket pelacakan (<code class="sp-mono">#ID</code>) yang tercatat di sistem.</li>
                        <li>Status proses tiket dan balasan resmi dari laboran dapat Anda pantau di <a href="{{ route('mahasiswa.suggestions') }}" class="sp-link">Riwayat Laporan</a>.</li>
                    </ul>
                </details>

                <details class="sp-guide__item">
                    <summary>Pemulihan akun dan lupa password</summary>
                    <ul>
                        <li>Gunakan tautan <strong>Lupa password</strong> di halaman login. Kode OTP pemulihan dikirim ke alamat email yang sudah terverifikasi.</li>
                        <li>Tambahkan dan verifikasi email Anda di menu <a href="{{ route('mahasiswa.profile') }}" class="sp-link">Profil Saya</a> agar fitur pemulihan mandiri dapat digunakan sewaktu-waktu.</li>
                        <li>Jika akun belum memiliki email terverifikasi, hubungi petugas laboran untuk bantuan reset password.</li>
                    </ul>
                </details>
            </div>
        </section>
    </div>
@endsection
