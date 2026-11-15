@extends('layouts.student')

@section('title', 'Notifikasi & Pengumuman')
@section('page-title', 'Notifikasi & Pengumuman')

@section('content')
    @php
        $pagination = $notificationPage['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'has_more' => false, 'total' => 0];
        $unreadCount = (int) ($notificationPage['unread_count'] ?? 0);
        $items = $notificationPage['data'] ?? [];
    @endphp

    <div class="sp-page sp-page--narrow">
        <header class="sp-page-header">
            <div>
                <p class="sp-card__kicker">LAYANAN MAHASISWA</p>
                <h2 class="sp-card__title" style="font-size: 20px;">Riwayat Notifikasi &amp; Pengumuman</h2>
                <p class="sp-card__sub">Daftar seluruh pengumuman kampus dan balasan laporan yang dikirimkan kepada Anda.</p>
            </div>
        </header>

        {{-- Filter Tabs & Tandai Semua Dibaca --}}
        <div class="sp-notif-filter-bar">
            <div class="sp-notif-tabs" role="tablist">
                <a href="{{ route('mahasiswa.notifications', ['unread_only' => 0]) }}"
                   class="sp-notif-tab @if(!$unreadOnly) is-active @endif"
                   role="tab">
                    Semua
                    @if(!$unreadOnly && !empty($pagination['total']))
                        <span class="sp-notif-tab__badge">{{ $pagination['total'] }}</span>
                    @endif
                </a>
                <a href="{{ route('mahasiswa.notifications', ['unread_only' => 1]) }}"
                   class="sp-notif-tab @if($unreadOnly) is-active @endif"
                   role="tab">
                    Belum Dibaca
                    @if($unreadCount > 0)
                        <span class="sp-notif-tab__badge sp-notif-tab__badge--alert">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>

            @if($unreadCount > 0)
                <form method="POST" action="{{ route('mahasiswa.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="sp-btn sp-btn--ghost sp-btn--xs">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Tandai Semua Dibaca
                    </button>
                </form>
            @endif
        </div>

        {{-- Daftar Artikel Notifikasi --}}
        <div class="sp-notif-feed">
            @forelse($items as $item)
                @php
                    $isUnread = empty($item['is_read']);
                    $isReply = ($item['type'] ?? '') === 'suggestion_reply';
                @endphp
                <article class="sp-card sp-notif-card-full {{ $isUnread ? 'is-unread' : '' }}">
                    <div class="sp-notif-card-full__head">
                        <div class="sp-notif-card-full__meta">
                            <span class="sp-status sp-status--{{ $isReply ? 'info' : ($isUnread ? 'warn' : 'neutral') }}">
                                {{ $isReply ? 'Balasan Laporan' : 'Pengumuman' }}
                            </span>
                            <time class="sp-muted" datetime="{{ $item['created_at'] }}">
                                {{ $item['created_at'] ? \Illuminate\Support\Carbon::parse($item['created_at'])->translatedFormat('d M Y, H:i') . ' WIB' : '' }}
                            </time>
                        </div>
                        <span class="sp-badge-read sp-badge-read--{{ $isUnread ? 'unread' : 'read' }}">
                            {{ $isUnread ? 'Belum dibaca' : 'Sudah dibaca' }}
                        </span>
                    </div>

                    <h3 class="sp-notif-card-full__title">{{ $item['judul'] }}</h3>

                    <div class="sp-notif-card-full__body">
                        <p>{!! nl2br(e($item['isi'])) !!}</p>
                    </div>

                    {{-- Bila ada pesan asli laporan --}}
                    @if($isReply && !empty($item['pesan_asli']))
                        <div class="sp-notif-quote">
                            <span class="sp-notif-quote__label">Pesan Asli Laporan Anda:</span>
                            <p class="sp-notif-quote__text">{!! nl2br(e($item['pesan_asli'])) !!}</p>
                        </div>
                    @endif

                    <div class="sp-notif-card-full__foot">
                        <span class="sp-notif-card-full__author">
                            Dari: <strong>{{ $item['penulis'] ?: 'Admin Laboran Smart BRAIL' }}</strong>
                        </span>

                        <div class="sp-notif-card-full__actions">
                            @if($isReply && !empty($item['suggestion_id']))
                                <a href="{{ route('mahasiswa.suggestions') }}" class="sp-link">
                                    Buka di Riwayat Laporan &rarr;
                                </a>
                            @endif

                            @if($isUnread)
                                <form method="POST" action="{{ $isReply ? route('mahasiswa.suggestions.read', $item['id']) : route('mahasiswa.notifications.read', $item['id']) }}">
                                    @csrf
                                    <button type="submit" class="sp-btn sp-btn--primary sp-btn--xs">
                                        Tandai Dibaca
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="sp-card sp-card--empty">
                    <div class="sp-empty-state">
                        <div class="sp-empty-state__icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8.5a6 6 0 0 0-12 0c0 6.5-2.5 8.5-2.5 8.5h17S18 15 18 8.5"/>
                                <path d="M10.3 20.5a2 2 0 0 0 3.4 0"/>
                            </svg>
                        </div>
                        <h3>{{ $unreadOnly ? 'Tidak ada notifikasi yang belum dibaca' : 'Belum ada notifikasi' }}</h3>
                        <p class="sp-muted">
                            {{ $unreadOnly ? 'Seluruh pengumuman dan balasan laporan telah Anda baca.' : 'Pengumuman laboratorium atau tindak lanjut laporan Anda akan tampil di halaman ini.' }}
                        </p>
                        @if($unreadOnly)
                            <a href="{{ route('mahasiswa.notifications', ['unread_only' => 0]) }}" class="sp-btn sp-btn--ghost sp-btn--sm" style="margin-top: 10px;">
                                Lihat Semua Riwayat
                            </a>
                        @endif
                    </div>
                </div>
            @endforelse
        </div>

        {{-- Navigasi Paginasi --}}
        @if(($pagination['last_page'] ?? 1) > 1)
            <nav class="sp-pagination" aria-label="Navigasi halaman notifikasi">
                @if($pagination['current_page'] > 1)
                    <a href="{{ route('mahasiswa.notifications', ['page' => $pagination['current_page'] - 1, 'unread_only' => $unreadOnly ? 1 : 0]) }}" class="sp-btn sp-btn--ghost sp-btn--xs">
                        &larr; Sebelumnya
                    </a>
                @else
                    <span class="sp-btn sp-btn--ghost sp-btn--xs is-disabled" aria-disabled="true">&larr; Sebelumnya</span>
                @endif

                <span class="sp-pagination__info">
                    Halaman <strong>{{ $pagination['current_page'] }}</strong> dari <strong>{{ $pagination['last_page'] }}</strong>
                </span>

                @if(!empty($pagination['has_more']))
                    <a href="{{ route('mahasiswa.notifications', ['page' => $pagination['current_page'] + 1, 'unread_only' => $unreadOnly ? 1 : 0]) }}" class="sp-btn sp-btn--ghost sp-btn--xs">
                        Berikutnya &rarr;
                    </a>
                @else
                    <span class="sp-btn sp-btn--ghost sp-btn--xs is-disabled" aria-disabled="true">Berikutnya &rarr;</span>
                @endif
            </nav>
        @endif
    </div>
@endsection
