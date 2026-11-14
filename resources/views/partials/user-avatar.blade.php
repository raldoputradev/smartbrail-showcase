@props([
    'user' => null,
    'name' => null,
    'photoUrl' => null,
    'size' => 36,
    'tone' => 'blue',
])

@php
    $displayName = trim((string) ($name ?? $user?->nama_lengkap ?? '?'));
    $url = $photoUrl ?? $user?->profile_photo_url ?? null;
    $px = max(24, (int) $size);
    $initial = $displayName !== '' && $displayName !== '?'
        ? strtoupper(substr($displayName, 0, 1))
        : '?';
    $toneStyles = [
        'teal' => 'background:rgba(45,212,191,0.2);color:#0d9488;',
        'blue' => 'background:rgba(74,144,217,0.2);color:#2563eb;',
        'amber' => 'background:rgba(245,158,11,0.2);color:#d97706;',
        'violet' => 'background:rgba(139,92,246,0.2);color:#7c3aed;',
    ];
    $fallbackStyle = $toneStyles[$tone] ?? $toneStyles['blue'];
@endphp

@if($url)
    <img
        src="{{ $url }}"
        alt=""
        {{ $attributes->merge(['style' => "width:{$px}px;height:{$px}px;border-radius:50%;object-fit:cover;flex-shrink:0;display:block;"]) }}
    >
@else
    <div
        {{ $attributes->merge(['style' => "width:{$px}px;height:{$px}px;border-radius:50%;flex-shrink:0;display:grid;place-items:center;font-size:" . max(11, (int) round($px * 0.38)) . "px;font-weight:800;{$fallbackStyle}"]) }}
        aria-hidden="true"
    >{{ $initial }}</div>
@endif
