@extends('layouts.student')

@section('title', 'Riwayat Laporan & Bantuan')
@section('page-title', 'Riwayat Laporan')

@section('content')
    @php
        $totalCount = $items->count();
        $repliedCount = $items->filter(fn ($i) => filled($i->admin_reply))->count();
        $pendingCount = $totalCount - $repliedCount;

        $prefillKategori = old('kategori', request()->query('kategori', 'absensi'));
        $prefillTanggal = old('tanggal_absensi', request()->query('tanggal', ''));
        $prefillRef = old('referensi_absensi', request()->query('ref', ''));
        $prefillSubjek = old('subjek', $prefillTanggal ? "Kendala Absensi Tanggal {$prefillTanggal}" : '');
    @endphp

    <div class="sp-page">
        {{-- Header & Ringkasan Tiket Bantuan --}}
        <section class="sp-card" aria-label="Ringkasan Riwayat Bantuan">
            <header class="sp-card__head">
                <div>
                    <p class="sp-card__kicker">PUSAT BANTUAN &amp; LAPORAN RESMI</p>
                    <h2 class="sp-card__title">Riwayat Laporan, Kendala Absensi &amp; Saran</h2>
                    <p class="sp-card__sub">
                        Pantau status tiket laporan yang sudah Anda konfirmasi dan baca tanggapan resmi dari Admin Laboran.
                    </p>
                </div>
            </header>

            <div class="sp-stats">
                <div class="sp-stat">
                    <span class="sp-stat__label"><span class="sp-dot sp-dot--info"></span> Total Tiket</span>
                    <strong class="sp-stat__value">{{ $totalCount }}</strong>
                    <span class="sp-stat__hint">Laporan &amp; masukan terkirim</span>
                </div>
                <div class="sp-stat">
                    <span class="sp-stat__label"><span class="sp-dot sp-dot--warn"></span> Menunggu Tanggapan</span>
                    <strong class="sp-stat__value">{{ $pendingCount }}</strong>
                    <span class="sp-stat__hint">Sedang ditinjau Admin Laboran</span>
                </div>
                <div class="sp-stat">
                    <span class="sp-stat__label"><span class="sp-dot sp-dot--ok"></span> Sudah Dibalas</span>
                    <strong class="sp-stat__value">{{ $repliedCount }}</strong>
                    <span class="sp-stat__hint">Tanggapan resmi tersedia</span>
                </div>
            </div>
        </section>

        {{-- Grid 2 Kolom: Kiri Daftar Tiket, Kanan Form Pengiriman --}}
        <div class="sp-grid sp-grid--bottom">
            {{-- Kolom Kiri: Daftar Riwayat Tiket --}}
            <section class="sp-card" aria-label="Daftar Tiket Laporan Anda">
                <header class="sp-card__head">
                    <div>
                        <p class="sp-card__kicker">DAFTAR TIKET ANDA</p>
                        <h3 class="sp-card__title">Riwayat Pengiriman ({{ $totalCount }})</h3>
                        <p class="sp-card__sub">Daftar laporan kendala absensi dan saran yang pernah Anda sampaikan.</p>
                    </div>
                </header>

                @if($items->isEmpty())
                    <div class="sp-empty">
                        Anda belum pernah mengirim laporan kendala absensi atau saran layanan laboratorium.
                    </div>
                @else
                    <div class="sp-ticket-list">
                        @foreach($items as $item)
                            @php
                                $hasReply = filled($item->admin_reply);
                                $isUnreadReply = $hasReply && $item->user_read_reply_at === null;
                                $statusTone = $hasReply ? 'ok' : ($item->status === 'diproses' ? 'info' : 'warn');
                                $statusLabel = $hasReply ? 'Dibalas Admin' : ($item->status === 'diproses' ? 'Sedang Diproses' : 'Menunggu Tanggapan');
                            @endphp
                            <article class="sp-ticket-card {{ $isUnreadReply ? 'is-unread' : '' }}">
                                <div class="sp-ticket-card__head">
                                    <div class="sp-ticket-card__title-wrap">
                                        <span class="sp-ticket-id sp-mono">Referensi #{{ $item->id }}</span>
                                        <h4 class="sp-ticket-card__title">{{ $item->subjek ?: 'Laporan / Saran Mahasiswa' }}</h4>
                                    </div>
                                    <div class="sp-ticket-card__badges">
                                        @if($isUnreadReply)
                                            <span class="sp-badge sp-badge--info">Balasan Baru</span>
                                        @endif
                                        <span class="sp-badge sp-badge--{{ $statusTone }}">{{ $statusLabel }}</span>
                                    </div>
                                </div>

                                <div class="sp-ticket-card__meta">
                                    Dikirim pada {{ $item->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB
                                </div>

                                <div class="sp-ticket-card__body">{{ $item->pesan }}</div>

                                @if($hasReply)
                                    <div class="sp-ticket-reply">
                                        <div class="sp-ticket-reply__head">
                                            <strong>Balasan Admin Laboran</strong>
                                            @if($item->admin_replied_at)
                                                <span>{{ $item->admin_replied_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB</span>
                                            @endif
                                        </div>
                                        <p class="sp-ticket-reply__body">{{ $item->admin_reply }}</p>

                                        @if($isUnreadReply)
                                            <form method="POST" action="{{ route('mahasiswa.suggestions.read', $item) }}" style="margin-top:10px">
                                                @csrf
                                                <button type="submit" class="sp-btn sp-btn--ghost sp-btn--xs">Tandai Balasan Sudah Dibaca</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Kolom Kanan: Formulir Kirim Laporan / Saran Baru --}}
            <section class="sp-card" aria-label="Kirim Laporan atau Saran Baru">
                <header class="sp-card__head">
                    <div>
                        <p class="sp-card__kicker">FORMULIR RESMI</p>
                        <h3 class="sp-card__title">Kirim Laporan atau Saran</h3>
                        <p class="sp-card__sub">Setiap pengiriman menghasilkan nomor referensi tiket (<code class="sp-mono">#ID</code>) yang langsung dapat dilacak.</p>
                    </div>
                </header>

                <form method="POST" action="{{ route('mahasiswa.suggestions.store') }}" class="sp-form">
                    @csrf
                    <div class="sp-field">
                        <label class="sp-label" for="spReportKategori">Kategori Laporan</label>
                        <select id="spReportKategori" name="kategori" class="sp-input" style="height:40px">
                            <option value="absensi" @selected($prefillKategori === 'absensi')>Kendala Absensi Harian</option>
                            <option value="saran" @selected($prefillKategori === 'saran')>Saran &amp; Masukan Layanan Lab</option>
                            <option value="akun" @selected($prefillKategori === 'akun')>Kendala Akun / Fingerprint</option>
                            <option value="lainnya" @selected($prefillKategori === 'lainnya')>Lainnya</option>
                        </select>
                    </div>

                    <div class="sp-grid--2cols" style="margin-top:0; gap:12px">
                        <div class="sp-field">
                            <label class="sp-label" for="spReportTanggal">Tanggal Absensi</label>
                            <input type="date" id="spReportTanggal" name="tanggal_absensi" class="sp-input" value="{{ $prefillTanggal }}">
                            <p class="sp-hint">Diisi jika terkait rekaman tap</p>
                        </div>
                        <div class="sp-field">
                            <label class="sp-label" for="spReportRef">Referensi Status</label>
                            <input type="text" id="spReportRef" name="referensi_absensi" class="sp-input" value="{{ $prefillRef }}" maxlength="180" placeholder="Mis. Hanya Masuk / Alfa">
                            <p class="sp-hint">Status catatan kendala</p>
                        </div>
                    </div>

                    <div class="sp-field">
                        <label class="sp-label" for="spReportSubjek">Subjek / Ringkasan</label>
                        <input type="text" id="spReportSubjek" name="subjek" class="sp-input" value="{{ $prefillSubjek }}" maxlength="180" placeholder="Contoh: Koreksi rekaman tap pulang">
                    </div>

                    <div class="sp-field">
                        <label class="sp-label" for="spReportPesan">Uraian Lengkap <span class="sp-muted">(min. 10 karakter)</span></label>
                        <textarea id="spReportPesan" name="pesan" class="sp-input" style="height:auto; min-height:110px; padding:10px 14px; resize:vertical" rows="4" minlength="10" maxlength="4000" required placeholder="Tuliskan kronologi kendala atau saran Anda secara jelas...">{{ old('pesan') }}</textarea>
                    </div>

                    <div class="sp-form-actions">
                        <button type="submit" class="sp-btn sp-btn--primary">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                            <span>Konfirmasi &amp; Kirim Laporan</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
