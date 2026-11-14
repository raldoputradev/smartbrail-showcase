@props(['name', 'id' => null, 'label', 'required' => false, 'icon' => null, 'options' => []])
@php $selectId = $id ?? $name; @endphp
<div {{ $attributes->class(['premium-select-wrap']) }}>
    <label class="premium-select-label" for="{{ $selectId }}">{{ $label }}@if($required) *@endif</label>
    <div class="premium-select">
        @if($icon)
            <span class="premium-select__icon">@include('components.icon', ['name' => $icon])</span>
        @endif
        <select
            name="{{ $name }}"
            id="{{ $selectId }}"
            class="premium-select__native"
            @if($required) required @endif
            {{ $attributes->except('class') }}
        >
            {{ $slot }}
        </select>
        <span class="premium-select__chev" aria-hidden="true">@include('components.icon', ['name' => 'chevron-down'])</span>
    </div>
</div>
