@extends('layouts.student')

@section('title', 'Beranda')
@section('page-title', 'Beranda')

@section('content')
    @php
        $tz = 'Asia/Jakarta';
        $now = now($tz);
        $hour = (int) $now->format('G');
        $salam = $hour < 11 ? 'pagi' : ($hour < 15 ? 'siang' : ($hour < 18 ? 'sore' : 'malam'));
        $firstName = \Illuminate\Support\Str::of((string) $user->nama_lengkap)->explode(' ')->first();

        $todayCarbon = \Carbon\Carbon::parse($todaySchedule['tanggal'], $tz);
        $rec = $todayRecord;
        $ps = $periodSummary;

        $next = $bms['berikutnya'] ?? null;
        $izinBerlaku = (bool) ($bms['izin_berlaku'] ?? false);
        $perhatian = $bms['perhatian'] ?? collect();
        $timBms = $bms['tim'] ?? null;
        $ketuaTim = $timBms ? $timBms->ketuaMember()->with('user')->first()?->user?->nama_lengkap : null;
        $anggotaCount = (int) ($bms['anggota_count'] ?? 0);

        $fingerStatus = $user->hasFingerprintEnrolled()
            ? 'terdaftar'
            : ($user->finger_id ? 'ID #' . $user->finger_id . ' dialokasikan (belum rekam sidik jari)' : 'belum dialokasikan');

        $statusMeta = [
            'diajukan' => ['label' => 'Menunggu keputusan', 'tone' => 'warn'],
            'menunggu_penerimaan' => ['label' => 'Tawaran waktu pengganti', 'tone' => 'info'],
            'disetujui' => ['label' => 'Disetujui', 'tone' => 'ok'],
            'ditolak' => ['label' => 'Ditolak', 'tone' => 'danger'],
        ];
    @endphp

    <div class="sp-page">
        {{-- Pengingat kontak pemulihan (bisa ditunda per sesi login) --}}
        @if(!empty($showContactPrompt))
            <section class="sp-banner" aria-label="Pengingat kontak pemulihan">
                <div class="sp-banner__body">
                    <h2>Lengkapi kontak pemulihan akun (opsional)</h2>
                    <p>
                        @if(empty($hasVerifiedEmail))
                            Email terverifikasi memungkinkan Anda memulihkan password sendiri bila lupa, tanpa menunggu bantuan Admin Laboran.
                        @else
                            Nomor HP belum diisi pada profil. Nomor ini mempermudah koordinasi operasional lab.
                        @endif
                    </p>
                </div>
                <div class="sp-banner__actions">
                    <a href="{{ route('mahasiswa.profile') }}#kontak-pemulihan" class="sp-btn sp-btn--primary sp-btn--sm">Lengkapi di profil</a>
                    <form method="POST" action="{{ route('mahasiswa.contact-prompt.dismiss') }}">
                        @csrf
                        <button type="submit" class="sp-btn sp-btn--ghost sp-btn--sm">Nanti saja</button>
                    </form>
                </div>
            </section>
        @endif

        {{-- 1. Sapaan ringkas + aksi utama --}}
        <section class="sb-hero" aria-label="Sapaan">
            <div class="sb-hero__body">
                <h2 class="sb-hero__greet">Selamat {{ $salam }}, {{ $firstName }}</h2>
                <p class="sb-hero__meta">
                    {{ $user->kelas ?: 'Kelas belum diatur' }}@if($user->angkatan) · {{ $user->angkatan }}@endif
                    @if($timBms) &nbsp;|&nbsp; Tim PBL: {{ $timBms->nama }}@endif
                </p>
            </div>
            <a href="{{ route('mahasiswa.ruangan') }}" class="sp-btn sp-btn--primary sb-hero__cta">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Ajukan Ruangan
            </a>
        </section>

        {{-- 2. Absensi hari ini + Ruangan berikutnya --}}
        <div class="sp-grid sp-grid--top">
            <section class="sp-card sp-today sp-today--{{ $todayState['tone'] }}" aria-labelledby="spTodayTitle">
                <header class="sp-card__head">
                    <div>
                        <p class="sp-card__kicker">ABSENSI HARI INI</p>
                        <h2 id="spTodayTitle" class="sp-card__title">{{ $todaySchedule['hari_nama'] }}, {{ $todayCarbon->translatedFormat('d F Y') }}</h2>
                    </div>
                    <span class="sp-status sp-status--{{ $todayState['tone'] }}">{{ $todayState['judul'] }}</span>
                </header>

                <p class="sp-today__msg">{{ $todayState['keterangan'] }}</p>

                <div class="sp-today__grid">
                    <div class="sp-today__cell">
                        <span class="sp-label">Jadwal Efektif</span>
                        <strong>{{ $todaySchedule['is_wajib'] ? $todaySchedule['jam_masuk'] . ' – ' . $todaySchedule['jam_pulang'] : ($todaySchedule['is_libur'] ? 'Libur' : 'Tidak wajib') }}</strong>
                        <small>{{ $todayState['sumber'] }}{{ $todayState['pengecualian'] ? ' · ' . $todayState['pengecualian'] : '' }}</small>
                    </div>
                    <div class="sp-today__cell">
                        <span class="sp-label">Masuk</span>
                        <strong class="sp-mono">{{ $rec['jam_masuk'] ?? '—' }}</strong>
                        <small>{{ ! empty($rec['jam_masuk']) ? $todayAssessment['ketepatan_masuk_label'] : 'Belum ada tap' }}</small>
                    </div>
                    <div class="sp-today__cell">
                        <span class="sp-label">Pulang</span>
                        <strong class="sp-mono">{{ $rec['jam_pulang'] ?? '—' }}</strong>
                        <small>{{ ! empty($rec['jam_pulang']) ? $todayAssessment['ketepatan_pulang_label'] : (in_array($todayState['kode'], ['berjalan']) ? 'Menunggu tap pulang' : 'Belum ada tap') }}</small>
                    </div>
                </div>

                <p class="sp-note" style="margin-top:10px">Sidik jari: {{ $fingerStatus }}.</p>

                <footer class="sp-card__foot sp-card__foot--compact">
                    <a href="{{ route('mahasiswa.absensi') }}" class="sp-link">
                        Lihat Riwayat Absensi
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                    @if(in_array($todayState['kode'], ['belum_pulang', 'tidak_lengkap', 'alfa', 'belum_tap_lewat'], true))
                        <button type="button" class="sp-btn sp-btn--ghost sp-btn--xs" data-open-help data-report-date="{{ $todaySchedule['tanggal'] }}" data-report-ref="{{ $todayState['judul'] }}">
                            Laporkan Kendala Hari Ini
                        </button>
                    @endif
                </footer>
            </section>

            <section class="sp-card sb-next" aria-labelledby="sbNextTitle">
                <header class="sp-card__head">
                    <div>
                        <p class="sp-card__kicker">RUANGAN BERIKUTNYA</p>
                        <h2 id="sbNextTitle" class="sp-card__title">
                            {{ $next?->ruangan?->nama ?? 'Belum ada jadwal' }}
                        </h2>
                    </div>
                    @if($next)
                        <span class="sp-status sp-status--{{ $izinBerlaku ? 'info' : 'ok' }}">{{ $izinBerlaku ? 'Izin berlaku' : 'Disetujui' }}</span>
                    @endif
                </header>

                @if($next)
                    <ul class="sb-next__facts">
                        <li>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M8 2.5v4M16 2.5v4M3.5 9.5h17"/></svg>
                            {{ $next->tanggal->translatedFormat('l, d F Y') }}
                        </li>
                        <li>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            {{ substr((string) $next->jam_mulai, 0, 5) }} – {{ substr((string) $next->jam_selesai, 0, 5) }}
                        </li>
                        <li>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 5.2a3.5 3.5 0 0 1 0 5.6M21.5 20a6.4 6.4 0 0 0-3.7-5.8"/></svg>
                            {{ $timBms?->nama ?? 'Pribadi' }} · {{ count($next->peserta ?? []) }} peserta
                        </li>
                    </ul>
                    <p class="sp-note">Persetujuan berarti izin terbit; bukan bukti ruangan benar-benar dipakai.</p>
                @else
                    <p class="sp-empty">Tidak ada pengajuan disetujui yang akan datang. Ajukan ruangan untuk tim Anda lewat halaman Ruangan.</p>
                @endif

                <footer class="sp-card__foot sp-card__foot--compact">
                    <a href="{{ route('mahasiswa.ruangan') }}" class="sp-link">
                        Buka Halaman Ruangan
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </footer>
            </section>
        </div>

        {{-- 3. Perlu perhatian — hanya muncul bila ada yang menunggu aksi/keputusan --}}
        @if($perhatian->isNotEmpty())
            <section class="sp-card sp-attn" aria-labelledby="spAttnTitle">
                <header class="sp-card__head">
                    <div>
                        <h2 id="spAttnTitle" class="sp-card__title">Perlu Perhatian</h2>
                        <p class="sp-card__sub">Tawaran waktu pengganti dan pengajuan yang menunggu keputusan</p>
                    </div>
                </header>
                <ul class="sp-attn__list">
                    @foreach($perhatian as $p)
                        @php
                            $meta = $statusMeta[$p->status] ?? ['label' => ucfirst($p->status), 'tone' => 'neutral'];
                            $isOffer = $p->status === \App\Models\RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN;
                        @endphp
                        <li class="sp-attn__item">
                            <div class="sp-attn__icon sp-attn__icon--{{ $meta['tone'] }}" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M8 2.5v4M16 2.5v4M3.5 9.5h17"/></svg>
                            </div>
                            <div class="sp-attn__body">
                                <strong>{{ $p->ruangan?->nama ?? 'Ruangan' }} — {{ $meta['label'] }}</strong>
                                <span>
                                    {{ $p->tanggal->translatedFormat('d M Y') }} ({{ substr((string) $p->jam_mulai, 0, 5) }}–{{ substr((string) $p->jam_selesai, 0, 5) }})
                                    @if($isOffer && $p->pengganti_mulai)
                                        → ditawarkan <b>{{ $p->pengganti_mulai->translatedFormat('d M Y H:i') }}–{{ $p->pengganti_selesai?->format('H:i') }}</b>
                                    @endif
                                </span>
                            </div>
                            <a href="{{ route('mahasiswa.ruangan') }}" class="sp-btn sp-btn--{{ $isOffer ? 'primary' : 'ghost' }} sp-btn--sm">
                                {{ $isOffer ? 'Tinjau Perubahan' : 'Lihat Pengajuan' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- 4. Kalender bulanan + panel tim --}}
        <div class="sp-grid sp-grid--mid">
            <section class="sp-card" aria-labelledby="spCalTitle">
                <header class="sp-card__head">
                    <div>
                        <h2 id="spCalTitle" class="sp-card__title">Kalender</h2>
                        <p class="sp-card__sub">Absensi, agenda, dan penggunaan ruangan per tanggal</p>
                    </div>
                    <a href="{{ route('mahasiswa.calendar') }}" class="sp-link">
                        Jadwal Lengkap
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </header>
                @include('student.partials.calendar', ['calendar' => $calendar, 'scheduleMeta' => $scheduleMeta, 'variant' => 'compact'])
            </section>

            <section class="sp-card sb-team" aria-labelledby="sbTeamTitle">
                <header class="sp-card__head">
                    <div>
                        <h2 id="sbTeamTitle" class="sp-card__title">Tim PBL</h2>
                        <p class="sp-card__sub">Tim Anda saat ini</p>
                    </div>
                </header>
                @if($timBms)
                    <div class="sb-team__name">
                        <span class="sb-team__mark" aria-hidden="true"><img src="{{ asset('branding/smartbrail-mark.svg') }}?v=1" alt="" width="26" height="26"></span>
                        <div>
                            <strong>{{ $timBms->nama }}</strong>
                            @if($timBms->manpro)<small>Manpro: {{ $timBms->manpro }}</small>@endif
                        </div>
                    </div>
                    <dl class="sb-team__facts">
                        <div><dt>Ketua Tim</dt><dd>{{ $ketuaTim ?: '—' }}</dd></div>
                        <div><dt>Anggota</dt><dd>{{ $anggotaCount }} mahasiswa</dd></div>
                        <div><dt>Pembimbing</dt><dd>{{ $timBms->co_manpro ?: $timBms->manpro ?: '—' }}</dd></div>
                    </dl>
                    <footer class="sp-card__foot sp-card__foot--compact">
                        <a href="{{ route('mahasiswa.team') }}" class="sp-link">
                            Lihat Detail Tim
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </footer>
                @else
                    <p class="sp-empty">Anda belum tergabung dalam tim PBL. Pengajuan ruangan tim akan tersedia setelah Anda masuk tim — hubungi dosen pembimbing atau laboran.</p>
                @endif
            </section>
        </div>

        {{-- 5. Ringkasan absensi + aktivitas terbaru --}}
        <div class="sp-grid sp-grid--bottom">
            <section class="sp-card sp-summary" aria-labelledby="spSummaryTitle">
                <header class="sp-card__head">
                    <div>
                        <h2 id="spSummaryTitle" class="sp-card__title">Ringkasan Absensi</h2>
                        <p class="sp-card__sub">{{ $ps['range_label'] }} · {{ $ps['total_sesi'] }} catatan hari terjadwal</p>
                    </div>
                    <form method="GET" class="sp-select-wrap">
                        <label for="spPeriode" class="sp-visually-hidden">Pilih Periode</label>
                        <select id="spPeriode" name="periode" class="sp-select" onchange="this.form.submit()">
                            @foreach($ps['options'] as $val => $label)
                                <option value="{{ $val }}" @selected((string) $ps['days'] === (string) $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="sp-btn sp-btn--ghost sp-btn--sm">Terapkan</button></noscript>
                    </form>
                </header>

                <div class="sp-stats">
                    <a class="sp-stat" href="{{ route('mahasiswa.absensi', ['status' => 'lengkap']) }}">
                        <span class="sp-stat__label"><span class="sp-dot sp-dot--ok"></span>Lengkap</span>
                        <strong class="sp-stat__value">{{ $ps['lengkap'] }}</strong>
                        <span class="sp-stat__hint">Tap masuk &amp; pulang</span>
                    </a>
                    <a class="sp-stat" href="{{ route('mahasiswa.absensi', ['status' => 'tidak_lengkap']) }}">
                        <span class="sp-stat__label"><span class="sp-dot sp-dot--warn"></span>Tidak Lengkap</span>
                        <strong class="sp-stat__value">{{ $ps['tidak_lengkap'] }}</strong>
                        <span class="sp-stat__hint">Pulang belum tercatat</span>
                    </a>
                    <a class="sp-stat" href="{{ route('mahasiswa.absensi', ['status' => 'alfa']) }}">
                        <span class="sp-stat__label"><span class="sp-dot sp-dot--danger"></span>Alfa</span>
                        <strong class="sp-stat__value">{{ $ps['alfa'] }}</strong>
                        <span class="sp-stat__hint">Tanpa tap hari wajib</span>
                    </a>
                    <a class="sp-stat" href="{{ route('mahasiswa.absensi', ['status' => 'catatan_waktu']) }}">
                        <span class="sp-stat__label"><span class="sp-dot sp-dot--info"></span>Catatan Waktu</span>
                        <strong class="sp-stat__value">{{ $ps['catatan_ketepatan'] }}</strong>
                        <span class="sp-stat__hint">{{ $ps['terlambat'] }} terlambat · {{ $ps['pulang_cepat'] }} pulang cepat</span>
                    </a>
                </div>
                <p class="sp-note">
                    Hari wajib saat ini: <strong>{{ $scheduleMeta['active_label'] }}</strong>.
                    @if($scheduleMeta['pending_weekly'])
                        @php
                            $upcomingDays = collect($scheduleMeta['upcoming_weekly'] ?? [])->where('wajib', true)->pluck('hari')->implode(', ');
                        @endphp
                        Jadwal mingguan baru{{ $upcomingDays !== '' ? ' (' . $upcomingDays . ')' : '' }} {{ $scheduleMeta['effective_from_label'] ? 'berlaku efektif mulai ' . $scheduleMeta['effective_from_label'] : 'menunggu tanggal efektif ditetapkan admin' }}.
                    @endif
                    Kelengkapan (Lengkap/Tidak Lengkap) dinilai terpisah dari ketepatan waktu.
                </p>
            </section>

            <section class="sp-card sp-recent" aria-labelledby="spRecentTitle">
                <header class="sp-card__head">
                    <div>
                        <h2 id="spRecentTitle" class="sp-card__title">Aktivitas Terbaru</h2>
                        <p class="sp-card__sub">5 sesi terjadwal terakhir</p>
                    </div>
                    <a href="{{ route('mahasiswa.absensi') }}" class="sp-link">Lihat Semua</a>
                </header>
                @if(count($recentRows))
                    <ul class="sp-recent__list">
                        @foreach($recentRows as $row)
                            <li class="sp-recent__item">
                                <div class="sp-recent__date">
                                    <strong>{{ \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</strong>
                                    <span>{{ $row['hari_nama'] }}</span>
                                </div>
                                <div class="sp-recent__times sp-mono">
                                    <span title="Jam Masuk">{{ $row['jam_masuk'] ?? '—' }}</span>
                                    <span class="sp-recent__sep">→</span>
                                    <span title="Jam Pulang">{{ $row['jam_pulang'] ?? '—' }}</span>
                                </div>
                                <span class="sp-status sp-status--{{ $row['kelengkapan_tone'] }}">{{ $row['kelengkapan_label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="sp-empty">Belum ada catatan absensi pada hari wajib dalam 12 bulan terakhir.</p>
                @endif
            </section>
        </div>
    </div>
@endsection
