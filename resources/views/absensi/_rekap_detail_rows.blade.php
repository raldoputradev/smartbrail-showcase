@forelse($rows as $row)
    <tr class="{{ ($row['jam_masuk'] ?? '—') !== '—' ? 'rk-row--tapped' : '' }}">
        <td>{{ $row['nim'] }}</td>
        <td><strong>{{ $row['nama'] }}</strong><div class="rk-sub">{{ $row['prodi'] }} · Sm{{ $row['semester'] }}</div></td>
        <td>{{ $row['kelas'] }}</td>
        <td>{{ $row['shift'] }} / {{ $row['sesi'] }}</td>
        <td>
            <span class="absensi-badge {{ $row['status'] }}">{{ str_replace('_', ' ', $row['status']) }}</span>
            @if(!empty($row['pulang_lembur']))
                <span class="absensi-badge" style="margin-left:4px;font-size:10px;">lembur</span>
            @endif
        </td>
        <td class="rk-center">{{ $row['slot_lab_masuk'] ? 'S'.$row['slot_lab_masuk'] : '—' }}</td>
        <td class="rk-mono">{{ $row['jam_masuk'] }}</td>
        <td class="rk-mono">{{ $row['jam_pulang'] }}</td>
        <td class="rk-muted">
            @if($row['menit_terlambat_masuk'])<div>M +{{ $row['menit_terlambat_masuk'] }}m</div>@endif
            @if($row['menit_terlambat_pulang'])<div>P +{{ $row['menit_terlambat_pulang'] }}m</div>@endif
            @if(!$row['menit_terlambat_masuk'] && !$row['menit_terlambat_pulang'])—@endif
        </td>
        @if($canKoreksi)
            <td class="is-right">
                @if($row['harian_id'])
                    <button type="button" class="rk-btn rk-btn--sm btn-koreksi-rekap"
                        data-action="{{ route($panel['route_koreksi'], $row['harian_id']) }}"
                        data-status="{{ $row['status'] }}"
                        data-nama="{{ $row['nama'] }}"
                        data-nim="{{ $row['nim'] }}"
                        data-tanggal="{{ $row['tanggal'] }}">
                        Koreksi
                    </button>
                @else
                    <span class="rk-muted">—</span>
                @endif
            </td>
        @endif
    </tr>
@empty
    <tr>
        <td colspan="{{ $canKoreksi ? 10 : 9 }}" class="rk-empty">Tidak ada data untuk filter / tab ini.</td>
    </tr>
@endforelse
