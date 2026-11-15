@php
    $rekapUrl = $rekapUrl ?? null;
    $reportsUrl = $reportsUrl ?? null;
    $settingsUrl = $settingsUrl ?? (Route::has('admin.absensi.settings') ? route('admin.absensi.settings') : null);
    $kebijakan = $kebijakanLabel ?? \App\Support\AbsensiKebijakan::labelMode();
@endphp
<div style="margin:0 0 14px;padding:12px 14px;border-radius:10px;background:rgba(74,144,217,0.08);border:1px solid rgba(74,144,217,0.2);font-size:12px;line-height:1.55;color:var(--text-muted);">
    <strong style="color:var(--text-main);">Konsep SIMALAS (singkat)</strong>
    <ul style="margin:8px 0 0;padding-left:18px;">
        <li>Sumber data: <strong>tap mesin ESP32</strong> (sidik jari). Aplikasi mobile hanya membaca riwayat, tidak bisa tap absensi.</li>
        <li>Kebijakan wajib saat ini: <strong>{{ $kebijakan }}</strong>. Alfa otomatis setelah sesi tutup jika tidak ada tap.</li>
        <li><strong>Rekap Harian</strong> = status per mahasiswa per hari (lengkap, alfa, koreksi).
            @if($rekapUrl)<a href="{{ $rekapUrl }}">Buka rekap harian</a>.@endif
        </li>
        <li><strong>Laporan Periodik</strong> = agregat rentang tanggal (jumlah tap, grafik aktivitas).
            @if($reportsUrl)<a href="{{ $reportsUrl }}">Buka laporan periodik</a>.@endif
        </li>
        @if($settingsUrl)
        <li>Jadwal 06:30 / 07:50 / 17:00 / slot lab diatur di <a href="{{ $settingsUrl }}">Pengaturan Absensi</a>.</li>
        @endif
    </ul>
</div>
