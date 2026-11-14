@php
    $summary = $summary ?? [];
    $denom = $summary['denominator_wajib'] ?? ($summary['total'] ?? 0);
@endphp
<div class="stat-cards stat-cards--rekap">
    @include('partials.stat-card', [
        'tone' => 'teal',
        'icon' => 'badge-check',
        'label' => 'Hadir',
        'value' => number_format($summary['hadir'] ?? 0),
        'valueId' => 'rekap-stat-hadir',
        'meta' => 'Lengkap tepat waktu',
    ])
    @include('partials.stat-card', [
        'tone' => 'amber',
        'icon' => 'clock-3',
        'label' => 'Terlambat',
        'value' => number_format($summary['terlambat'] ?? 0),
        'valueId' => 'rekap-stat-terlambat',
        'meta' => 'Ada telat masuk/pulang',
    ])
    @include('partials.stat-card', [
        'tone' => 'teal',
        'icon' => 'circle-check',
        'label' => 'Lengkap',
        'value' => number_format($summary['lengkap'] ?? 0),
        'valueId' => 'rekap-stat-lengkap',
        'meta' => 'Masuk & pulang tercatat',
    ])
    @include('partials.stat-card', [
        'tone' => 'rose',
        'icon' => 'circle-x',
        'label' => 'Alfa',
        'value' => number_format($summary['alfa'] ?? 0),
        'valueId' => 'rekap-stat-alfa',
        'meta' => 'Tanpa tap wajib',
    ])
    @include('partials.stat-card', [
        'tone' => 'amber',
        'icon' => 'alert-circle',
        'label' => 'Tidak lengkap',
        'value' => number_format($summary['tidak_lengkap'] ?? 0),
        'valueId' => 'rekap-stat-tidak_lengkap',
        'meta' => 'Masuk atau pulang saja',
    ])
    @include('partials.stat-card', [
        'tone' => 'blue',
        'icon' => 'log-in',
        'label' => 'Hanya masuk',
        'value' => number_format($summary['masuk'] ?? 0),
        'valueId' => 'rekap-stat-masuk',
        'meta' => 'Belum tap pulang',
    ])
    @include('partials.stat-card', [
        'tone' => 'amber',
        'icon' => 'clock-3',
        'label' => 'Belum tap',
        'value' => number_format($summary['belum'] ?? 0),
        'valueId' => 'rekap-stat-belum',
        'meta' => 'Belum ada tap wajib',
    ])
    @include('partials.stat-card', [
        'tone' => 'blue',
        'icon' => 'calendar-x',
        'label' => 'Tidak wajib',
        'value' => number_format($summary['tidak_wajib'] ?? 0),
        'valueId' => 'rekap-stat-tidak_wajib',
        'meta' => 'Di luar hari terjadwal',
    ])
    @include('partials.stat-card', [
        'tone' => 'cyan',
        'icon' => 'percent',
        'label' => '% Lengkap',
        'value' => ($summary['persentase_lengkap'] ?? 0) . '%',
        'valueId' => 'rekap-stat-persen',
        'meta' => ($summary['lengkap'] ?? 0) . ' / ' . $denom . ' wajib',
        'metaId' => 'rekap-stat-persen-meta',
    ])
</div>
