@extends('layouts.student')

@section('title', 'Riwayat Absensi')
@section('page-title', 'Riwayat Absensi')

@section('content')
    <div class="sp-page">
        {{-- Pagehead foto kampus + judul --}}
        <section class="sp-pagehead" aria-label="Kepala halaman absensi">
            <p class="sp-pagehead__crumb">
                <a href="{{ route('mahasiswa.beranda') }}">Absensi</a>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                Riwayat Absensi
            </p>
            <div class="sp-pagehead__main">
                <span class="sp-pagehead__icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
                </span>
                <div>
                    <h2 class="sp-pagehead__title">Riwayat &amp; Penilaian Absensi Saya</h2>
                    <p class="sp-pagehead__sub">Lihat rekap kehadiran, detail absensi, dan catatan waktu Anda.</p>
                </div>
            </div>
        </section>

        {{-- Ringkasan periode + statistik --}}
        <div class="sat-summary" aria-label="Ringkasan Periode Absensi">
            <div class="sat-period">
                <span class="sat-period__label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Periode Data
                </span>
                <strong class="sat-period__range">{{ $summary['range_label'] }}</strong>
                <span class="sat-period__meta">Sesi {{ ucfirst($user->shift ?? 'pagi') }} · {{ $summary['kebijakan_label'] }}</span>
            </div>
            <a href="{{ route('mahasiswa.absensi', ['status' => 'lengkap']) }}" class="sat-stat" title="Filter Hadir Lengkap">
                <span class="sat-stat__label"><span class="sat-stat__icon sat-ico--ok"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg></span> Hadir Lengkap</span>
                <strong class="sat-stat__value">{{ $summary['lengkap'] }} <small>hari</small></strong>
                <span class="sat-stat__hint">Tap masuk &amp; pulang tercatat</span>
            </a>
            <a href="{{ route('mahasiswa.absensi', ['status' => 'tidak_lengkap']) }}" class="sat-stat" title="Filter Tidak Lengkap">
                <span class="sat-stat__label"><span class="sat-stat__icon sat-ico--warn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 8v5M12 16.5v.5"/></svg></span> Tidak Lengkap</span>
                <strong class="sat-stat__value">{{ $summary['tidak_lengkap'] }} <small>hari</small></strong>
                <span class="sat-stat__hint">Hanya masuk / belum tap pulang</span>
            </a>
            <a href="{{ route('mahasiswa.absensi', ['status' => 'alfa']) }}" class="sat-stat" title="Filter Alfa">
                <span class="sat-stat__label"><span class="sat-stat__icon sat-ico--danger"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></span> Alfa</span>
                <strong class="sat-stat__value">{{ $summary['alfa'] }} <small>hari</small></strong>
                <span class="sat-stat__hint">Tidak ada rekaman tap</span>
            </a>
            <a href="{{ route('mahasiswa.absensi', ['status' => 'catatan_waktu']) }}" class="sat-stat" title="Filter Catatan Waktu">
                <span class="sat-stat__label"><span class="sat-stat__icon sat-ico--info"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span> Catatan Waktu</span>
                <strong class="sat-stat__value">{{ $summary['catatan_waktu'] }} <small>hari</small></strong>
                <span class="sat-stat__hint">Terlambat / pulang lebih awal</span>
            </a>
        </div>

        {{-- Filter & Daftar Riwayat Tap Lab --}}
        <section class="sp-card" aria-label="Daftar Riwayat Absensi">
            <div class="sp-filter-bar">
                <form method="GET" action="{{ route('mahasiswa.absensi') }}" class="sp-filter-form sat-filter">
                    <div class="sp-filter-group">
                        <label for="spFilterStatus">Filter Data</label>
                        <select id="spFilterStatus" name="status" class="sp-select" onchange="this.form.submit()">
                            <option value="semua" @selected($filterStatus === 'semua')>Semua Status ({{ $summary['total'] }})</option>
                            <option value="lengkap" @selected($filterStatus === 'lengkap')>Hadir Lengkap ({{ $summary['lengkap'] }})</option>
                            <option value="tidak_lengkap" @selected($filterStatus === 'tidak_lengkap')>Tidak Lengkap / Hanya Masuk ({{ $summary['tidak_lengkap'] }})</option>
                            <option value="alfa" @selected($filterStatus === 'alfa')>Alfa ({{ $summary['alfa'] }})</option>
                            <option value="catatan_waktu" @selected($filterStatus === 'catatan_waktu')>Ada Catatan Ketepatan Waktu ({{ $summary['catatan_waktu'] }})</option>
                        </select>
                    </div>

                    <div class="sp-filter-group">
                        <label for="spFilterBulan">Bulan</label>
                        <select id="spFilterBulan" name="bulan" class="sp-select" onchange="this.form.submit()">
                            <option value="semua" @selected($filterBulan === 'semua')>Semua Bulan</option>
                            @foreach($availableMonths as $m)
                                <option value="{{ $m['value'] }}" @selected($filterBulan === $m['value'])>{{ $m['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($filterStatus !== 'semua' || $filterBulan !== 'semua')
                        <a href="{{ route('mahasiswa.absensi') }}" class="sp-btn sp-btn--ghost sp-btn--xs">Reset Filter</a>
                    @endif
                </form>

                @if($filterStatus !== 'semua' || $filterBulan !== 'semua')
                    <div class="sp-filter-note">
                        <span class="sp-badge sp-badge--info">Filter aktif</span>
                        @if($filterStatus !== 'semua') Status: <strong>{{ str_replace('_', ' ', ucfirst($filterStatus)) }}</strong>@endif
                        @if($filterBulan !== 'semua') · Bulan: <strong>{{ collect($availableMonths)->firstWhere('value', $filterBulan)['label'] ?? $filterBulan }}</strong>@endif
                        · {{ $rows->count() }} baris ditemukan.
                    </div>
                @endif
                <div class="sp-filter-note">
                    Kelengkapan rekaman tap (<strong>Masuk &amp; Pulang</strong>) dinilai terpisah dari <strong>Ketepatan Waktu</strong>.
                </div>
            </div>

            @if($rows->isEmpty())
                <div class="sp-empty">
                    Tidak ada data yang cocok dengan filter absensi saat ini.
                </div>
            @else
                {{-- Tabel Desktop --}}
                <div class="sp-table-wrap sp-att-desktop-table">
                    <table class="sp-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jadwal Efektif</th>
                                <th>Tap Masuk</th>
                                <th>Tap Pulang</th>
                                <th>Kelengkapan</th>
                                <th>Ketepatan Waktu</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php
                                    $humanDate = \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y');
                                    $reportRef = $row['kelengkapan_label'] . ($row['ringkasan_ketepatan'] !== '—' ? ' (' . $row['ringkasan_ketepatan'] . ')' : '');
                                    $detailPayload = [
                                        'tanggal' => $row['tanggal'],
                                        'tanggal_human' => $row['hari_nama'] . ', ' . $humanDate,
                                        'sesi' => strtoupper((string) ($row['sesi'] ?? 'pagi')),
                                        'jadwal' => $row['jadwal_masuk'] . ' – ' . $row['jadwal_pulang'] . ' WIB',
                                        'jam_masuk' => $row['jam_masuk'] ?? '—',
                                        'jam_pulang' => $row['jam_pulang'] ?? '—',
                                        'kelengkapan_label' => $row['kelengkapan_label'],
                                        'kelengkapan_tone' => $row['kelengkapan_tone'],
                                        'kelengkapan_desc' => $row['kelengkapan_desc'],
                                        'ketepatan_masuk_label' => $row['ketepatan_masuk_label'],
                                        'ketepatan_masuk_tone' => $row['ketepatan_masuk_tone'],
                                        'ketepatan_pulang_label' => $row['ketepatan_pulang_label'],
                                        'ketepatan_pulang_tone' => $row['ketepatan_pulang_tone'],
                                        'slot_lab' => $row['slot_lab_masuk'] ? 'Slot S' . $row['slot_lab_masuk'] . ' (sesi waktu masuk)' : 'Tidak ada slot tercatat',
                                        'jadwal_konsisten' => (bool) ($row['jadwal_konsisten'] ?? true),
                                        'report_url' => route('mahasiswa.suggestions', ['kategori' => 'absensi', 'tanggal' => $row['tanggal'], 'ref' => $reportRef]),
                                    ];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="sat-day">
                                            <span class="sat-day__stripe sat-day__stripe--{{ $row['kelengkapan_tone'] }}" aria-hidden="true"></span>
                                            <div style="padding-left:10px">
                                                <div class="sp-table__cell-main">{{ $row['hari_nama'] }}, {{ $humanDate }}</div>
                                                <div class="sp-table__cell-sub sp-mono">{{ $row['tanggal'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="sp-mono">{{ $row['jadwal_masuk'] }} – {{ $row['jadwal_pulang'] }}</span>
                                        <div class="sp-table__cell-sub">Sesi {{ ucfirst($row['sesi'] ?? 'pagi') }}</div>
                                        @if(! ($row['jadwal_konsisten'] ?? true))
                                            <div class="sp-table__cell-sub" style="color:var(--sp-warn,#b45309)" title="Penilaian ketepatan baris ini dihitung dengan aturan jadwal yang berlaku saat tap, bukan jadwal yang ditampilkan.">± dinilai dgn aturan saat itu</div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="sp-mono">{{ $row['jam_masuk'] ?? '—' }}</strong>
                                        <div class="sp-table__tone--{{ $row['ketepatan_masuk_tone'] }}">
                                            {{ $row['ketepatan_masuk_label'] }}
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="sp-mono">{{ $row['jam_pulang'] ?? '—' }}</strong>
                                        <div class="sp-table__tone--{{ $row['ketepatan_pulang_tone'] }}">
                                            {{ $row['ketepatan_pulang_label'] }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="sp-badge sp-badge--{{ $row['kelengkapan_tone'] }}">
                                            {{ $row['kelengkapan_label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($row['ringkasan_ketepatan'] !== '—')
                                            <span class="sp-badge sp-badge--{{ $row['ringkasan_ketepatan_tone'] }}">
                                                {{ $row['ringkasan_ketepatan'] }}
                                            </span>
                                        @else
                                            <span class="sp-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="sp-table-actions">
                                            <button type="button"
                                                    class="sp-btn sp-btn--ghost sp-btn--xs"
                                                    data-att-detail="{{ json_encode($detailPayload) }}"
                                                    title="Lihat rincian lengkap sesi ini">
                                                Rincian
                                            </button>
                                            <a href="{{ route('mahasiswa.suggestions', ['kategori' => 'absensi', 'tanggal' => $row['tanggal'], 'ref' => $reportRef]) }}"
                                               class="sp-btn sp-btn--ghost sp-btn--xs"
                                               title="Laporkan kendala absensi tanggal ini ke Laboran">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                                <span>Lapor</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kartu Mobile Responsif (Layar <= 900px) --}}
                <div class="sp-att-mobile-cards">
                    @foreach($rows as $row)
                        @php
                            $humanDate = \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y');
                            $reportRef = $row['kelengkapan_label'] . ($row['ringkasan_ketepatan'] !== '—' ? ' (' . $row['ringkasan_ketepatan'] . ')' : '');
                            $detailPayload = [
                                'tanggal' => $row['tanggal'],
                                'tanggal_human' => $row['hari_nama'] . ', ' . $humanDate,
                                'sesi' => strtoupper((string) ($row['sesi'] ?? 'pagi')),
                                'jadwal' => $row['jadwal_masuk'] . ' – ' . $row['jadwal_pulang'] . ' WIB',
                                'jam_masuk' => $row['jam_masuk'] ?? '—',
                                'jam_pulang' => $row['jam_pulang'] ?? '—',
                                'kelengkapan_label' => $row['kelengkapan_label'],
                                'kelengkapan_tone' => $row['kelengkapan_tone'],
                                'kelengkapan_desc' => $row['kelengkapan_desc'],
                                'ketepatan_masuk_label' => $row['ketepatan_masuk_label'],
                                'ketepatan_masuk_tone' => $row['ketepatan_masuk_tone'],
                                'ketepatan_pulang_label' => $row['ketepatan_pulang_label'],
                                'ketepatan_pulang_tone' => $row['ketepatan_pulang_tone'],
                                'slot_lab' => $row['slot_lab_masuk'] ? 'Slot S' . $row['slot_lab_masuk'] . ' (sesi waktu masuk)' : 'Tidak ada slot tercatat',
                                'jadwal_konsisten' => (bool) ($row['jadwal_konsisten'] ?? true),
                                'report_url' => route('mahasiswa.suggestions', ['kategori' => 'absensi', 'tanggal' => $row['tanggal'], 'ref' => $reportRef]),
                            ];
                        @endphp
                        <article class="sp-att-card" style="border-left:3px solid var(--sp-{{ $row['kelengkapan_tone'] === 'danger' ? 'danger' : $row['kelengkapan_tone'] }})">
                            <div class="sp-att-card__head">
                                <div>
                                    <h3 class="sp-att-card__title">{{ $row['hari_nama'] }}, {{ $humanDate }}</h3>
                                    <p class="sp-att-card__sub">Jadwal: {{ $row['jadwal_masuk'] }} – {{ $row['jadwal_pulang'] }} WIB (Sesi {{ ucfirst($row['sesi'] ?? 'pagi') }})
                                        @if(! ($row['jadwal_konsisten'] ?? true)) · <span title="Penilaian ketepatan baris ini dihitung dengan aturan jadwal yang berlaku saat tap, bukan jadwal yang ditampilkan.">± aturan saat itu</span>@endif
                                    </p>
                                </div>
                                <div class="sp-att-card__badges">
                                    <span class="sp-badge sp-badge--{{ $row['kelengkapan_tone'] }}">{{ $row['kelengkapan_label'] }}</span>
                                    @if($row['ringkasan_ketepatan'] !== '—')
                                        <span class="sp-badge sp-badge--{{ $row['ringkasan_ketepatan_tone'] }}">{{ $row['ringkasan_ketepatan'] }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="sp-att-card__times">
                                <div class="sp-att-card__time-block">
                                    <span>Tap Masuk</span>
                                    <strong class="sp-mono">{{ $row['jam_masuk'] ?? '—' }}</strong>
                                    <small class="sp-table__tone--{{ $row['ketepatan_masuk_tone'] }}">{{ $row['ketepatan_masuk_label'] }}</small>
                                </div>
                                <div class="sp-att-card__time-block">
                                    <span>Tap Pulang</span>
                                    <strong class="sp-mono">{{ $row['jam_pulang'] ?? '—' }}</strong>
                                    <small class="sp-table__tone--{{ $row['ketepatan_pulang_tone'] }}">{{ $row['ketepatan_pulang_label'] }}</small>
                                </div>
                            </div>

                            <div class="sp-att-card__foot">
                                <button type="button"
                                        class="sp-btn sp-btn--ghost sp-btn--xs"
                                        data-att-detail="{{ json_encode($detailPayload) }}">
                                    Rincian Sesi
                                </button>
                                <a href="{{ route('mahasiswa.suggestions', ['kategori' => 'absensi', 'tanggal' => $row['tanggal'], 'ref' => $reportRef]) }}"
                                   class="sp-btn sp-btn--ghost sp-btn--xs">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                    <span>Lapor Kendala</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Modal Rincian Absensi Harian + Aksi Laporkan Masalah Absensi --}}
    <div class="sp-modal-backdrop" id="spAttDetailModal" role="dialog" aria-modal="true" aria-labelledby="spAttModalTitle" hidden>
        <div class="sp-modal">
            <div class="sp-modal__head">
                <div>
                    <span class="sp-badge sp-badge--info" id="spAttModalBadge">Rincian Kehadiran</span>
                    <h3 id="spAttModalTitle" class="sp-modal__title">Detail Absensi</h3>
                    <p class="sp-modal__meta" id="spAttModalSubtitle"></p>
                </div>
                <button type="button" class="sp-icon-btn sp-modal__close" id="spAttModalCloseBtn" aria-label="Tutup rincian absensi">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="sp-modal__body">
                <dl class="sp-detail-dl">
                    <div>
                        <dt>Jadwal Efektif</dt>
                        <dd id="spAttModalJadwal">—</dd>
                    </div>
                    <div>
                        <dt>Status Kelengkapan</dt>
                        <dd id="spAttModalKelengkapan">—</dd>
                    </div>
                    <div>
                        <dt>Tap Masuk &amp; Ketepatan</dt>
                        <dd id="spAttModalMasuk">—</dd>
                    </div>
                    <div>
                        <dt>Tap Pulang &amp; Ketepatan</dt>
                        <dd id="spAttModalPulang">—</dd>
                    </div>
                    <div>
                        <dt>Informasi Teknis Slot Lab</dt>
                        <dd id="spAttModalSlot">—</dd>
                    </div>
                </dl>
            </div>
            <div class="sp-modal__foot">
                <a href="#" class="sp-btn sp-btn--primary sp-btn--sm" id="spAttModalReportBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                    <span>Laporkan Kendala Tanggal Ini</span>
                </a>
                <button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" id="spAttModalDoneBtn">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('spAttDetailModal');
            const closeBtn = document.getElementById('spAttModalCloseBtn');
            const doneBtn = document.getElementById('spAttModalDoneBtn');
            const reportBtn = document.getElementById('spAttModalReportBtn');
            const titleEl = document.getElementById('spAttModalTitle');
            const subEl = document.getElementById('spAttModalSubtitle');
            const jadwalEl = document.getElementById('spAttModalJadwal');
            const kelengkapanEl = document.getElementById('spAttModalKelengkapan');
            const masukEl = document.getElementById('spAttModalMasuk');
            const pulangEl = document.getElementById('spAttModalPulang');
            const slotEl = document.getElementById('spAttModalSlot');

            function closeModal() {
                if (modal) modal.hidden = true;
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (doneBtn) doneBtn.addEventListener('click', closeModal);
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
            });

            document.querySelectorAll('[data-att-detail]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    try {
                        const d = JSON.parse(this.getAttribute('data-att-detail'));
                        if (titleEl) titleEl.textContent = 'Detail Absensi — ' + d.tanggal_human;
                        if (subEl) subEl.textContent = 'Tanggal ' + d.tanggal + ' · Sesi ' + d.sesi;
                        if (jadwalEl) {
                            jadwalEl.textContent = d.jadwal;
                            if (d.jadwal_konsisten === false) {
                                jadwalEl.innerHTML = d.jadwal + ' <span class="sp-muted" style="font-size:11px">· dinilai dengan aturan jadwal saat tap</span>';
                            }
                        }
                        if (kelengkapanEl) {
                            kelengkapanEl.innerHTML = '<span class="sp-badge sp-badge--' + d.kelengkapan_tone + '">' + d.kelengkapan_label + '</span> <span class="sp-muted" style="margin-left:6px">' + (d.kelengkapan_desc || '') + '</span>';
                        }
                        if (masukEl) {
                            masukEl.innerHTML = '<strong class="sp-mono">' + d.jam_masuk + '</strong> <span class="sp-table__tone--' + d.ketepatan_masuk_tone + '" style="margin-left:6px">(' + d.ketepatan_masuk_label + ')</span>';
                        }
                        if (pulangEl) {
                            pulangEl.innerHTML = '<strong class="sp-mono">' + d.jam_pulang + '</strong> <span class="sp-table__tone--' + d.ketepatan_pulang_tone + '" style="margin-left:6px">(' + d.ketepatan_pulang_label + ')</span>';
                        }
                        if (slotEl) slotEl.textContent = d.slot_lab || '—';
                        if (reportBtn && d.report_url) reportBtn.href = d.report_url;

                        if (modal) modal.hidden = false;
                    } catch (err) {
                        console.error('Error displaying attendance detail:', err);
                    }
                });
            });
        });
    </script>
@endsection
