@php
    $dosenList = $dosenList ?? collect();
    $manproUserId = $manproUserId ?? old('manpro_user_id');
    $coManproUserId = $coManproUserId ?? old('co_manpro_user_id');
    $prefix = $prefix ?? '';
@endphp
<div>
    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Akun dosen (manpro)</label>
    <select name="manpro_user_id" id="{{ $prefix }}manpro_user_id" style="width:100%;padding:10px;border:1px solid var(--border-subtle);border-radius:8px;background:var(--bg-card);color:var(--text-dark);">
        <option value="">— Pilih akun dosen —</option>
        @foreach($dosenList as $d)
            <option value="{{ $d->id }}" @selected((string) $manproUserId === (string) $d->id)>{{ $d->nama_lengkap }}</option>
        @endforeach
    </select>
    <small style="display:block;margin-top:4px;color:var(--text-muted);font-size:11px;">Wajib agar menu dosen menampilkan mahasiswa tim ini.</small>
</div>
<div>
    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Akun dosen (co-manpro)</label>
    <select name="co_manpro_user_id" id="{{ $prefix }}co_manpro_user_id" style="width:100%;padding:10px;border:1px solid var(--border-subtle);border-radius:8px;background:var(--bg-card);color:var(--text-dark);">
        <option value="">— Opsional —</option>
        @foreach($dosenList as $d)
            <option value="{{ $d->id }}" @selected((string) $coManproUserId === (string) $d->id)>{{ $d->nama_lengkap }}</option>
        @endforeach
    </select>
</div>
