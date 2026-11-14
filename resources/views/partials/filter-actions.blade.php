@props([
    'resetUrl' => null,
    'submitLabel' => 'Terapkan',
    'showReset' => true,
])
<div class="filter-toolbar-actions">
    <button type="submit" class="btn-premium btn-premium--primary" title="{{ $submitLabel }}">
        @include('components.icon', ['name' => 'search'])
        <span>{{ $submitLabel }}</span>
    </button>
    @if($showReset && $resetUrl)
        <a href="{{ $resetUrl }}" class="btn-premium" title="Reset filter" style="text-decoration:none;">
            @include('components.icon', ['name' => 'rotate-ccw'])
            <span>Reset</span>
        </a>
    @endif
    {{ $slot ?? '' }}
</div>
