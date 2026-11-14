@php
    $compact = $compact ?? false;
    $showShift = $showShift ?? true;
@endphp
@foreach($rows as $m)
    <tr>
        <td style="font-family:monospace;font-size:12px;">{{ $m->nitk_nidn }}</td>
        <td style="font-weight:600;">{{ $m->nama_lengkap }}</td>
        <td>{{ $m->kelas ?: '—' }}</td>
        <td>{{ $m->semester ?: '—' }}</td>
        @unless($compact)
            <td>{{ $m->tim ?: '—' }}</td>
            <td>{{ $m->shift ? ucfirst($m->shift) : '—' }}</td>
        @endunless
        @if($compact && $showShift)
            <td>{{ $m->shift ? ucfirst($m->shift) : '—' }}</td>
        @endif
        <td style="padding:8px 10px;min-width:{{ $compact ? '240px' : 'auto' }};">
            <form method="POST" action="{{ route('admin.pbl.assign-mahasiswa') }}" class="pbl-assign-form">
                @csrf
                <input type="hidden" name="user_id" value="{{ $m->id }}">
                <select name="pbl_project_id" required class="pbl-assign-select">
                    <option value="">— Pilih proyek —</option>
                    @foreach($projectOptions as $opt)
                        <option value="{{ $opt->id }}" @selected($m->tim === $opt->nama)>{{ $opt->kode }} · {{ $opt->nama }}</option>
                    @endforeach
                </select>
                <button type="submit" class="pbl-assign-save">Simpan</button>
            </form>
            @if($m->tim)
                <form method="POST" action="{{ route('admin.pbl.clear-tim', $m) }}" style="margin-top:6px;">
                    @csrf
                    <button type="submit" class="pbl-assign-clear">Kosongkan tim</button>
                </form>
            @endif
        </td>
    </tr>
@endforeach
