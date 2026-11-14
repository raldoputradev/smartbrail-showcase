@php
    $approval = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');
@endphp
@if($approval === \App\Support\AccountActivation::STATUS)
    <span class="status-badge status-badge--icon info" title="Akun hasil import; pemilik mengaktifkan sendiri">
        @include('components.icon', ['name' => 'key-round', 'class' => 'status-badge__icon'])
        Menunggu aktivasi
    </span>
@elseif($user->role === 'mahasiswa')
    @if($approval === 'approved' && $user->is_active)
        <span class="status-badge status-badge--icon success">
            @include('components.icon', ['name' => 'smartphone', 'class' => 'status-badge__icon'])
            Mobile aktif
        </span>
    @elseif($approval === 'rejected')
        <span class="status-badge status-badge--icon danger">
            @include('components.icon', ['name' => 'ban', 'class' => 'status-badge__icon'])
            Ditolak
        </span>
    @elseif($user->email_verified_at)
        <span class="status-badge status-badge--icon warning">
            @include('components.icon', ['name' => 'clock', 'class' => 'status-badge__icon'])
            Tunggu admin
        </span>
    @else
        <span class="status-badge status-badge--icon warning">
            @include('components.icon', ['name' => 'mail', 'class' => 'status-badge__icon'])
            Tunggu OTP
        </span>
    @endif
@else
    @if($approval === 'approved' && $user->is_active)
        <span class="status-badge status-badge--icon success">
            @include('components.icon', ['name' => 'badge-check', 'class' => 'status-badge__icon'])
            Disetujui
        </span>
    @elseif($approval === 'rejected')
        <span class="status-badge status-badge--icon danger">
            @include('components.icon', ['name' => 'ban', 'class' => 'status-badge__icon'])
            Ditolak
        </span>
    @elseif($user->email_verified_at)
        <span class="status-badge status-badge--icon warning">
            @include('components.icon', ['name' => 'clock', 'class' => 'status-badge__icon'])
            Tunggu admin
        </span>
    @else
        <span class="status-badge status-badge--icon warning">
            @include('components.icon', ['name' => 'mail', 'class' => 'status-badge__icon'])
            Tunggu OTP
        </span>
    @endif
@endif
