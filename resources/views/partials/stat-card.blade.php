@props([
    'tone' => 'blue',
    'icon' => 'activity',
    'label' => '',
    'value' => '—',
    'valueId' => null,
    'meta' => '',
    'metaId' => null,
    'metaClass' => '',
    'tag' => null,
    'href' => null,
])
@if($href)
<a href="{{ $href }}" class="stat-card stat-card--{{ $tone }} stat-card--link">
@else
<div class="stat-card stat-card--{{ $tone }}">
@endif
    <div class="stat-card__accent"></div>
    <div class="stat-card__glow"></div>
    <div class="stat-card__top">
        <div>
            @if($tag)
                <span class="stat-card__tag">{{ $tag }}</span>
            @endif
            <div class="stat-card__label">{{ $label }}</div>
        </div>
        <div class="stat-card__icon">@include('components.icon', ['name' => $icon])</div>
    </div>
    <div class="stat-card__value" @if($valueId) id="{{ $valueId }}" @endif>{{ $value }}</div>
    @if($meta !== '' || $metaId)
        <div class="stat-card__meta {{ $metaClass }}" @if($metaId) id="{{ $metaId }}" @endif>{{ $meta }}</div>
    @endif
@if($href)
</a>
@else
</div>
@endif
