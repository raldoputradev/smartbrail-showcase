@extends('layouts.student')

@section('title', 'Pengajuan Ruangan')
@section('page-title', 'Pengajuan Ruangan')

@section('content')
@php
    $tz = 'Asia/Jakarta';
    $now = now($tz);
    $terkirim = session('terkirim');
    $hariMap = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $chipTone = [
        'diajukan' => 'info', 'menunggu_penerimaan' => 'warn', 'disetujui' => 'ok',
        'ditolak' => 'danger', 'dibatalkan' => 'neutral', 'selesai' => 'neutral', 'kedaluwarsa' => 'neutral',
    ];
    $statusLabel = [
        'diajukan' => 'Menunggu keputusan', 'menunggu_penerimaan' => 'Tawaran waktu pengganti',
        'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan',
        'selesai' => 'Selesai', 'kedaluwarsa' => 'Kedaluwarsa',
    ];
    $anggotaList = $anggota->map(fn ($a) => [
        'id' => $a->id,
        'nama' => $a->nama_lengkap,
        'nim' => $a->nitk_nidn,
        'foto' => $a->profile_photo_path ? asset($a->profile_photo_path) : null,
    ])->values();
    $initialsOf = function (?string $name) {
        return collect(explode(' ', trim((string) $name)))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'M';
    };
    $pesertaOld = (array) old('peserta', [auth()->id()]);
@endphp

