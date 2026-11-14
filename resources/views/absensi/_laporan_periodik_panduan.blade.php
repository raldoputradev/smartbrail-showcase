@php

    $routes = $reportPanel['routes'] ?? [];

    $rekapUrl = isset($routes['rekap']) ? route($routes['rekap']) : null;

    $isDosen = $reportPanel['is_dosen'] ?? false;

    $kebijakan = $kebijakanLabel ?? \App\Support\AbsensiKebijakan::labelMode();

@endphp

<details class="absensi-panduan-panel animate-in" data-panduan-key="laporan-periodik">

    <summary>

        <span>📊 Panduan Laporan Periodik</span>

        <span style="display:flex;align-items:center;gap:8px;">

            <span class="absensi-panduan-hint">klik untuk buka</span>

            <span class="absensi-panduan-chevron" aria-hidden="true">▾</span>

        </span>

    </summary>

    <div class="card-body" style="padding-top:12px;font-size:13px;line-height:1.6;color:var(--text-main);">

        <p class="panduan-lead">

            <strong>Tujuan halaman ini:</strong> melihat <em>rekap status absensi</em> per mahasiswa dalam rentang tanggal

            (<strong>Hadir</strong>, <strong>Tidak Lengkap</strong>, <strong>Alfa</strong>) plus akses loker.

            Untuk koreksi harian per tanggal, gunakan

            @if($rekapUrl)<a href="{{ $rekapUrl }}">Rekap Harian</a>@else Rekap Harian @endif

            ({{ $kebijakan }}).

        </p>



        <div class="panduan-compare">

            <div class="panduan-compare-col">

                <h5>📘 Rekap Harian</h5>

                <ul>

                    <li>Satu tanggal, satu baris per mahasiswa</li>

                    <li>Status: lengkap, alfa, tidak lengkap</li>

                    <li>Slot lab, lembur, tombol koreksi</li>

                </ul>

            </div>

            <div class="panduan-compare-col panduan-compare-col--here">

                <h5>📊 Laporan Periodik (di sini)</h5>

                <ul>

                    <li>Rentang tanggal, grafik status harian</li>

                    <li>Per mahasiswa: jumlah hari Hadir / Tidak Lengkap / Alfa</li>

                    <li>Detail tap: badge hijau = masuk, oranye = pulang</li>

                </ul>

            </div>

        </div>



        <div class="panduan-section-title">Arti kartu di atas grafik</div>

        <div class="panduan-grid-2">

            <div class="panduan-mini-card">

                <strong>Mahasiswa</strong>

                <p>Jumlah akun sesuai filter kelas/tim — bukan jumlah yang hadir.</p>

            </div>

            <div class="panduan-mini-card">

                <strong>Hadir</strong>

                <p>Hari dengan status <em>lengkap</em> (tap masuk + pulang).</p>

            </div>

            <div class="panduan-mini-card">

                <strong>Tidak Lengkap</strong>

                <p>Hari dengan status <em>tidak_lengkap</em> atau masih <em>masuk</em> (belum pulang).</p>

            </div>

            <div class="panduan-mini-card">

                <strong>Alfa</strong>

                <p>Hari wajib tanpa tap sama sekali (setelah sesi ditutup).</p>

            </div>

        </div>



        <div class="panduan-section-title">Tabel &amp; export</div>

        <ul class="panduan-bullets">

            <li><strong>Grafik</strong> — jumlah mahasiswa per status per hari (bukan jumlah tap).</li>

            <li><strong>Rekap per mahasiswa</strong> — total hari Hadir / Tidak Lengkap / Alfa di periode.</li>

            <li><strong>Detail</strong> — log tap; badge hijau = masuk, oranye = pulang.</li>

            <li><strong>Koreksi resmi</strong> — selalu lewat Rekap Harian + halaman Alfa / Tidak Lengkap.</li>

            <li><strong>Excel / PDF</strong> — untuk tren periode; daftar hadir harian pakai export Rekap Harian.</li>

        </ul>

    </div>

</details>


