@extends('layouts.dashboard')

@section('title', 'Saran & Masukan')
@section('page-title', 'Saran & Masukan')
@section('page-subtitle', 'Antrian moderasi — landing publik, dosen, dan mahasiswa')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/comm-premium.css') }}?v=10">
@endsection

@section('content')
    @if(session('success'))
        <div class="panel-info-strip animate-in" style="margin-bottom:16px;border-color:rgba(15,118,110,0.3);">{{ session('success') }}</div>
    @endif

    <div class="comm-stat-grid suggest-inbox-stats" id="suggestStats">
        <div class="comm-stat-card"><small>Total</small><strong data-stat="total">{{ number_format($counts['total']) }}</strong></div>
        <div class="comm-stat-card comm-stat-card--baru"><small>Baru</small><strong data-stat="baru">{{ number_format($counts['baru']) }}</strong></div>
        <div class="comm-stat-card comm-stat-card--dibaca"><small>Dibaca</small><strong data-stat="dibaca">{{ number_format($counts['dibaca']) }}</strong></div>
        <div class="comm-stat-card comm-stat-card--ditanggapi"><small>Ditanggapi</small><strong data-stat="ditanggapi">{{ number_format($counts['ditanggapi']) }}</strong></div>
    </div>

    @php
        $filterQuery = request()->only(['q', 'status', 'source']);
        $linkParams = fn (int $id) => array_merge($filterQuery, ['id' => $id]);
        $formQuery = http_build_query(array_merge($filterQuery, ['id' => optional($selected)->id]));
    @endphp

    <div class="suggest-inbox-layout animate-in">
        {{-- Panel kiri: antrian pesan --}}
        <aside class="suggest-inbox-list card">
            <form method="GET" action="{{ route('admin.suggestions.index') }}" class="suggest-inbox-filters">
                @if($selected)
                    <input type="hidden" name="id" value="{{ $selected->id }}">
                @endif
                <input type="text" name="q" class="comm-input" value="{{ request('q') }}" placeholder="Cari nama, subjek, pesan…">
                <select name="status" class="comm-input comm-select-filter">
                    <option value="">Semua status</option>
                    <option value="baru" @selected(request('status')==='baru')>Baru</option>
                    <option value="dibaca" @selected(request('status')==='dibaca')>Dibaca</option>
                    <option value="ditanggapi" @selected(request('status')==='ditanggapi')>Ditanggapi</option>
                </select>
                <select name="source" class="comm-input comm-select-filter">
                    <option value="">Semua asal</option>
                    <option value="public" @selected(request('source')==='public')>Landing publik</option>
                    <option value="dosen" @selected(request('source')==='dosen')>Dosen</option>
                    <option value="mahasiswa" @selected(request('source')==='mahasiswa')>Mahasiswa (akun)</option>
                </select>
                <button type="submit" class="btn-premium btn-premium--primary btn-premium--sm" title="Filter">
                    @include('components.icon', ['name' => 'search'])<span>Filter</span>
                </button>
            </form>

            <div class="suggest-inbox-queue" id="suggestQueue" data-selected-id="{{ optional($selected)->id }}">
                @forelse($items as $row)
                    @php
                        $isActive = $selected && (int) $selected->id === (int) $row->id;
                        $preview = \Illuminate\Support\Str::limit(strip_tags($row->pesan), 72);
                        $statusColor = ['baru'=>'#f43f5e','dibaca'=>'#f59e0b','ditanggapi'=>'#14b8a6'][$row->status] ?? '#64748b';
                    @endphp
                    <a href="{{ route('admin.suggestions.index', $linkParams($row->id)) }}"
                       class="suggest-queue-item {{ $isActive ? 'is-active' : '' }} {{ $row->status === 'baru' ? 'is-unread' : '' }}"
                       id="saran-{{ $row->id }}">
                        <span class="suggest-queue-item__avatar" aria-hidden="true">
                            @include('partials.user-avatar', ['user' => $row->user, 'name' => $row->nama, 'size' => 40, 'tone' => 'teal'])
                        </span>
                        <span class="suggest-queue-item__body">
                            <span class="suggest-queue-item__top">
                                <strong class="suggest-queue-item__name">{{ $row->nama }}</strong>
                                <time class="suggest-queue-item__date">{{ $row->created_at->translatedFormat('d M H:i') }}</time>
                            </span>
                            <span class="suggest-queue-item__subject">{{ $row->subjek ?: 'Tanpa subjek' }}</span>
                            <span class="suggest-queue-item__preview">{{ $preview }}</span>
                        </span>
                        <span class="suggest-queue-item__status" style="--dot:{{ $statusColor }};" title="{{ $row->status }}"></span>
                    </a>
                @empty
                    <p class="suggest-inbox-empty">Belum ada saran masuk.</p>
                @endforelse
            </div>

            @if($items->hasPages())
                <div class="suggest-inbox-pagination">{{ $items->withQueryString()->links() }}</div>
            @endif
        </aside>

        {{-- Panel kanan: detail & moderasi --}}
        <section class="suggest-inbox-detail card">
            @if($selected)
                @php
                    $row = $selected;
                    $statusColor = ['baru'=>'#f43f5e','dibaca'=>'#f59e0b','ditanggapi'=>'#14b8a6'][$row->status] ?? '#64748b';
                    $nim = $row->user?->nitk_nidn;
                    $prodi = $row->user?->programStudi?->nama;
                    $hasReply = filled($row->admin_reply);
                    $editingReply = request()->boolean('edit_reply')
                        || ($errors->has('admin_reply') && (int) old('_reply_for') === $row->id);
                    $showReplyForm = (!$hasReply) || $editingReply;
                @endphp

                <header class="suggest-detail-profile">
                    <span class="suggest-detail-profile__avatar" aria-hidden="true">
                        @include('partials.user-avatar', ['user' => $row->user, 'name' => $row->nama, 'size' => 64, 'tone' => 'teal'])
                    </span>
                    <div class="suggest-detail-profile__info">
                        <h3 class="suggest-detail-profile__name">{{ $row->nama }}</h3>
                        @if($row->user && $row->user->role === 'mahasiswa')
                            <p class="suggest-detail-profile__meta">NIM: {{ $nim ?: '—' }} · {{ $prodi ?: 'Program studi belum diisi' }}</p>
                        @elseif($row->email)
                            <p class="suggest-detail-profile__meta">{{ $row->email }}</p>
                        @else
                            <p class="suggest-detail-profile__meta">{{ $row->sourceLabel() }}</p>
                        @endif
                        <p class="suggest-detail-profile__meta suggest-detail-profile__meta--muted">
                            {{ $row->created_at->translatedFormat('d M Y, H:i') }} · {{ $row->sourceLabel() }}
                        </p>
                    </div>
                    <div class="suggest-detail-profile__badges">
                        <span class="comm-source-pill comm-source-pill--{{ $row->source ?? 'public' }}">{{ $row->sourceLabel() }}</span>
                        <form method="POST" action="{{ route('admin.suggestions.update', $row) }}?{{ $formQuery }}" class="comm-admin-status-form" data-no-loader="1">
                            @csrf
                            @method('PATCH')
                            <label class="comm-field-label" for="suggest_status">Status tiket</label>
                            <select name="status" id="suggest_status" class="comm-input comm-select-filter" onchange="this.form.submit()">
                                <option value="baru" @selected($row->status==='baru')>Baru</option>
                                <option value="dibaca" @selected($row->status==='dibaca')>Dibaca</option>
                                <option value="ditanggapi" @selected($row->status==='ditanggapi')>Ditanggapi</option>
                            </select>
                        </form>
                    </div>
                </header>

                <div class="suggest-detail-body">
                    <h2 class="suggest-detail-body__title">{{ $row->subjek ?: 'Tanpa subjek' }}</h2>
                    <div class="suggest-detail-body__message">{!! nl2br(e($row->pesan)) !!}</div>

                    @if($hasReply)
                        <div class="suggest-detail-reply-sent">
                            <span class="comm-reply-block__label">Balasan terkirim</span>
                            <div class="comm-bubble comm-bubble--admin">{!! nl2br(e($row->admin_reply)) !!}</div>
                            <div class="comm-bubble-meta">{{ $row->repliedBy?->nama_lengkap ?? 'Admin' }} · {{ $row->admin_replied_at?->translatedFormat('d M Y, H:i') }}</div>
                            @unless($editingReply)
                                <p class="suggest-reply-locked">Sudah dibalas. Untuk mengirim ulang: <strong>Edit</strong> atau <strong>Hapus</strong> balasan dulu.</p>
                            @endunless
                        </div>
                    @endif

                    @if($showReplyForm)
                        <div class="suggest-detail-reply-panel is-open" id="suggest-reply-panel">
                            <h4 class="comm-admin-reply-card__title">{{ $hasReply ? 'Edit balasan' : 'Balas ke pengirim' }}</h4>
                            <form method="POST" action="{{ route('admin.suggestions.reply', $row) }}?{{ $formQuery }}" class="comm-admin-reply-form" id="suggestReplyForm">
                                @csrf
                                <input type="hidden" name="_reply_for" value="{{ $row->id }}">
                                <input type="hidden" name="mode" value="{{ $hasReply ? 'edit' : 'create' }}">
                                <div class="comm-input-editor">
                                    <div class="comm-emoji-picker-root" data-emoji-for="reply_panel_{{ $row->id }}"></div>
                                    <textarea name="admin_reply" id="reply_panel_{{ $row->id }}" class="comm-textarea" rows="5" required placeholder="Tulis balasan untuk pengirim…">{{ old('_reply_for') == $row->id ? old('admin_reply') : ($row->admin_reply ?? '') }}</textarea>
                                </div>
                                @error('admin_reply')
                                    <p class="suggest-field-error">{{ $message }}</p>
                                @enderror
                                <p class="comm-admin-reply-hint">
                                    @if($hasReply)
                                        Menyimpan edit tidak mengirim notifikasi push baru (kecuali teks berubah).
                                    @else
                                        Balasan tampil di aplikasi mobile / halaman akun pengirim.
                                    @endif
                                </p>
                                <div class="comm-admin-reply-actions">
                                    @if($hasReply)
                                        <a href="{{ route('admin.suggestions.index', array_merge($filterQuery, ['id' => $row->id])) }}" class="btn-premium btn-premium--outline" style="text-decoration:none;">Batal edit</a>
                                    @endif
                                    <button type="submit" class="btn-premium btn-premium--primary" id="suggestReplySubmit">
                                        @include('components.icon', ['name' => 'send'])
                                        <span>{{ $hasReply ? 'Simpan perubahan' : 'Kirim balasan' }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <footer class="suggest-detail-actions">
                    <form method="POST" action="{{ route('admin.suggestions.destroy', $row) }}?{{ $formQuery }}" class="inline-action-form"
                        data-swal-confirm data-swal-title="Tandai sebagai spam?" data-swal-text="Entri saran akan dihapus permanen dari antrian." data-swal-confirm-button="Ya, hapus" data-swal-confirm-color="#f43f5e">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-premium btn-premium--outline">Tandai Spam</button>
                    </form>

                    @if($hasReply)
                        <a href="{{ route('admin.suggestions.index', array_merge($filterQuery, ['id' => $row->id, 'edit_reply' => 1])) }}"
                           class="btn-premium btn-premium--outline" style="text-decoration:none;">
                            @include('components.icon', ['name' => 'pencil'])
                            <span>Edit balasan</span>
                        </a>
                        <form method="POST" action="{{ route('admin.suggestions.reply.clear', $row) }}?{{ $formQuery }}" class="inline-action-form"
                            data-swal-confirm data-swal-title="Hapus balasan?" data-swal-text="Setelah dihapus Anda bisa kirim balasan baru." data-swal-confirm-button="Ya, hapus" data-swal-confirm-color="#f43f5e">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-premium btn-premium--outline-danger">
                                @include('components.icon', ['name' => 'trash-2'])
                                <span>Hapus balasan</span>
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn-premium btn-premium--primary" data-reply-focus="{{ $row->id }}">
                            @include('components.icon', ['name' => 'send'])
                            <span>Balas pesan</span>
                        </button>
                    @endif
                </footer>
            @else
                <div class="suggest-inbox-detail-empty">
                    <p>Pilih pesan di antrian kiri atau tunggu saran masuk.</p>
                </div>
            @endif
        </section>
    </div>
@endsection

@section('scripts')
<script src="{{ asset('js/emoji-toolbar.js') }}?v=4"></script>
<script>
(function () {
    document.querySelectorAll('[data-reply-focus]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = document.getElementById('suggest-reply-panel');
            if (!panel) return;
            panel.classList.add('is-open');
            var ta = panel.querySelector('textarea');
            if (ta) {
                ta.focus();
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });

    var replyForm = document.getElementById('suggestReplyForm');
    if (replyForm) {
        replyForm.addEventListener('submit', function () {
            var btn = document.getElementById('suggestReplySubmit');
            if (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.7';
            }
        });
    }

    var queueEl = document.getElementById('suggestQueue');
    var statsEl = document.getElementById('suggestStats');
    if (!queueEl || !statsEl) return;

    var feedUrl = @json(route('admin.suggestions.feed')) + '?' + window.location.search.replace(/^\?/, '');
    var indexBase = @json(route('admin.suggestions.index'));
    var lastFingerprint = '';
    var lastBaru = {{ (int) $counts['baru'] }};
    var selectedId = parseInt(queueEl.dataset.selectedId || '0', 10) || null;
    var userTyping = false;

    queueEl.addEventListener('focusin', function (e) {
        if (e.target.matches('a, button')) return;
    });
    document.addEventListener('focusin', function (e) {
        userTyping = !!e.target.closest('#suggest-reply-panel textarea, .suggest-inbox-filters input');
    });
    document.addEventListener('focusout', function () {
        setTimeout(function () {
            userTyping = !!document.activeElement?.closest('#suggest-reply-panel textarea, .suggest-inbox-filters input');
        }, 0);
    });

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function itemUrl(id) {
        var p = new URLSearchParams(window.location.search);
        p.set('id', String(id));
        return indexBase + '?' + p.toString();
    }

    function avatarHtml(item) {
        if (item.avatar_url) {
            return '<img src="' + esc(item.avatar_url) + '" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;display:block;">';
        }
        return '<div style="width:40px;height:40px;border-radius:50%;flex-shrink:0;display:grid;place-items:center;font-size:15px;font-weight:800;background:rgba(45,212,191,0.2);color:#0d9488;" aria-hidden="true">' + esc(item.initial) + '</div>';
    }

    function updateStats(counts) {
        ['total', 'baru', 'dibaca', 'ditanggapi'].forEach(function (key) {
            var el = statsEl.querySelector('[data-stat="' + key + '"]');
            if (el) el.textContent = Number(counts[key] || 0).toLocaleString('id-ID');
        });
    }

    function renderQueue(items) {
        if (!Array.isArray(items) || items.length === 0) {
            queueEl.innerHTML = '<p class="suggest-inbox-empty">Belum ada saran masuk.</p>';
            return;
        }
        queueEl.innerHTML = items.map(function (item) {
            var isActive = selectedId && Number(item.id) === Number(selectedId);
            var unread = item.is_unread ? ' is-unread' : '';
            return '<a href="' + esc(itemUrl(item.id)) + '" class="suggest-queue-item' + (isActive ? ' is-active' : '') + unread + '" id="saran-' + esc(item.id) + '">' +
                '<span class="suggest-queue-item__avatar" aria-hidden="true">' + avatarHtml(item) + '</span>' +
                '<span class="suggest-queue-item__body">' +
                    '<span class="suggest-queue-item__top">' +
                        '<strong class="suggest-queue-item__name">' + esc(item.nama) + '</strong>' +
                        '<time class="suggest-queue-item__date">' + esc(item.created_human) + '</time>' +
                    '</span>' +
                    '<span class="suggest-queue-item__subject">' + esc(item.subjek) + '</span>' +
                    '<span class="suggest-queue-item__preview">' + esc(item.preview) + '</span>' +
                '</span>' +
                '<span class="suggest-queue-item__status" style="--dot:' + esc(item.status_color) + ';" title="' + esc(item.status) + '"></span>' +
            '</a>';
        }).join('');
    }

    function toastNew() {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Pesan saran baru masuk',
            showConfirmButton: false,
            timer: 2800,
            timerProgressBar: true,
        });
    }

    async function refreshInbox() {
        if (userTyping) return;
        try {
            var res = await fetch(feedUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            var data = await res.json();
            if (!data.ok) return;

            updateStats(data.counts || {});

            if (data.fingerprint && data.fingerprint === lastFingerprint) return;

            var baruNow = Number((data.counts || {}).baru || 0);
            if (lastFingerprint && baruNow > lastBaru) {
                toastNew();
            }
            lastBaru = baruNow;
            lastFingerprint = data.fingerprint || '';

            renderQueue(data.items || []);
        } catch (err) { /* silent */ }
    }

    refreshInbox();
    setInterval(refreshInbox, 12_000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') refreshInbox();
    });
})();
</script>
@endsection
