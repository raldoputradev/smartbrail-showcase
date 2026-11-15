@php
    $statusLabels = [
        'lengkap' => 'Lengkap',
        'masuk' => 'Hanya masuk',
        'tidak_lengkap' => 'Tidak lengkap',
        'alfa' => 'Alfa',
        'belum' => 'Belum tap',
        'sakit' => 'Sakit',
        'izin' => 'Izin',
        'libur' => 'Libur',
        'tidak_wajib' => 'Tidak wajib',
    ];
    $currentStatus = $status ?? 'belum';
@endphp
<span class="student-status student-status--{{ $currentStatus }}">
    <span class="student-status__dot" aria-hidden="true"></span>
    {{ $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus)) }}
</span>
