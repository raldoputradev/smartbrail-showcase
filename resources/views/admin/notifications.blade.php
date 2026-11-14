@extends('layouts.dashboard')
@php use App\Support\ApprovalActionLabel; @endphp

@section('title', 'Antrian Notifikasi')
@section('page-title', 'Antrian Notifikasi')
@section('page-subtitle', 'Tugas prioritas · riwayat sistem')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/comm-premium.css') }}?v=11">
@endsection

@section('content')
    <div class="nq-desk animate-in">
        <header class="nq-hero">
            <div class="nq-hero__left">
                <span class="nq-eyebrow">Pusat tindak lanjut admin</span>
                <h2>
                    Antrian kerja
                    <button type="button" class="nq-help-q" onclick="openNqHelp('umum')" title="Panduan" aria-label="Panduan">?</button>
                </h2>
                <p>Item di bawah perlu ditindaklanjuti. Lonceng header hanya mengingatkan.</p>
            </div>
            <div class="nq-hero__right">
                <div class="nq-hero__count">
                    <strong>{{ $notifications->filter(fn ($n) => !empty($n['url']))->count() }}</strong>
                    <span>item prioritas</span>
                </div>
                <button type="button" class="ann-btn ann-btn--ghost" onclick="openNqHelp('arah')">
                    Panduan <span class="nq-help-q">?</span>
                </button>
            </div>
        </header>

        @if(($pendingApprovalCount ?? 0) > 0)
            <div class="users-pending-banner">
                <div>
                    <div class="users-pending-banner__title">
                        @include('components.icon', ['name' => 'user-check'])
                        <span>{{ \App\Support\PendingAccountApproval::notificationTitle() }}</span>
                    </div>
                    <p>{{ \App\Support\PendingAccountApproval::notificationBody() }}</p>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    <a href="{{ route('admin.users', ['approval_status' => 'pending']) }}" class="btn-premium btn-premium--primary" style="text-decoration:none;">
                        @include('components.icon', ['name' => 'filter'])<span>Setujui / tolak sekarang</span>
                    </a>
                    @if(($pendingApprovalUsers ?? collect())->isNotEmpty())
                        <div class="users-pending-chips">
                            @foreach($pendingApprovalUsers as $pu)
                                <a href="{{ route('admin.users', ['q' => $pu->email, 'approval_status' => 'pending']) }}" class="users-pending-chip">
                                    {{ $pu->nama_lengkap }}
                                    <small>{{ $pu->role_display ?? ucfirst($pu->role) }}</small>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <section class="nq-queue card">
            <div class="nq-section-head">
                <div>
                    <h3>
                        Antrian prioritas
                        <button type="button" class="nq-help-q" onclick="openNqHelp('antrian')" title="Panduan antrian" aria-label="Panduan antrian">?</button>
                    </h3>
                    <p>Klik kartu untuk langsung ke halaman aksi.</p>
                </div>
            </div>
            <div class="nq-queue__list">
                @forelse($notifications as $item)
                    @php
                        $tone = $item['tone'] ?? 'blue';
                        $hasUrl = !empty($item['url']);
                        $tag = $hasUrl ? 'a' : 'div';
                    @endphp
                    <{{ $tag }}
                        @if($hasUrl) href="{{ $item['url'] }}" @endif
                        class="nq-queue__item nq-queue__item--{{ $tone }} {{ $hasUrl ? 'is-link' : 'is-empty' }}">
                        <span class="nq-queue__dot" aria-hidden="true"></span>
                        <div class="nq-queue__body">
                            <strong>{{ $item['title'] }}</strong>
                            @if(!empty($item['description']))
                                <p>{{ $item['description'] }}</p>
                            @endif
                            <small>{{ $item['time'] }}</small>
                        </div>
                        @if($hasUrl)
                            <span class="nq-queue__cta">{{ $item['action'] ?? 'Buka' }} →</span>
                        @endif
                    </{{ $tag }}>
                @empty
                    <div class="nq-queue__item is-empty">
                        <div class="nq-queue__body">
                            <strong>Antrian kosong</strong>
                            <p>Tidak ada tugas prioritas. Tekan (?) untuk panduan arah menu.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="nq-history card">
            <div class="nq-section-head">
                <div>
                    <h3>
                        Riwayat aktivitas sistem
                        <button type="button" class="nq-help-q" onclick="openNqHelp('riwayat')" title="Panduan riwayat" aria-label="Panduan riwayat">?</button>
                    </h3>
                    <p>Arsip otomatis sistem — bukan pesan dari atasan.</p>
                </div>
                <a href="{{ route('admin.users', ['log_action' => 'deleted']) }}" class="ann-btn ann-btn--ghost" style="text-decoration:none;">
                    Riwayat hapus →
                </a>
            </div>
            <div class="nq-history__table-wrap">
                <table class="data-table" style="min-width:860px;">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User / Subjek</th>
                            <th>Aksi</th>
                            <th>Oleh</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activityLogs as $log)
                            <tr>
                                <td class="ann-desk__mono">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <div style="font-weight:700;">{{ $log->subjectDisplayName() }}</div>
                                    <div style="font-size:11px;color:var(--text-muted);">
                                        {{ $log->subjectDisplayEmail() }} · {{ $log->subjectDisplayNitk() }}
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-badge--icon {{ ApprovalActionLabel::tone($log->action) }}">
                                        {{ ApprovalActionLabel::label($log->action) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->actor)
                                        <div style="font-weight:700;color:var(--soft-blue);">{{ $log->actor->nama_lengkap }}</div>
                                        <div style="font-size:11px;color:var(--text-muted);">{{ $log->actor->role_display ?? $log->actor->role }}</div>
                                    @else
                                        <span style="font-weight:600;color:var(--text-muted);">Sistem / pendaftar</span>
                                    @endif
                                </td>
                                <td style="max-width:240px;font-size:12px;color:var(--text-muted);">{{ $log->note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">Belum ada riwayat aktivitas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @include('components.table-pagination', ['paginator' => $activityLogs])
            </div>
        </section>
    </div>

    <div class="iot-modal-backdrop" id="nqHelpModal" role="dialog" aria-modal="true">
        <div class="iot-modal nq-help-modal">
            <button type="button" class="iot-modal-close" onclick="closeNqHelp()" aria-label="Tutup">×</button>
            <div class="nq-help-badge">PANDUAN</div>
            <h3 id="nqHelpTitle">Panduan</h3>
            <p id="nqHelpDesc" class="nq-help-desc"></p>
            <div class="nq-help-code" id="nqHelpCode"></div>
            <div class="nq-help-links" id="nqHelpLinks"></div>
            <div class="nq-help-actions">
                <button type="button" class="ann-btn ann-btn--primary" onclick="closeNqHelp()">Mengerti</button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
.nq-hero__left h2{display:inline-flex;align-items:center;gap:8px}
.nq-hero__right{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.nq-section-head h3{display:inline-flex;align-items:center;gap:8px}
.nq-help-q{
    display:inline-grid;place-items:center;width:18px;height:18px;margin:0;
    border:1px solid color-mix(in srgb,#0f766e 45%,var(--border-subtle));
    border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;
    font-size:11px;font-weight:800;line-height:1;cursor:pointer;padding:0;vertical-align:middle;
}
.nq-help-q:hover,.ann-btn .nq-help-q{background:#0f766e;color:#fff;border-color:#0f766e}
.iot-modal-backdrop{position:fixed;inset:0;z-index:3000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.72);backdrop-filter:blur(8px)}
.iot-modal-backdrop.is-open{display:flex}
.iot-modal{width:min(560px,100%);position:relative;border:1px solid rgba(148,163,184,.28);border-radius:18px;padding:22px;background:var(--bg-card);color:var(--text-dark);box-shadow:0 24px 70px rgba(15,23,42,.38);max-height:min(90vh,720px);overflow:auto}
.iot-modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:999px;background:rgba(148,163,184,.14);color:var(--text-dark);font-size:20px;cursor:pointer}
.nq-help-badge{display:inline-flex;padding:5px 10px;border-radius:999px;margin-bottom:8px;background:rgba(15,118,110,.14);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em}
.nq-help-modal h3{margin:0 0 8px;font-size:18px}
.nq-help-desc{margin:0 0 12px;font-size:13px;line-height:1.6;color:var(--text-muted)}
.nq-help-code{font-family:ui-monospace,Consolas,monospace;font-size:12px;line-height:1.55;padding:12px 14px;border-radius:12px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 40%,transparent);color:var(--text-dark);white-space:pre-wrap;margin-bottom:12px}
.nq-help-code:empty{display:none}
.nq-help-links{display:grid;gap:8px;margin-bottom:14px}
.nq-help-links:empty{display:none}
.nq-help-links a{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--bg-main);text-decoration:none;color:var(--text-dark);font-size:12.5px;font-weight:700}
.nq-help-links a:hover{border-color:rgba(15,118,110,.4)}
.nq-help-links a span{color:var(--text-muted);font-weight:600}
.nq-help-actions{display:flex;justify-content:flex-end}
</style>
<script>
const NQ_HELP = {
    umum: {
        title: 'Antrian Notifikasi',
        desc: 'Halaman ini adalah pusat tindak lanjut admin. Bukan inbox chat — isinya tugas yang perlu dikerjakan sekarang.',
        code: 'Lonceng header  → pengingat singkat\nAntrian prioritas → tugas yang harus ditindak\nRiwayat sistem   → arsip aksi otomatis',
        links: [],
    },
    arah: {
        title: 'Panduan arah menu',
        desc: 'Kalau item antrian belum muncul, buka menu terkait lewat tautan di bawah.',
        code: '',
        links: [
            { href: @json(route('admin.users', ['verify_status' => 'ready_approve'])), label: 'Akun menunggu approval', meta: 'Manajemen User' },
            { href: @json(route('admin.suggestions.index', ['status' => 'baru'])), label: 'Saran & masukan baru', meta: 'Komunikasi' },
            { href: @json(route('admin.monitoring')), label: 'Mesin IoT offline', meta: 'Monitoring' },
            { href: @json(route('admin.announcements.index')), label: 'Broadcast resmi', meta: 'Pengumuman' },
            { href: @json(route('admin.users')), label: 'Riwayat approval lengkap', meta: 'Manajemen User' },
        ],
    },
    antrian: {
        title: 'Antrian prioritas',
        desc: 'Setiap kartu mengarah ke halaman aksi. Klik untuk menyelesaikan tugas (setujui akun, balas saran, cek mesin, dll).',
        code: 'Approval akun → Manajemen User\nSaran baru    → Saran & Masukan\nMesin offline → Monitoring\nLogin terkunci → Manajemen User',
        links: [],
    },
    riwayat: {
        title: 'Riwayat aktivitas sistem',
        desc: 'Ini arsip otomatis SIMALAS: siapa mendaftar, OTP, disetujui, ditolak, atau dihapus. Bukan notifikasi dari atasan.',
        code: 'Filter & pencarian lengkap ada di\nManajemen User → Riwayat Approval',
        links: [
            { href: @json(route('admin.users', ['log_action' => 'deleted'])), label: 'Riwayat hapus akun', meta: 'Manajemen User' },
        ],
    },
};

function openNqHelp(kind) {
    const data = NQ_HELP[kind] || NQ_HELP.umum;
    const modal = document.getElementById('nqHelpModal');
    document.getElementById('nqHelpTitle').textContent = data.title;
    document.getElementById('nqHelpDesc').textContent = data.desc || '';
    const code = document.getElementById('nqHelpCode');
    code.textContent = data.code || '';
    const links = document.getElementById('nqHelpLinks');
    links.innerHTML = (data.links || []).map(function (l) {
        return '<a href="' + l.href + '"><strong>' + l.label + '</strong><span>' + l.meta + ' →</span></a>';
    }).join('');
    modal.style.display = 'flex';
    modal.classList.add('is-open');
}
function closeNqHelp() {
    const modal = document.getElementById('nqHelpModal');
    modal.style.display = 'none';
    modal.classList.remove('is-open');
}
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('nqHelpModal');
    if (modal) {
        modal.addEventListener('click', function (e) { if (e.target === modal) closeNqHelp(); });
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeNqHelp(); });
});
</script>
@endsection
