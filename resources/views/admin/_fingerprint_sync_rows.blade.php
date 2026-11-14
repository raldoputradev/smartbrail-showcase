@forelse($rows as $log)
    <tr>
        <td class="fp-mono fp-nowrap">{{ $log->requested_at?->format('d M Y H:i:s') }}</td>
        <td class="fp-mono"><strong>{{ $log->id_mesin }}</strong></td>
        <td><span class="status-badge" style="text-transform:capitalize;">{{ $log->mesin_tipe ?: '—' }}</span></td>
        <td>{{ $log->actionLabel() }}</td>
        <td class="fp-mono fp-sm">
            @if($log->action === 'sync_pull')
                after={{ $log->after_finger_id ?? 0 }} · limit={{ $log->batch_limit ?? '—' }}
            @else
                —
            @endif
        </td>
        <td class="fp-mono fp-sm">{{ $log->since ?: '—' }}</td>
        <td class="fp-center"><strong>{{ $log->templates_count }}</strong></td>
        <td class="fp-mono fp-sm">
            @if(!empty($log->finger_ids))
                #{{ implode(', #', $log->finger_ids) }}
            @else
                —
            @endif
        </td>
        <td class="fp-mono fp-sm fp-ellipsis" title="{{ $log->sync_token }}">{{ $log->sync_token ?: '—' }}</td>
        <td class="fp-mono fp-sm">{{ $log->ip_address ?: '—' }}</td>
        <td>
            @if($log->success)
                <span class="status-badge success">OK</span>
            @else
                <span class="status-badge danger">Gagal</span>
            @endif
        </td>
        <td class="fp-sm">{{ $log->message ?: '—' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="12" class="fp-empty">Belum ada log sync. Muncul saat mesin boot / polling / enroll.</td>
    </tr>
@endforelse
