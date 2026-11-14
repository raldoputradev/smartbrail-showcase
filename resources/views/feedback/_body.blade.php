{{-- Konten bersama halaman Saran (dosen + mahasiswa). $isMhs bool, route names sebagai string --}}
@if(session('success'))
    <div class="panel-info-strip animate-in" style="margin-bottom:16px;border-color:rgba(15,118,110,0.3);">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="panel-info-strip animate-in" style="margin-bottom:16px;border-color:rgba(244,63,94,0.35);background:rgba(244,63,94,0.06);">{{ session('error') }}</div>
@endif

@if(!$isMhs)
    <p class="comm-page-intro">Admin akan meninjau saran Anda. Jika sudah ditanggapi, balasan muncul di bawah dan di lonceng <strong>Antrian Operasional</strong>.</p>
@elseif(($unreadReplies ?? 0) > 0)
    <div class="mhs-card" style="margin-bottom:14px;background:#fef3c7;border-color:#fcd34d;">
        Ada <strong>{{ $unreadReplies }}</strong> balasan admin yang belum dibaca.
    </div>
@endif

<div class="{{ $isMhs ? 'mhs-card' : 'card animate-in comm-form-card' }}" style="margin-bottom:20px;">
    @if(!$isMhs)<div class="card-header"><h3>Kirim saran baru</h3></div>@endif
    <div class="{{ $isMhs ? '' : 'card-body' }}">
        @if($isMhs)<h3 style="font-size:16px;font-weight:800;margin-bottom:12px;">Kirim saran baru</h3>@endif
        <form method="POST" action="{{ route($storeRoute) }}" class="comm-form-grid">
            @csrf
            <div>
                <label class="comm-field-label" for="fb_subjek">Subjek</label>
                <input type="text" class="comm-input" name="subjek" id="fb_subjek" value="{{ old('subjek') }}" placeholder="Ringkasan masalah atau ide" maxlength="180">
            </div>
            <div>
                <label class="comm-field-label" for="fb_pesan">Pesan *</label>
                <div class="comm-input-editor">
                    <div class="comm-emoji-picker-root" data-emoji-for="fb_pesan"></div>
                    <textarea name="pesan" id="fb_pesan" class="comm-textarea" rows="5" required placeholder="Tulis saran, kritik, atau laporan…">{{ old('pesan') }}</textarea>
                </div>
            </div>
            <div>
                <button type="submit" class="{{ $isMhs ? 'mhs-btn mhs-btn--primary' : 'btn-premium btn-premium--primary' }}" style="{{ $isMhs ? 'width:100%;' : '' }}">
                    @if(!$isMhs)@include('components.icon', ['name' => 'send'])@endif
                    <span>Kirim saran</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="{{ $isMhs ? 'mhs-card' : 'card animate-in' }}">
    @if(!$isMhs)<div class="card-header"><h3>Riwayat saran Anda</h3></div>@endif
    <div class="{{ $isMhs ? '' : 'card-body' }}">
        @if($isMhs)<h3 style="font-size:16px;font-weight:800;margin-bottom:12px;">Riwayat saran</h3>@endif
        @forelse($items as $row)
            <article id="saran-{{ $row->id }}" class="comm-thread-card comm-thread-card--timeline {{ $row->hasUnreadReplyForUser() ? 'is-unread' : '' }}">
                <div class="comm-thread-head">
                    <div>
                        <strong class="comm-thread-title">{{ $row->subjek ?: 'Tanpa subjek' }}</strong>
                        <div class="comm-thread-meta">{{ $row->created_at->translatedFormat('d M Y, H:i') }} · {{ ucfirst($row->status) }}</div>
                    </div>
                    @if($row->hasUnreadReplyForUser())
                        <span class="status-badge success">Balasan baru</span>
                    @endif
                </div>
                <div class="comm-reply-stack">
                    <div class="comm-reply-block comm-reply-block--user">
                        <span class="comm-reply-block__label">Anda</span>
                        <div class="comm-bubble comm-bubble--user">{{ $row->pesan }}</div>
                    </div>
                    @if($row->admin_reply)
                        <div class="comm-reply-block comm-reply-block--admin">
                            <span class="comm-reply-block__label">Balasan admin</span>
                            <div class="comm-bubble comm-bubble--admin">{{ $row->admin_reply }}</div>
                            <div class="comm-bubble-meta">
                                {{ $row->repliedBy?->nama_lengkap ?? 'Admin' }} · {{ $row->admin_replied_at?->translatedFormat('d M Y, H:i') }}
                            </div>
                        </div>
                    @else
                        <p class="comm-reply-waiting">Menunggu tinjauan admin.</p>
                    @endif
                </div>
                @if($row->admin_reply && $row->hasUnreadReplyForUser())
                    <form method="POST" action="{{ route($readRoute, $row) }}" class="comm-read-reply-form">
                        @csrf
                        <button type="submit" class="btn-premium" style="font-size:12px;padding:8px 14px;">Tandai sudah dibaca</button>
                    </form>
                @endif
            </article>
        @empty
            <p style="text-align:center;color:var(--text-muted);padding:24px;">Belum ada saran. Gunakan form di atas.</p>
        @endforelse
        @if($items->hasPages())
            <div style="margin-top:16px;">{{ $items->links() }}</div>
        @endif
    </div>
</div>
