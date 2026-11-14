@props([
    'project',
    'role',
    'label',
    'current' => null,
    'members',
    'storeRoute',
    'clearRoute',
    'canEdit' => true,
])

<div class="pbl-team-detail__role-card pbl-team-detail__role-card--lead">
    <small>{{ $label }}</small>
    @if($current?->user)
        <strong>{{ $current->user->nama_lengkap }}</strong>
        <span class="pbl-team-detail__nim">NIM {{ $current->user->nitk_nidn }}</span>
    @else
        <strong class="is-muted">Belum ditetapkan</strong>
    @endif

    @if($canEdit)
        <div class="pbl-role-form">
            @if($members->isEmpty())
                <p class="pbl-role-form__hint">Tempatkan mahasiswa ke tim ini terlebih dahulu.</p>
            @else
                <form method="POST" action="{{ $storeRoute }}" class="pbl-role-form__row">
                    @csrf
                    <select name="user_id" required class="pbl-assign-select">
                        <option value="">{{ $current ? 'Ganti' : 'Pilih' }} mahasiswa…</option>
                        @foreach($members as $mhs)
                            <option value="{{ $mhs->id }}" @selected($current?->user_id === $mhs->id)>
                                {{ $mhs->nama_lengkap }} ({{ $mhs->nitk_nidn }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="pbl-assign-save">Simpan</button>
                </form>
            @endif
            @if($current)
                <form method="POST" action="{{ $clearRoute }}" class="pbl-role-form__clear">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}">
                    <button type="submit" class="pbl-assign-clear">Kosongkan {{ strtolower($label) }}</button>
                </form>
            @endif
        </div>
    @endif
</div>
