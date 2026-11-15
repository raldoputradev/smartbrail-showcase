@extends('layouts.student')

@section('title', 'Jadwal & Kalender')
@section('page-title', 'Jadwal & Kalender')

@section('content')
    @php $meta = $scheduleMeta; @endphp
    <div class="sp-page">
        <div class="sp-grid sp-grid--cal">
            <section class="sp-card" aria-labelledby="spWeekTitle">
                <header class="sp-card__head">
                    <div>
                        <p class="sp-card__kicker">JADWAL LABORATORIUM</p>
                        <h2 id="spWeekTitle" class="sp-card__title">Jadwal mingguan</h2>
                        <p class="sp-card__sub">Sesi {{ ucfirst($meta['shift']) }} · Ditetapkan admin laboratorium</p>
                    </div>
                </header>
                <table class="sp-week">
                    <caption class="sp-visually-hidden">Jadwal absensi per hari yang berlaku saat ini</caption>
                    <tbody>
                        @foreach($meta['weekly'] as $d)
                            <tr class="{{ $d['wajib'] ? 'is-wajib' : '' }}">
                                <th scope="row">{{ $d['hari'] }}</th>
                                <td>@if($d['wajib'])<span class="sp-mono">{{ $d['jam_masuk'] }} – {{ $d['jam_pulang'] }}</span>@else<span class="sp-muted">Tidak wajib</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(! $meta['sesi_aktif'])
                    <p class="sp-note">Sesi {{ $meta['shift'] }} sedang dinonaktifkan admin, sehingga tidak ada hari wajib.</p>
                @endif
                @if($meta['pending_weekly'] && $meta['upcoming_weekly'])
                    <div class="sp-upcoming">
                        <h3>Mulai {{ $meta['effective_from_label'] }}</h3>
                        <ul>
                            @foreach(collect($meta['upcoming_weekly'])->where('wajib', true) as $d)
                                <li><span>{{ $d['hari'] }}</span><span class="sp-mono">{{ $d['jam_masuk'] }} – {{ $d['jam_pulang'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="sp-note">Tap datang awal, terlambat, maupun pulang lebih awal tetap dicatat mesin. Penilaian ketepatan waktu dihitung server dari jadwal yang berlaku pada tanggal tersebut.</p>
            </section>

            <section class="sp-card" aria-labelledby="spCalFullTitle">
                <header class="sp-card__head">
                    <div>
                        <p class="sp-card__kicker">AGENDA &amp; LIBUR</p>
                        <h2 id="spCalFullTitle" class="sp-card__title">Kalender Akademik</h2>
                        <p class="sp-card__sub">Pilih tanggal untuk melihat jadwal efektif dan catatan kehadiran</p>
                    </div>
                </header>
                @include('student.partials.calendar', ['calendar' => $calendar, 'scheduleMeta' => $meta, 'variant' => 'full'])
            </section>
        </div>
    </div>
@endsection
