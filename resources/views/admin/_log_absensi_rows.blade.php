@forelse($logs as $log)
    <tr>
        <td style="font-weight:700;color:#0f766e;text-align:center;">{{ ($logs->currentPage() - 1) * $logs->perPage() + $loop->iteration }}</td>
        <td style="font-family:monospace;font-size:12.5px;">{{ optional($log->waktu_tap)->translatedFormat('d M Y H:i:s') }}</td>
        <td>
            <div style="display:flex;align-items:center;gap:10px;">
                @include('partials.user-avatar', ['user' => $log->user, 'size' => 32, 'tone' => 'teal'])
                <span style="font-weight:600;">{{ $log->user->nama_lengkap ?? '-' }}</span>
            </div>
        </td>
        <td style="font-family:monospace;">{{ $log->user->nitk_nidn ?? '-' }}</td>
        <td style="text-transform:capitalize;">{{ $log->user->role ?? '-' }}</td>
        <td style="font-family:monospace;">{{ $log->id_mesin }}</td>
    </tr>
@empty
    <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">Belum ada data log absensi. Mesin akan mengirim data ke sini saat user tap sidik jari.</td></tr>
@endforelse
