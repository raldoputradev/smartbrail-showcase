@php
    $groupActive = collect($group['items'])->contains(fn ($it) => request()->routeIs(...$it['active']));
@endphp
<div class="sp-nav__group" data-nav-group="{{ $group['key'] }}" @if($groupActive) data-active="1" @endif>
    <button type="button" class="sp-nav__group-btn" aria-expanded="true" aria-controls="spNavGroup-{{ $group['key'] }}">
        @if(!empty($group['icon']))
            <svg class="sp-nav__icon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$group['icon']] !!}</svg>
        @endif
        <span>{{ $group['label'] }}</span>
        <svg class="sp-nav__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <ul class="sp-nav__list" id="spNavGroup-{{ $group['key'] }}">
        @foreach($group['items'] as $item)
            @include('layouts.partials.student-nav-item')
        @endforeach
    </ul>
</div>
