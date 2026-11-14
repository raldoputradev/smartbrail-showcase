@php
    $dosenList = $dosenList ?? collect();
    $manproUserId = $manproUserId ?? old('manpro_user_id');
    $coManproUserId = $coManproUserId ?? old('co_manpro_user_id');
    $prefix = $prefix ?? '';
    $pickerId = $prefix !== '' ? $prefix : 'pbl';
    $dropdown = $dropdown ?? true;
@endphp

<div class="pbl-dosen-pickers{{ $dropdown ? ' pbl-dosen-pickers--compact' : '' }}">
    <div class="pbl-dosen-picker" data-picker="{{ $pickerId }}_manpro">
        <label class="pbl-field-label" for="{{ $pickerId }}_manpro_search">
            Manpro utama
            <span class="pbl-field-hint">Wajib — akun dosen disetujui &amp; sudah verifikasi email</span>
        </label>
        <div class="pbl-dosen-search-wrap">
            @include('components.icon', ['name' => 'search'])
            <input type="search" id="{{ $pickerId }}_manpro_search" class="pbl-dosen-search"
                placeholder="Cari nama atau NIDN…" autocomplete="off" data-target="{{ $pickerId }}_manpro_select">
        </div>
        <select name="manpro_user_id" id="{{ $pickerId }}_manpro_select"
            class="pbl-dosen-select{{ $dropdown ? ' pbl-dosen-select--dropdown' : '' }}"
            @if(!$dropdown) size="5" @endif
            required>
            <option value="">— Pilih dosen —</option>
            @foreach($dosenList as $d)
                <option value="{{ $d->id }}"
                    data-search="{{ strtolower($d->nama_lengkap . ' ' . $d->nitk_nidn . ' ' . ($d->email ?? '')) }}"
                    @selected((string) $manproUserId === (string) $d->id)>
                    {{ $d->nama_lengkap }} · NIDN {{ $d->nitk_nidn }}
                </option>
            @endforeach
        </select>
        <p class="pbl-dosen-selected" id="{{ $pickerId }}_manpro_selected" aria-live="polite"></p>
    </div>

    <div class="pbl-dosen-picker" data-picker="{{ $pickerId }}_comanpro">
        <label class="pbl-field-label" for="{{ $pickerId }}_comanpro_search">
            Co-manpro
            <span class="pbl-field-hint">Opsional</span>
        </label>
        <div class="pbl-dosen-search-wrap">
            @include('components.icon', ['name' => 'search'])
            <input type="search" id="{{ $pickerId }}_comanpro_search" class="pbl-dosen-search"
                placeholder="Cari nama atau NIDN…" autocomplete="off" data-target="{{ $pickerId }}_comanpro_select">
        </div>
        <select name="co_manpro_user_id" id="{{ $pickerId }}_comanpro_select"
            class="pbl-dosen-select{{ $dropdown ? ' pbl-dosen-select--dropdown' : '' }}"
            @if(!$dropdown) size="5" @endif>
            <option value="">— Tidak ada co-manpro —</option>
            @foreach($dosenList as $d)
                <option value="{{ $d->id }}"
                    data-search="{{ strtolower($d->nama_lengkap . ' ' . $d->nitk_nidn . ' ' . ($d->email ?? '')) }}"
                    @selected((string) $coManproUserId === (string) $d->id)>
                    {{ $d->nama_lengkap }} · NIDN {{ $d->nitk_nidn }}
                </option>
            @endforeach
        </select>
        <p class="pbl-dosen-selected" id="{{ $pickerId }}_comanpro_selected" aria-live="polite"></p>
    </div>
</div>
