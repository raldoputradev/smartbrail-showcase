@extends('layouts.student')

@section('title', 'Pusat Notifikasi')
@section('page-title', 'Pusat Notifikasi')

@section('content')
    @php
        $items = $state['items'] ?? [];
        $unreadCount = (int) ($state['unread_count'] ?? 0);
        $total = (int) ($state['total'] ?? 0);
        $counts = $state['counts'] ?? ['announcement' => 0, 'suggestion_reply' => 0, 'pengajuan' => 0];
        $hasMore = !empty($state['has_more']);
        $penting = collect($items)->where('priority', 'penting')->count();

        $icons = [
            'announcement' => '<path d="M3 11l14-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/><path d="M17 8a4 4 0 0 1 0 5"/>',
            'suggestion_reply' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
            'pengajuan' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/><line x1="9" y1="15" x2="15" y2="15"/>',
        ];
        $tones = ['announcement' => 'info', 'suggestion_reply' => 'ok', 'pengajuan' => 'warn'];

        $donutSegs = [];
        $acc = 0;
        foreach (['announcement' => 'var(--st-info)', 'suggestion_reply' => 'var(--st-ok)', 'pengajuan' => 'var(--st-warn)'] as $k => $color) {
            $c = (int) ($counts[$k] ?? 0);
            if ($c > 0 && $total > 0) {
                $from = round($acc / $total * 360, 1);
                $acc += $c;
                $to = round($acc / $total * 360, 1);
                $donutSegs[] = "{$color} {$from}deg {$to}deg";
            }
        }
        $donutGradient = $donutSegs ? 'conic-gradient(' . implode(', ', $donutSegs) . ')' : 'var(--st-border)';
    @endphp

    <div class="sp-page">
        {{-- Hero --}}
        <div class="sp-nc-hero">
            <div class="sp-nc-hero__text">
                <p class="sp-nc-hero__kicker">Layanan Mahasiswa</p>
                <h2 class="sp-nc-hero__title">Pusat Notifikasi SmartBrail</h2>
                <p class="sp-nc-hero__sub">Semua pengumuman, balasan laporan, dan keputusan pengajuan ruangan dalam satu tempat.</p>
            </div>
            <div class="sp-nc-hero__stats">
                <div class="sp-nc-stat">
                    <span class="sp-nc-stat__num">{{ $total }}</span>
                    <span class="sp-nc-stat__label">Total</span>
                </div>
                <div class="sp-nc-stat sp-nc-stat--unread">
                    <span class="sp-nc-stat__num">{{ $unreadCount }}</span>
                    <span class="sp-nc-stat__label">Belum dibaca</span>
                </div>
                <div class="sp-nc-stat">
                    <span class="sp-nc-stat__num">{{ $penting }}</span>
                    <span class="sp-nc-stat__label">Penting</span>
                </div>
            </div>
        </div>

        <div class="sp-nc-layout">
            {{-- Kolom utama --}}
            <div>
                <div class="sp-nc-tabs" role="tablist" aria-label="Filter notifikasi">
                    <a href="{{ route('mahasiswa.notifications', ['filter' => 'all']) }}"
                       class="sp-nc-tab {{ $filter === 'all' ? 'is-active' : '' }}" role="tab">
                        Semua <span class="sp-nc-tab__count">{{ $total }}</span>
                    </a>
                    <a href="{{ route('mahasiswa.notifications', ['filter' => 'unread']) }}"
                       class="sp-nc-tab {{ $filter === 'unread' ? 'is-active' : '' }}" role="tab">
                        Belum Dibaca <span class="sp-nc-tab__count sp-nc-tab__count--alert">{{ $unreadCount }}</span>
                    </a>
                    <a href="{{ route('mahasiswa.notifications', ['filter' => 'announcement']) }}"
                       class="sp-nc-tab {{ $filter === 'announcement' ? 'is-active' : '' }}" role="tab">
                        Pengumuman <span class="sp-nc-tab__count">{{ $counts['announcement'] }}</span>
                    </a>
                    <a href="{{ route('mahasiswa.notifications', ['filter' => 'suggestion_reply']) }}"
                       class="sp-nc-tab {{ $filter === 'suggestion_reply' ? 'is-active' : '' }}" role="tab">
                        Balasan Laporan <span class="sp-nc-tab__count">{{ $counts['suggestion_reply'] }}</span>
                    </a>
                    <a href="{{ route('mahasiswa.notifications', ['filter' => 'pengajuan']) }}"
                       class="sp-nc-tab {{ $filter === 'pengajuan' ? 'is-active' : '' }}" role="tab">
                        Ruangan <span class="sp-nc-tab__count">{{ $counts['pengajuan'] }}</span>
                    </a>
                </div>

                <div class="sp-nc-feed">
                    @forelse($items as $item)
                        @php
                            $isUnread = empty($item['is_read']);
                            $kind = (string) ($item['kind'] ?? 'announcement');
                            $tone = $tones[$kind] ?? 'neutral';
                        @endphp
                        <article class="sp-nc-card {{ $isUnread ? 'is-unread' : '' }}">
                            <div class="sp-nc-card__icon sp-nc-card__icon--{{ $tone }}">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$kind] ?? $icons['announcement'] !!}</svg>
                            </div>
                            <div class="sp-nc-card__main">
                                <div class="sp-nc-card__top">
                                    <span class="sp-status sp-status--{{ $tone }}">{{ $item['category_label'] ?? 'Info' }}</span>
                                    @if(($item['priority'] ?? '') === 'penting')
                                        <span class="sp-nc-flag">Penting</span>
                                    @endif
                                    @if($isUnread)
                                        <span class="sp-nc-unread-dot" title="Belum dibaca"></span>
                                    @endif
                                    <time class="sp-nc-card__time">{{ $item['time_human'] ?? '' }}</time>
                                </div>
                                <h3 class="sp-nc-card__title">{{ $item['title'] }}</h3>
                                <p class="sp-nc-card__excerpt">{{ $item['excerpt'] }}</p>
                                <div class="sp-nc-card__foot">
                                    @if(!empty($item['meta']))
                                        <span class="sp-nc-card__meta">{{ $item['meta'] }}</span>
                                    @endif
                                    <span class="sp-nc-card__actions">
                                        @if(!empty($item['action_url']))
                                            <a href="{{ $item['action_url'] }}" class="sp-link">{{ $item['action_label'] ?? 'Buka' }} &rarr;</a>
                                        @endif
                                        @if($isUnread && !empty($item['read_url']))
                                            <form method="POST" action="{{ $item['read_url'] }}">
                                                @csrf
                                                <button type="submit" class="sp-btn sp-btn--ghost sp-btn--xs">Tandai dibaca</button>
                                            </form>
                                        @endif
                                    </span>
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
                                <h3>{{ $filter === 'unread' ? 'Semua sudah dibaca' : 'Belum ada notifikasi' }}</h3>
                                <p class="sp-muted">
                                    {{ $filter === 'unread' ? 'Tidak ada notifikasi yang menunggu perhatian Anda.' : 'Pengumuman, balasan laporan, dan keputusan pengajuan akan tampil di sini.' }}
                                </p>
                                @if($filter !== 'all')
                                    <a href="{{ route('mahasiswa.notifications', ['filter' => 'all']) }}" class="sp-btn sp-btn--ghost sp-btn--sm" style="margin-top: 10px;">Lihat Semua</a>
                                @endif
                            </div>
                        </div>
                    @endforelse
                </div>

                @if($page > 1 || $hasMore)
                    <nav class="sp-pagination" aria-label="Navigasi halaman notifikasi">
                        @if($page > 1)
                            <a href="{{ route('mahasiswa.notifications', ['filter' => $filter, 'page' => $page - 1]) }}" class="sp-btn sp-btn--ghost sp-btn--xs">&larr; Sebelumnya</a>
                        @else
                            <span class="sp-btn sp-btn--ghost sp-btn--xs is-disabled" aria-disabled="true">&larr; Sebelumnya</span>
                        @endif
                        <span class="sp-pagination__info">Halaman <strong>{{ $page }}</strong></span>
                        @if($hasMore)
                            <a href="{{ route('mahasiswa.notifications', ['filter' => $filter, 'page' => $page + 1]) }}" class="sp-btn sp-btn--ghost sp-btn--xs">Berikutnya &rarr;</a>
                        @else
                            <span class="sp-btn sp-btn--ghost sp-btn--xs is-disabled" aria-disabled="true">Berikutnya &rarr;</span>
                        @endif
                    </nav>
                @endif
            </div>

            {{-- Sidebar ringkasan --}}
            <aside class="sp-nc-side">
                <div class="sp-card sp-nc-summary">
                    <h3 class="sp-nc-summary__title">Ringkasan Notifikasi</h3>
                    <div class="sp-nc-donut" style="background: {{ $donutGradient }};">
                        <div class="sp-nc-donut__hole">
                            <strong>{{ $total }}</strong>
                            <span>notifikasi</span>
                        </div>
                    </div>
                    <ul class="sp-nc-legend">
                        <li><span class="sp-nc-legend__dot" style="background: var(--st-info);"></span>Pengumuman <strong>{{ $counts['announcement'] }}</strong></li>
                        <li><span class="sp-nc-legend__dot" style="background: var(--st-ok);"></span>Balasan Laporan <strong>{{ $counts['suggestion_reply'] }}</strong></li>
                        <li><span class="sp-nc-legend__dot" style="background: var(--st-warn);"></span>Keputusan Ruangan <strong>{{ $counts['pengajuan'] }}</strong></li>
                    </ul>
                </div>

                <div class="sp-card sp-nc-actions">
                    <h3 class="sp-nc-summary__title">Aksi Cepat</h3>
                    @if($unreadCount > 0)
                        <form method="POST" action="{{ route('mahasiswa.notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="sp-btn sp-btn--primary sp-btn--sm" style="width:100%;">
                                Tandai Semua Dibaca ({{ $unreadCount }})
                            </button>
                        </form>
                    @else
                        <p class="sp-muted" style="font-size:13px;margin:0 0 8px;">Semua notifikasi sudah dibaca.</p>
                    @endif
                    @include('partials.push-toggle')
                </div>
            </aside>
        </div>
    </div>
@endsection
