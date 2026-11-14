@props(['variant' => 'rekap', 'reportsUrl' => null, 'rekapUrl' => null])

@php

    $reportsUrl = $reportsUrl ?? (Route::has('admin.reports') ? route('admin.reports') : (Route::has('dosen.reports') ? route('dosen.reports') : null));

    $rekapUrl = $rekapUrl ?? (Route::has('admin.attendance') ? route('admin.attendance') : (Route::has('dosen.absensi') ? route('dosen.absensi') : null));

@endphp

<div class="module-hint animate-in" role="note">

    <div class="module-hint__icon" aria-hidden="true">

        @include('components.icon', ['name' => $variant === 'rekap' ? 'clipboard-list' : 'bar-chart-3'])

    </div>

    <div class="module-hint__body">

        @if($variant === 'rekap')

            <strong>Rekap Harian (operasional)</strong>

            <p>

                Hanya menampilkan data <strong>hari ini</strong> per mahasiswa: status lengkap, alfa, koreksi, dan export baris demi baris.
                Data diperbarui otomatis. Filter kelas/tim/status tetap bisa dipakai.
                Panel hijau <strong>“📘 Panduan Rekap Harian”</strong> (di bawah banner ini, di atas kartu statistik) berisi alur lengkap, status, slot lab, dan koreksi.

                @if($reportsUrl)

                    Untuk rekap rentang tanggal, grafik tap/loker, dan agregat periode, buka

                    <a href="{{ $reportsUrl }}">Laporan Periodik</a>.

                @endif

            </p>

        @else

            <strong>Laporan Periodik (agregat)</strong>

            <p>

                Rentang tanggal: total tap absensi &amp; loker, grafik harian, top mesin, dan rekap per mahasiswa.

                Panel hijau <strong>“📊 Panduan Laporan Periodik”</strong> (ringkas, klik untuk buka) — beda dengan rekap harian.

                @if($rekapUrl)

                    Untuk operasional harian (alfa, koreksi), buka

                    <a href="{{ $rekapUrl }}">Rekap Absensi Harian</a>.

                @endif

            </p>

        @endif

    </div>

</div>

