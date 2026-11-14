@php
    $ops = $ops ?? [];
    $hb = $ops['heartbeat'] ?? [];
    $stats = $ops['stats'] ?? [];
    $kamisReady = (bool) ($ops['kamis_ready'] ?? false);
    $schedOk = (bool) ($hb['is_ok'] ?? false);
@endphp

<section class="abs-ops animate-in {{ $kamisReady ? 'is-ready' : 'is-alert' }}">
    <div class="abs-ops__head">
        <div>
            <div class="abs-ops__eyebrow">Kesiapan operasional</div>
            <h3 class="abs-ops__title">Status absensi hari ini</h3>
            <p class="abs-ops__meta">
                {{ $ops['kebijakan_label'] ?? '' }} · {{ $ops['today'] ?? '' }}
                @if($ops['hari_wajib_hari_ini'] ?? false)
                    · <span class="abs-ops__tag">Hari wajib absen</span>
                @endif
            </p>
        </div>
        <div class="abs-ops__head-right">
            <span class="abs-ops__badge {{ $kamisReady ? 'is-ok' : 'is-bad' }}">
                {{ $kamisReady ? 'Siap produksi' : 'Perlu tindakan' }}
            </span>
            <button type="button" class="abs-help-link" onclick="openAbsensiHelp('vps')" title="Panduan scheduler">
                Scheduler (?)
            </button>
        </div>
    </div>

    <div class="abs-ops__stats">
        <div class="abs-ops__stat {{ $schedOk ? 'is-ok' : 'is-bad' }}">
            <span class="abs-ops__stat-label">Scheduler VPS</span>
            <strong>{{ $schedOk ? 'Aktif' : 'Tidak aktif' }}</strong>
            <small>
                @if($hb['at'] ?? null)
                    {{ $hb['at']->diffForHumans() }}
                @else
                    Belum ada heartbeat
                @endif
            </small>
        </div>
        <div class="abs-ops__stat">
            <span class="abs-ops__stat-label">Tap hari ini</span>
            <strong>{{ $stats['taps_hari_ini'] ?? 0 }}</strong>
        </div>
        <div class="abs-ops__stat">
            <span class="abs-ops__stat-label">Alfa kemarin</span>
            <strong>{{ $stats['alfa_kemarin'] ?? 0 }}</strong>
        </div>
        <div class="abs-ops__stat {{ ($stats['tanpa_shift'] ?? 0) > 0 ? 'is-warn' : '' }}">
            <span class="abs-ops__stat-label">Mhs tanpa shift</span>
            <strong>{{ $stats['tanpa_shift'] ?? 0 }}</strong>
        </div>
        <div class="abs-ops__stat">
            <span class="abs-ops__stat-label">Lengkap hari ini</span>
            <strong>{{ $stats['lengkap_hari_ini'] ?? 0 }}</strong>
        </div>
    </div>

    @if(!empty($ops['close_hints']))
        <p class="abs-ops__hints">
            Tutup sesi otomatis (WIB):
            @foreach($ops['close_hints'] as $hint)
                <strong>{{ $hint['shift'] }}</strong>
                alfa ~{{ $hint['alfa_at'] ?? $hint['close_at'] ?? '—' }},
                tidak lengkap ~{{ $hint['tidak_lengkap_at'] ?? $hint['close_at'] ?? '—' }}@if(!$loop->last); @endif
            @endforeach
        </p>
    @endif

    @if(!empty($ops['issues']))
        <ul class="abs-ops__issues">
            @foreach($ops['issues'] as $issue)
                <li class="{{ ($issue['level'] ?? '') === 'danger' ? 'is-bad' : 'is-warn' }}">
                    <strong>{{ $issue['title'] }}</strong>
                    <span>{{ $issue['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="abs-ops__actions">
        <form method="POST" action="{{ route('admin.absensi.tutup-sesi') }}" class="abs-ops__form">
            @csrf
            <input type="date" name="tanggal" value="{{ $ops['yesterday'] ?? now()->subDay()->toDateString() }}">
            <button type="submit" class="abs-btn abs-btn--danger">Tutup sesi / hitung alfa</button>
        </form>
        <form method="POST" action="{{ route('admin.absensi.generate-alfa') }}">
            @csrf
            <input type="hidden" name="days" value="3">
            <button type="submit" class="abs-btn abs-btn--ghost">Cadangan alfa (3 hari)</button>
        </form>
        <a href="{{ route('admin.pbl.index') }}" class="abs-btn abs-btn--ghost">Proyek PBL</a>
        <a href="{{ route('admin.attendance') }}" class="abs-btn abs-btn--ghost">Rekap absensi</a>
    </div>
</section>
