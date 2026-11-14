@php

    $panel = $panel ?? [];

    $query = request()->query();

@endphp

<div class="export-btn-group" role="group" aria-label="Export rekap">

    <a href="{{ route($panel['route_rekap_export'], $query) }}" class="export-btn excel" title="Unduh Excel">

        <span class="export-btn-icon">@include('components.icon', ['name' => 'file-spreadsheet'])</span>

        <span>Excel</span>

    </a>

    <a href="{{ route($panel['route_rekap_print'], $query) }}" target="_blank" class="export-btn pdf" title="Cetak / simpan PDF">

        <span class="export-btn-icon">@include('components.icon', ['name' => 'file-text'])</span>

        <span>PDF</span>

    </a>

</div>

