@php $isActive = $item['on'] ?? request()->routeIs(...$item['active']); @endphp
<li>
    <a href="{{ $item['href'] ?? route($item['route']) }}"
       class="sp-nav__link @if($isActive) is-active @endif"
       data-tip="{{ $item['label'] }}" title="{{ $item['label'] }}"
       @if($isActive) aria-current="page" @endif>
        <svg class="sp-nav__icon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg>
        <span class="sp-nav__label">{{ $item['label'] }}@if(!empty($item['hint']))<small>{{ $item['hint'] }}</small>@endif</span>
        @if(!empty($item['feature']) && \App\Support\FeatureBadge::tag($item['feature']))<span class="sp-tag sp-tag--{{ \App\Support\FeatureBadge::state($item['feature']) }}">{{ \App\Support\FeatureBadge::tag($item['feature']) }}</span>@endif
    </a>
</li>
