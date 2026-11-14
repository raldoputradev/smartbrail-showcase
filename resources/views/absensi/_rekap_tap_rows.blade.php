@forelse($tapMasukHariIni ?? [] as $tap)
    <tr>
        <td class="rk-rank">{{ $tap['urutan'] }}</td>
        <td class="rk-mono">{{ $tap['waktu_tap'] }}</td>
        <td>{{ $tap['nim'] }}</td>
        <td><strong>{{ $tap['nama'] }}</strong></td>
        <td>{{ $tap['kelas'] }}</td>
        <td>{{ $tap['shift'] }} / {{ $tap['sesi'] }}</td>
        <td class="rk-center">{{ $tap['slot_lab'] ? 'S'.$tap['slot_lab'] : '—' }}</td>
        <td class="rk-muted">
            @if(($tap['menit_terlambat'] ?? 0) > 0)
                +{{ $tap['menit_terlambat'] }}m
            @else
                aman
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="8" class="rk-empty">Belum ada tap masuk hari ini.</td></tr>
@endforelse
