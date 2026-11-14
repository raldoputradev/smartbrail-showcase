@php
    $active = $activeNav ?? 'rekap';
@endphp
<div class="rk-mini-nav">
    <a href="{{ route($panel['route_rekap']) }}"
        class="rk-mini-nav__link {{ $active === 'rekap' ? 'is-active' : '' }}">
        Rekap Harian
    </a>
    @if($panel['is_laboran'])
        <a href="{{ route('admin.absensi.settings') }}"
            class="rk-mini-nav__link {{ $active === 'settings' ? 'is-active' : '' }}">
            Atur Absensi
        </a>
    @endif
</div>
