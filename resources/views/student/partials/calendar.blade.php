{{--
    Kalender interaktif mahasiswa.
    Param: $calendar (hasil buildMonthCalendar), $scheduleMeta, $variant ('compact' | 'full')
    Grid dirender oleh public/js/student-shell.js dari data JSON berikut; navigasi bulan memakai
    GET mahasiswa.calendar.month sehingga sumber aturan tetap satu (AbsensiEffectiveSchedule).
--}}
@php
    $variant = $variant ?? 'compact';
    $wajibDays = collect($scheduleMeta['weekly'] ?? [])->where('wajib', true)->pluck('hari')->implode(', ');
@endphp
<div class="sp-cal sp-cal--{{ $variant }}"
     data-sp-calendar
     data-month-url="{{ route('mahasiswa.calendar.month') }}"
     data-history-url="{{ route('mahasiswa.absensi') }}">
    <script type="application/json" data-sp-calendar-data>@json($calendar)</script>

    <div class="sp-cal__main">
        <div class="sp-cal__toolbar">
            <h3 class="sp-cal__month" data-cal-month aria-live="polite">{{ $calendar['month_label'] }}</h3>
            <div class="sp-cal__nav">
                <a class="sp-icon-btn sp-icon-btn--bordered" href="?bulan={{ $calendar['prev_month'] }}" data-cal-prev aria-label="Bulan sebelumnya">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <a class="sp-btn sp-btn--ghost sp-btn--sm" href="?bulan={{ $calendar['today_month'] }}" data-cal-today>Hari ini</a>
                <a class="sp-icon-btn sp-icon-btn--bordered" href="?bulan={{ $calendar['next_month'] }}" data-cal-next aria-label="Bulan berikutnya">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>
        </div>

        <div class="sp-cal__weekdays" aria-hidden="true">
            <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
        </div>
        <div class="sp-cal__grid" role="grid" aria-label="Kalender absensi" data-cal-grid>
            <noscript><p class="sp-muted">Aktifkan JavaScript untuk menampilkan kalender interaktif.</p></noscript>
        </div>

        <div class="sp-cal__filter" role="group" aria-label="Filter penanda kalender">
            <button type="button" class="sp-cal__chip is-on" data-cal-filter="semua">Semua</button>
            <button type="button" class="sp-cal__chip" data-cal-filter="absensi">Absensi</button>
            <button type="button" class="sp-cal__chip" data-cal-filter="ruangan">Ruangan</button>
        </div>

        <ul class="sp-cal__legend" aria-label="Keterangan kalender">
            <li><span class="sp-cal__key sp-cal__key--wajib"></span>Hari wajib{{ $wajibDays ? ' (' . $wajibDays . ')' : '' }}</li>
            <li><span class="sp-cal__tag sp-cal__tag--libur">L</span>Libur</li>
            <li><span class="sp-cal__tag sp-cal__tag--jam">J</span>Perubahan jam</li>
            <li><span class="sp-cal__tag sp-cal__tag--agenda">A</span>Ujian / informasi</li>
            <li><span class="sp-cal__tag sp-cal__tag--room">R</span>Penggunaan ruangan</li>
            <li><span class="sp-cal__dot"></span>Ada rekaman absensi</li>
        </ul>
        @if(empty($scheduleMeta['kalender_lengkap']))
            <p class="sp-note">Jenis agenda kalender (ujian, informasi, perubahan jam) belum aktif di server. Saat ini hanya tanggal libur yang dibaca.</p>
        @endif
    </div>

    <aside class="sp-cal__detail" data-cal-detail aria-live="polite">
        <p class="sp-muted">Pilih tanggal untuk melihat jadwal, agenda, dan rekaman absensi.</p>
    </aside>
</div>
