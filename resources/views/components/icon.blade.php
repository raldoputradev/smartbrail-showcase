@props(['name', 'class' => ''])
<span {{ $attributes->merge(['class' => 'icon-wrap ' . $class]) }} aria-hidden="true">
    <i data-lucide="{{ $name }}"></i>
</span>