<div class="sp-page" id="srApp"
     data-ketersediaan-url="{{ route('mahasiswa.ruangan.ketersediaan') }}"
     data-today="{{ $now->toDateString() }}">

    {{-- Pagehead: foto kampus sebagai latar area halaman --}}
    <section class="sp-pagehead" aria-label="Kepala halaman pengajuan">
        <p class="sp-pagehead__crumb">
            <a href="{{ route('mahasiswa.beranda') }}">Ruangan</a>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
            {{ $tabRiwayat ? 'Riwayat Pengajuan' : 'Ajukan Ruangan' }}
        </p>
        <div class="sp-pagehead__main">
            <span class="sp-pagehead__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 20 6.8v10.4l-6.5 2.3z"/><path d="M13.5 4.5V22"/><path d="M4 19.5h9.5"/><circle cx="16.3" cy="12" r=".9" fill="currentColor" stroke="none"/></svg>
            </span>
            <div>
                <h2 class="sp-pagehead__title">Ajukan Pemakaian Ruangan</h2>
                <p class="sp-pagehead__sub">Ajukan penggunaan ruangan untuk kegiatan tim PBL Anda.{{ $tim ? ' Tim: ' . $tim->nama . '.' : '' }}</p>
            </div>
        </div>
    </section>

    @if(session('status'))<div class="sp-flash sp-flash--ok" role="status">{{ session('status') }}</div>@endif

    @if(!$tim)
        <section class="sp-card" aria-label="Belum ada tim">
            <div class="sr-empty">
                <strong>Belum ada tim PBL</strong>
                <p class="sp-muted" style="margin:4px 0 0">Pengajuan ruangan memerlukan keanggotaan tim PBL yang sah. Hubungi dosen pembimbing atau laboran untuk dimasukkan ke tim.</p>
            </div>
        </section>
    @else
        <div class="sr-layout">
            {{-- KOLOM KIRI: pilihan ruangan + ketersediaan --}}
            <div class="sr-col">
                <section class="sp-card" aria-labelledby="srRoomTitle">
                    <header class="sp-card__head">
                        <div>
                            <p class="sp-card__kicker">LANGKAH 1</p>
                            <h2 id="srRoomTitle" class="sp-card__title">Pilih Ruangan</h2>
                        </div>
                    </header>
                    <div class="sr-rooms" role="radiogroup" aria-label="Pilihan ruangan">
                        @foreach($ruanganList as $r)
                            @php
                                $a = $r['aturan'];
                                $m = $r['model'];
                                $hariLabel = empty($a['hari']) ? 'Setiap hari' : implode(', ', array_map(fn ($d) => $hariMap[$d] ?? $d, $a['hari']));
                                $fasilitas = array_filter((array) ($m->fasilitas ?? []));
                            @endphp
                            <button type="button" class="sr-room" role="radio" aria-checked="false"
                                    data-room-id="{{ $m->id }}"
                                    data-hari="{{ implode(',', array_map('intval', $a['hari'] ?? [])) }}"
                                    data-max-peserta="{{ (int) ($a['max_peserta'] ?? 0) }}">
                                <span class="sr-room__thumb" aria-hidden="true">
                                    @if($m->foto)
                                        <img src="{{ asset($m->foto) }}" alt="" loading="lazy" onerror="this.parentElement.classList.add('sr-room__thumb--empty');this.remove()">
                                    @else
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 20 6.8v10.4l-6.5 2.3z"/><path d="M13.5 4.5V22"/><path d="M4 19.5h9.5"/><circle cx="16.3" cy="12" r=".9" fill="currentColor" stroke="none"/></svg>
                                    @endif
                                </span>
                                <span class="sr-room__body">
                                    <strong>{{ $m->nama }}</strong>
                                    <span class="sr-room__fac">
                                        @if($m->kapasitas)
                                            <span>
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                Kapasitas {{ $m->kapasitas }} orang
                                            </span>
                                        @endif
                                        @if($fasilitas)
                                            <span>
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg>
                                                {{ implode(', ', $fasilitas) }}
                                            </span>
                                        @elseif($m->lokasi)
                                            <span>{{ $m->lokasi }}</span>
                                        @endif
                                    </span>
                                    <span class="sr-room__rules">
                                        <span>{{ $hariLabel }}</span>
                                        <span>{{ $a['jam_buka'] }}–{{ $a['jam_tutup'] }}</span>
                                        <span>maks {{ intdiv($a['max_duration_minutes'], 60) ?: 1 }}{{ $a['max_duration_minutes'] >= 60 ? ' jam' : ' '.$a['max_duration_minutes'].' mnt' }}</span>
                                        <span>s.d. H+{{ $a['max_advance_days'] }}</span>
                                    </span>
                                </span>
                                <span class="sr-room__state">
                                    <span class="sr-room__avail sp-status sp-status--ok" data-avail>Tersedia</span>
                                    <span class="sr-room__radio" aria-hidden="true"></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="sp-card" aria-labelledby="srAvailTitle">
                    <header class="sp-card__head">
                        <div>
                            <p class="sp-card__kicker">LANGKAH 2</p>
                            <h2 id="srAvailTitle" class="sp-card__title">Jadwal Ketersediaan</h2>
                            <p class="sp-card__sub">Pilih tanggal dan durasi pemakaian yang diinginkan.</p>
                        </div>
                    </header>

                    <div class="sr-avail__row">
                        <div class="sp-field" style="margin:0">
                            <label class="sp-label" for="srTanggalPick">Tanggal</label>
                            <input type="date" id="srTanggalPick" class="sp-input" value="{{ old('tanggal', $now->toDateString()) }}">
                        </div>
                        <div class="sp-field" style="margin:0">
                            <span class="sp-label">Durasi Pemakaian</span>
                            <div class="sr-dur" id="srDurasi" role="radiogroup" aria-label="Durasi pemakaian"></div>
                        </div>
                    </div>

                    <p class="sr-slots__msg" id="srSlotsMsg" role="alert" hidden></p>
                    <div class="sr-slots" id="srSlots" aria-live="polite"></div>

                    <div class="sr-legend" aria-hidden="true">
                        <span><i class="sr-legend__dot sr-legend__dot--ok"></i>Tersedia</span>
                        <span><i class="sr-legend__dot sr-legend__dot--busy"></i>Sudah dipesan</span>
                        <span><i class="sr-legend__dot sr-legend__dot--pick"></i>Pilihan Anda</span>
                    </div>
                </section>
            </div>

            {{-- KOLOM KANAN: form dengan progres 1-2-3 --}}
            <div class="sr-col">
                <section class="sp-card sr-form" aria-labelledby="srFormTitle">
                    <header class="sp-card__head">
                        <div>
                            <h2 id="srFormTitle" class="sp-card__title">Form Pengajuan Pemakaian</h2>
                        </div>
                    </header>

                    <ol class="sr-steps" id="srSteps" aria-label="Progres pengajuan">
                        <li class="sr-steps__item is-active" data-step-dot="1"><span class="sr-steps__num">1</span><span class="sr-steps__label">Detail Pengajuan</span></li>
                        <li class="sr-steps__item" data-step-dot="2"><span class="sr-steps__num">2</span><span class="sr-steps__label">Konfirmasi</span></li>
                        <li class="sr-steps__item" data-step-dot="3"><span class="sr-steps__num">3</span><span class="sr-steps__label">Selesai</span></li>
                    </ol>

                    @if($errors->any())
                        <div class="sp-flash sp-flash--danger" role="alert" style="margin-bottom:14px">
                            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                        </div>
                    @endif

                    @if($terkirim)
                        <div class="sr-success" role="status">
                            <span class="sr-success__icon" aria-hidden="true">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                            </span>
                            <strong>Pengajuan terkirim</strong>
                            <span class="sp-status sp-status--warn">Menunggu keputusan Laboran</span>
                        </div>
                        <dl class="sr-review">
                            <div><dt>Nomor</dt><dd>#{{ $terkirim['id'] }}</dd></div>
                            <div><dt>Ruangan</dt><dd>{{ $terkirim['ruangan'] }}</dd></div>
                            <div><dt>Waktu</dt><dd>{{ \Illuminate\Support\Carbon::parse($terkirim['tanggal'])->isoFormat('dddd, D MMMM Y') }} · {{ $terkirim['jam_mulai'] }}–{{ $terkirim['jam_selesai'] }}</dd></div>
                            <div><dt>Tujuan</dt><dd>{{ $terkirim['tujuan'] }}</dd></div>
                            <div><dt>Peserta</dt><dd>{{ implode(', ', $terkirim['peserta']) }}</dd></div>
                        </dl>
                        <p class="sp-note">Izin pemakaian terbit per peserta setelah disetujui laboran — pengajuan tidak otomatis disetujui.</p>
                        <div class="sr-form__actions">
                            <a href="{{ route('mahasiswa.ruangan', ['tab' => 'riwayat']) }}#riwayat" class="sp-btn sp-btn--primary">Lihat Riwayat Pengajuan</a>
                        </div>
                    @else
                        <form method="POST" action="{{ route('mahasiswa.ruangan.store') }}" id="srForm" novalidate>
                            @csrf

                            {{-- LANGKAH 1: detail --}}
                            <div data-step="1">
                                <div class="sp-field">
                                    <label class="sp-label" for="srRuangan">Ruangan <span class="sp-req">*</span></label>
                                    <select id="srRuangan" name="ruangan_id" class="sp-input" required>
                                        @foreach($ruanganList as $r)
                                            <option value="{{ $r['model']->id }}" @selected(old('ruangan_id') == $r['model']->id)>{{ $r['model']->nama }}@if($r['model']->lokasi) — {{ $r['model']->lokasi }}@endif</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sr-fields-2">
                                    <div class="sp-field">
                                        <label class="sp-label" for="srTanggal">Tanggal Pemakaian <span class="sp-req">*</span></label>
                                        <input type="date" id="srTanggal" name="tanggal" class="sp-input" value="{{ old('tanggal', $now->toDateString()) }}" required>
                                    </div>
                                    <div class="sp-field">
                                        <span class="sp-label">Waktu terpilih</span>
                                        <div class="sr-timepick" id="srTimePick" aria-live="polite">—</div>
                                    </div>
                                </div>
                                <div class="sr-fields-2">
                                    <div class="sp-field">
                                        <label class="sp-label" for="srMulai">Jam Mulai <span class="sp-req">*</span></label>
                                        <input type="text" id="srMulai" name="jam_mulai" class="sp-input" value="{{ old('jam_mulai') }}" required
                                               inputmode="numeric" maxlength="5" placeholder="07:00" pattern="[0-2][0-9]:[0-5][0-9]"
                                               autocomplete="off" title="Format 24 jam HH:MM">
                                    </div>
                                    <div class="sp-field">
                                        <label class="sp-label" for="srSelesai">Jam Selesai <span class="sp-req">*</span></label>
                                        <input type="text" id="srSelesai" name="jam_selesai" class="sp-input" value="{{ old('jam_selesai') }}" required readonly
                                               inputmode="numeric" maxlength="5" placeholder="08:00" tabindex="-1" aria-readonly="true"
                                               autocomplete="off" title="Dihitung otomatis dari jam mulai + durasi">
                                    </div>
                                </div>
                                <p class="sp-field-error" id="srTimeError" hidden></p>
                                <div class="sp-field">
                                    <label class="sp-label" for="srTujuan">Tujuan Pemakaian <span class="sp-req">*</span></label>
                                    <input type="text" id="srTujuan" name="tujuan" class="sp-input" maxlength="500" value="{{ old('tujuan') }}" placeholder="Contoh: rapat koordinasi tim proyek" required>
                                </div>
                                <div class="sp-field">
                                    <span class="sp-label">Tim / Kelompok</span>
                                    <div class="sr-teamline">{{ $tim->nama }}@if($tim->kode) · {{ $tim->kode }}@endif</div>
                                </div>
                                <div class="sp-field">
                                    <span class="sp-label">Peserta (<span id="srPesertaCount">{{ count($pesertaOld) }}</span> orang<span id="srPesertaCap"></span>)</span>
                                    <div class="sr-picked" id="srPicked"></div>
                                    <div id="srPesertaInputs">
                                        @foreach($anggotaList as $a)
                                            @if(in_array($a['id'], $pesertaOld))
                                                <input type="hidden" name="peserta[]" value="{{ $a['id'] }}" data-peserta-id="{{ $a['id'] }}">
                                            @endif
                                        @endforeach
                                    </div>
                                    <button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" id="srAddPeserta" style="margin-top:6px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                        Tambah Peserta
                                    </button>
                                    <small class="sp-muted" style="display:block;margin-top:4px">Hanya anggota tim yang berwenang dapat dipilih.</small>
                                </div>
                                <div class="sr-form__actions">
                                    <button type="button" class="sp-btn sp-btn--ghost" id="srCancel" onclick="window.location='{{ route('mahasiswa.beranda') }}'">Batal</button>
                                    <button type="button" class="sp-btn sp-btn--primary" id="srNext" disabled>Lanjutkan</button>
                                </div>
                            </div>

                            {{-- LANGKAH 2: konfirmasi --}}
                            <div data-step="2" hidden>
                                <dl class="sr-review" id="srReview"></dl>
                                <p class="sp-note">Periksa kembali. Pengajuan yang terkirim berstatus <strong>menunggu keputusan laboran</strong>, bukan otomatis disetujui.</p>
                                <div class="sr-form__actions">
                                    <button type="button" class="sp-btn sp-btn--ghost" id="srBack">Kembali</button>
                                    <button type="submit" class="sp-btn sp-btn--primary" id="srSubmit">Kirim Pengajuan</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </section>
            </div>
        </div>

        {{-- Modal pemilihan peserta --}}
        <div class="sp-modal-backdrop" id="srPesertaModal" role="dialog" aria-modal="true" aria-labelledby="srPesertaModalTitle" hidden>
            <div class="sp-modal sp-modal--wide">
                <div class="sp-modal__head">
                    <div>
                        <h3 id="srPesertaModalTitle" class="sp-modal__title">Pilih Peserta</h3>
                        <p class="sp-modal__meta">Anggota tim <strong>{{ $tim->nama }}</strong> — <span id="srPesertaModalCount">0</span> dipilih<span id="srPesertaModalCap"></span>.</p>
                    </div>
                    <button type="button" class="sp-icon-btn sp-modal__close" id="srPesertaModalClose" aria-label="Tutup pilihan peserta">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="sp-modal__body">
                    <div class="stp-toolbar__search" style="margin-bottom:12px;max-width:none">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="search" id="srPesertaSearch" class="sp-input" placeholder="Cari anggota…" aria-label="Cari anggota tim">
                    </div>
                    <div class="sr-pickall" id="srPickAllWrap">
                        <label class="sr-pickall__btn">
                            <input type="checkbox" id="srPickAll"> <span>Pilih semua anggota tim</span>
                        </label>
                        <span class="sp-muted" id="srPickAllNote" style="font-size:12px"></span>
                    </div>
                    <ul class="sr-memberlist" id="srMemberList">
                        @foreach($anggotaList as $a)
                            <li class="sr-member" data-search="{{ mb_strtolower($a['nama'] . ' ' . $a['nim']) }}">
                                <label>
                                    <input type="checkbox" value="{{ $a['id'] }}" @checked(in_array($a['id'], $pesertaOld))>
                                    <span class="sp-avatar sp-avatar--sm">
                                        @if($a['foto'])<img src="{{ $a['foto'] }}" alt="" onerror="this.remove()">@endif
                                        <span>{{ $initialsOf($a['nama']) }}</span>
                                    </span>
                                    <span class="sr-member__meta">
                                        <strong>{{ $a['nama'] }}</strong>
                                        <small>{{ $a['nim'] }}</small>
                                    </span>
                                    @if($a['id'] === auth()->id())<span class="sp-badge sp-badge--info">Anda</span>@endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <p class="sp-empty" id="srMemberEmpty" hidden>Tidak ada anggota yang cocok.</p>
                </div>
                <div class="sp-modal__foot">
                    <button type="button" class="sp-btn sp-btn--primary sp-btn--sm" id="srPesertaModalDone">Selesai</button>
                </div>
            </div>
        </div>
    @endif

    <script type="application/json" id="srAnggotaData">{!! json_encode($anggotaList->map(fn($a) => ['id' => $a['id'], 'nama' => $a['nama']])->values(), JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- RIWAYAT — progres: Diajukan → Keputusan Laboran → Izin penggunaan --}}
    <section class="sp-card" id="riwayat" aria-labelledby="srHistoryTitle">
        <header class="sp-card__head">
            <div>
                <h2 id="srHistoryTitle" class="sp-card__title">Riwayat Pengajuan{{ $tim ? ' — ' . $tim->nama : '' }}</h2>
                <p class="sp-card__sub">Diajukan → Keputusan laboran → Izin penggunaan. Status mengikuti keputusan dan masa izin — bukan bukti ruangan benar-benar dipakai.</p>
            </div>
        </header>

        @forelse($pengajuan as $p)
            @php
                $st = $p->statusEfektif();
                $izin = $p->izin->first();
                $izinLabel = null;
                if ($st === 'disetujui') {
                    if ($izin) {
                        $izinLabel = match (true) {
                            $izin->status !== 'aktif' => 'Izin ' . $izin->status,
                            $izin->mulai->gt($now) => 'Belum mulai — berlaku ' . $izin->mulai->format('d M H:i'),
                            $izin->selesai->gte($now) => 'Berlaku s.d. ' . $izin->selesai->format('H:i'),
                            default => 'Berakhir',
                        };
                    } else {
                        $izinLabel = 'Izin untuk ' . count((array) ($p->peserta ?? [])) . ' peserta';
                    }
                }
                $steps = [
                    1 => ['label' => 'Diajukan', 'state' => 'done'],
                    2 => ['label' => 'Keputusan Laboran', 'state' => 'wait'],
                    3 => ['label' => 'Izin penggunaan', 'state' => 'wait'],
                ];
                if ($st === 'diajukan') {
                    $steps[2]['state'] = 'wait';
                } elseif ($st === 'menunggu_penerimaan') {
                    $steps[2] = ['label' => 'Tawaran pengganti', 'state' => 'active'];
                } elseif ($st === 'disetujui') {
                    $steps[2]['state'] = 'done';
                    $steps[3]['state'] = $izinLabel === 'Berakhir' ? 'done' : 'active';
                } elseif ($st === 'selesai') {
                    $steps[2]['state'] = 'done';
                    $steps[3]['state'] = 'done';
                } else {
                    $steps[2] = ['label' => $statusLabel[$st] ?? 'Keputusan', 'state' => 'fail'];
                }
            @endphp
            <article class="sr-item">
                <div class="sr-item__head">
                    <div>
                        <strong>{{ $p->ruangan?->nama ?? 'Ruangan' }}</strong>
                        <small class="sp-muted" style="display:block">#{{ $p->id }} · {{ $p->tujuan }}</small>
                    </div>
                    <span class="sp-status sp-status--{{ $chipTone[$st] ?? 'neutral' }}">{{ $statusLabel[$st] ?? $st }}</span>
                </div>

                <ol class="sr-tl" aria-label="Progres pengajuan">
                    @foreach($steps as $num => $step)
                        <li class="sr-tl__step sr-tl__step--{{ $step['state'] }}">
                            <span class="sr-tl__dot" aria-hidden="true">@if($step['state']==='done')<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>@elseif($step['state']==='fail')<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>@else{{ $num }}@endif</span>
                            <span class="sr-tl__label">{{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="sr-item__times">
                    <div class="sr-time"><span>Diminta</span> {{ $p->tanggal->isoFormat('ddd, D MMM Y') }} · {{ substr((string) $p->jam_mulai, 0, 5) }}–{{ substr((string) $p->jam_selesai, 0, 5) }}</div>
                    @if($p->pengganti_mulai)
                        <div class="sr-time sr-time--alt"><span>Pengganti</span> {{ $p->pengganti_mulai->isoFormat('ddd, D MMM Y') }} · {{ $p->pengganti_mulai->format('H:i') }}–{{ $p->pengganti_selesai?->format('H:i') }}@if($p->pengganti_catatan) — {{ $p->pengganti_catatan }}@endif</div>
                    @endif
                </div>

                <p class="sr-item__meta">
                    Pengaju: {{ $p->snapshot['pengaju']['name'] ?? '—' }}
                    · Peserta: {{ collect($p->snapshot['peserta'] ?? [])->pluck('name')->implode(', ') ?: '—' }}
                    @if($izinLabel) · <strong>{{ $izinLabel }}</strong>@endif
                    @if($p->alasan_keputusan) · <em>{{ $p->alasan_keputusan }}</em>@endif
                </p>

                <div class="sr-item__actions">
                    @if($st === 'menunggu_penerimaan' && $p->pengganti_mulai && (int) $p->pengaju_id === (int) auth()->id())
                        <form method="POST" action="{{ route('mahasiswa.ruangan.terima-pengganti', $p) }}">
                            @csrf
                            <button type="submit" class="sp-btn sp-btn--primary sp-btn--xs">Terima waktu pengganti</button>
                        </form>
                    @elseif($st === 'menunggu_penerimaan')
                        <small class="sp-muted">Menunggu penerimaan oleh pengaju.</small>
                    @endif
                    @if(in_array($st, ['diajukan', 'menunggu_penerimaan', 'disetujui'], true) && (int) $p->pengaju_id === (int) auth()->id())
                        <form method="POST" action="{{ route('mahasiswa.ruangan.batal', $p) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">
                            @csrf
                            <button type="submit" class="sp-btn sp-btn--ghost sp-btn--xs">Batalkan</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="sp-muted">Belum ada pengajuan. Riwayat tim akan tampil di sini setelah pengajuan pertama dikirim.</p>
        @endforelse
    </section>
</div>
@endsection
