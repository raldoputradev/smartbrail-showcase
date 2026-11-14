@extends('layouts.copilot')

@section('title', 'Copilot')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/copilot.css') }}?v=2">
@endsection

@section('content')
<div class="cp-app" id="cpApp">
    <aside class="cp-side" id="cpSide" aria-label="Riwayat percakapan">
        <div class="cp-side__top">
            <a class="cp-back" href="{{ $backUrl }}" title="Kembali ke SIMALAS">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                <span class="cp-side__text">Kembali ke SIMALAS</span>
            </a>
            <button type="button" class="cp-iconbtn cp-side__collapse" id="cpCollapse" aria-label="Ciutkan riwayat" title="Ciutkan riwayat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
            </button>
        </div>
        <button type="button" class="cp-new" id="cpNew" title="Percakapan baru">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span class="cp-side__text">Percakapan baru</span>
        </button>
        <div class="cp-side__label cp-side__text">Riwayat</div>
        <nav class="cp-history" id="cpHistory">
            @foreach($conversations as $c)
                <div class="cp-history__item" data-id="{{ $c->id }}">
                    <button type="button" class="cp-history__open" data-open="{{ $c->id }}">{{ $c->title }}</button>
                    <button type="button" class="cp-history__del" data-del="{{ $c->id }}" aria-label="Hapus percakapan {{ $c->title }}" title="Hapus">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    </button>
                </div>
            @endforeach
            <p class="cp-history__empty" id="cpHistoryEmpty" @if($conversations->isNotEmpty()) hidden @endif>Belum ada riwayat.</p>
        </nav>
    </aside>
    <div class="cp-scrim" id="cpScrim" hidden></div>

    <main class="cp-main">
        <header class="cp-head">
            <button type="button" class="cp-iconbtn" id="cpToggle" aria-label="Buka riwayat" aria-controls="cpSide" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="cp-head__title">SIMALAS Copilot</h1>
            <span class="cp-head__status" title="{{ $aiEnabled ? 'Jawaban dari Gemini, tampil bertahap' : 'Gemini belum dikonfigurasi; jawaban dari data lokal' }}">
                <span class="cp-dot {{ $aiEnabled ? 'is-on' : '' }}" aria-hidden="true"></span>{{ $aiEnabled ? 'Gemini' : 'Mode lokal' }} · baca-saja
            </span>
            <a class="cp-head__back" href="{{ $backUrl }}" aria-label="Kembali ke SIMALAS">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg><span>SIMALAS</span>
            </a>
        </header>

        <div class="cp-thread-wrap">
            <div class="cp-thread" id="cpThread" tabindex="-1" aria-label="Isi percakapan">
                <div class="cp-thread__inner" id="cpThreadInner"></div>
            </div>
            <button type="button" class="cp-jump" id="cpJump" hidden>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>Ke pesan terbaru
            </button>
        </div>

        <form class="cp-composer" id="cpForm" novalidate>
            <div class="cp-composer__error" id="cpFormError" role="alert" hidden>
                <span id="cpFormErrorText"></span>
                <button type="button" id="cpFormRetry">Coba lagi</button>
            </div>
            <div class="cp-composer__box">
                <label class="cp-sr" for="cpInput">Pesan untuk Copilot</label>
                <textarea id="cpInput" rows="1" maxlength="2000" placeholder="{{ $cpConfig['placeholder'] }}" autocomplete="off"></textarea>
                <button type="submit" class="cp-send" id="cpSend" aria-label="Kirim" title="Kirim" disabled>
                    <svg class="cp-send__go" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <svg class="cp-send__stop" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5"/></svg>
                </button>
            </div>
            <p class="cp-composer__hint" id="cpPrivacyNote">
                @if($aiEnabled)
                    Baca-saja. Pertanyaan bebas diproses Gemini (Google); nama/NIM/email mahasiswa disamarkan, tetapi tidak semua data pribadi pasti tersaring.
                @else
                    Copilot hanya membaca data SIMALAS.
                @endif
                <span class="cp-hint-desktop">Enter kirim · Shift+Enter baris baru</span>
            </p>
        </form>
    </main>
</div>

<div class="cp-sr" id="cpLive" aria-live="polite" aria-atomic="true"></div>

<dialog class="cp-dialog" id="cpDeleteDialog" aria-labelledby="cpDeleteTitle">
    <form method="dialog">
        <h2 id="cpDeleteTitle">Hapus percakapan?</h2>
        <p>Riwayat <strong id="cpDeleteName"></strong> akan dihapus permanen.</p>
        <div class="cp-dialog__actions">
            <button value="cancel" class="cp-btn">Batal</button>
            <button value="confirm" class="cp-btn cp-btn--danger">Hapus</button>
        </div>
    </form>
</dialog>

<script type="application/json" id="cpConfig">{!! json_encode($cpConfig + [
    'chartJs' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'suggestions' => $suggestions,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('scripts')
<script src="{{ asset('js/copilot.js') }}?v=4" defer></script>
@endsection
