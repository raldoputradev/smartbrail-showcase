@extends('layouts.dashboard')

@section('title', 'Pengaturan Absensi')
@section('page-title', 'Pengaturan Absensi')
@section('page-subtitle', 'Kebijakan wajib, jadwal sesi, dan kesiapan operasional')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @php $panel = ['is_laboran' => true, 'route_rekap' => 'admin.attendance', 'route_alfa' => 'admin.absensi.alfa', 'route_tidak_lengkap' => 'admin.absensi.tidak-lengkap']; @endphp
    @include('absensi._styles')
    @include('absensi._nav', ['activeNav' => 'settings'])

    @include('absensi._pengaturan_absensi_panduan', [
        'jadwalSesi' => $jadwalSesi ?? [],
        'kebijakanLabel' => $kebijakanLabel ?? null,
    ])

    <div class="abs-set-toolbar animate-in">
        <div class="abs-set-toolbar__left">
            <span class="abs-set-live"><span class="abs-set-live__dot"></span>PENGATURAN</span>
            <div>
                <strong>Atur Absensi</strong>
                <small>Jam sesi · hari wajib · scheduler VPS</small>
            </div>
        </div>
        <div class="abs-set-toolbar__right">
            <button type="button" class="abs-btn abs-btn--ghost" onclick="openAbsensiHelp()">
                Panduan <span class="abs-help-q">?</span>
            </button>
            <a href="{{ route('admin.settings') }}" class="abs-btn abs-btn--ghost">Pengaturan</a>
            <a href="{{ route('admin.attendance') }}" class="abs-btn abs-btn--ghost">Rekap Harian</a>
        </div>
    </div>

    @if(session('success'))
        <div class="abs-flash abs-flash--ok animate-in">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="abs-flash abs-flash--info animate-in">{{ session('info') }}</div>
    @endif

    @include('admin._absensi_ops_panel', ['ops' => $ops ?? []])

    <section class="abs-card animate-in">
        <div class="abs-card__head">
            <div>
                <h3>Kebijakan absensi wajib</h3>
                <p>Aktif sekarang: <strong>{{ $kebijakanLabel }}</strong></p>
            </div>
            <button type="button" class="abs-help-q" onclick="openAbsensiHelp()" title="Panduan" aria-label="Panduan">?</button>
        </div>
        <form method="POST" action="{{ route('admin.absensi.kebijakan') }}" class="abs-policy">
            @csrf
            <label class="abs-policy__opt {{ ($kebijakan['mode'] ?? 'kamis') === 'kamis' ? 'is-active' : '' }}">
                <input type="radio" name="mode" value="kamis" @checked(($kebijakan['mode'] ?? 'kamis') === 'kamis')>
                <span class="abs-policy__title">Hanya Kamis</span>
                <span class="abs-policy__desc">Default lab — alfa hanya dihitung hari Kamis</span>
            </label>
            <label class="abs-policy__opt {{ ($kebijakan['mode'] ?? '') === 'harian' ? 'is-active' : '' }}">
                <input type="radio" name="mode" value="harian" @checked(($kebijakan['mode'] ?? '') === 'harian')>
                <span class="abs-policy__title">Setiap hari</span>
                <span class="abs-policy__desc">Wajib absen harian — alfa dihitung tiap hari kerja</span>
            </label>
            <div class="abs-policy__actions">
                <button type="submit" class="abs-btn abs-btn--primary">Simpan kebijakan</button>
            </div>
        </form>
    </section>

    <section class="abs-card animate-in">
        <div class="abs-card__head">
            <div>
                <h3>Jadwal sesi pagi &amp; malam</h3>
                <p>Jam standar, toleransi telat, dan tutup sesi otomatis</p>
            </div>
        </div>
        <div class="abs-sesi-grid">
            @foreach($sesi as $s)
                <form method="POST" action="{{ route('admin.absensi.sesi') }}" class="abs-sesi">
                    @csrf
                    <input type="hidden" name="shift" value="{{ $s->shift }}">
                    <input type="hidden" name="timezone" value="{{ $s->timezone }}">
                    <div class="abs-sesi__head">
                        <span class="abs-sesi__badge">Sesi {{ $s->shift }}</span>
                        <label class="abs-sesi__active">
                            <input type="checkbox" name="aktif" value="1" @checked($s->aktif)>
                            Aktif
                        </label>
                    </div>
                    <div class="abs-sesi__fields">
                        <label>
                            <span>Jam standar masuk</span>
                            <input type="time" name="jam_masuk" value="{{ \Illuminate\Support\Str::of($s->jam_masuk)->substr(0,5) }}">
                        </label>
                        @if($s->shift === 'pagi')
                        <label title="Tap masuk paling awal (sebelum jam standar)">
                            <span>Mulai tap masuk</span>
                            <input type="time" name="jam_mulai_tap_masuk" value="{{ \Illuminate\Support\Str::of($s->jam_mulai_tap_masuk ?? '06:30')->substr(0,5) }}">
                        </label>
                        @endif
                        <label>
                            <span>Jam pulang</span>
                            <input type="time" name="jam_pulang" value="{{ \Illuminate\Support\Str::of($s->jam_pulang)->substr(0,5) }}">
                        </label>
                        <label title="Tap masuk sampai jam standar + menit ini = aman">
                            <span>Toleransi telat masuk (menit)</span>
                            <input type="number" name="toleransi_masuk_menit" value="{{ $s->toleransi_masuk_menit }}">
                        </label>
                        <label title="Tap pulang sampai jam pulang + menit ini = aman">
                            <span>Toleransi telat pulang (menit)</span>
                            <input type="number" name="toleransi_pulang_menit" value="{{ $s->toleransi_pulang_menit ?? $s->toleransi_masuk_menit }}">
                        </label>
                        <label title="Setelah jam pulang + menit ini: hanya tap masuk → tidak lengkap">
                            <span>Toleransi tidak lengkap (menit)</span>
                            <input type="number" name="grace_alfa_menit" value="{{ $s->grace_alfa_menit }}">
                        </label>
                    </div>
                    <div class="abs-sesi__foot">
                        <button type="submit" class="abs-btn abs-btn--primary">Simpan sesi {{ $s->shift }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </section>

    @if(!empty($slotLabPagi))
    <section class="abs-card animate-in">
        <div class="abs-card__head">
            <div>
                <h3>Slot lab pagi (10 × 55 menit)</h3>
                <p>Otomatis dari jam standar masuk</p>
            </div>
            <button type="button" class="abs-help-q" onclick="openAbsensiHelp('slot')" title="Panduan slot" aria-label="Panduan slot">?</button>
        </div>
        <div class="abs-table-wrap">
            <table class="absensi-table abs-settings-table">
                <thead><tr><th>Slot</th><th>Mulai</th><th>Selesai</th></tr></thead>
                <tbody>
                    @foreach($slotLabPagi as $slot)
                        <tr>
                            <td><strong>{{ $slot['no'] }}</strong></td>
                            <td>{{ $slot['mulai'] }}</td>
                            <td>{{ $slot['selesai'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
@endsection

@section('scripts')
<script>
    function openAbsensiHelp(focus) {
        const modal = document.getElementById('absensiHelpModal');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        const map = { vps: 'absensiHelpVps', slot: 'absensiHelpSlot' };
        const id = map[focus];
        if (id) {
            const el = document.getElementById(id);
            if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        }
    }

    function closeAbsensiHelp() {
        const modal = document.getElementById('absensiHelpModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('absensiHelpModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeAbsensiHelp();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAbsensiHelp();
        });

        document.querySelectorAll('.abs-policy__opt input[type="radio"]').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.abs-policy__opt').forEach((opt) => {
                    opt.classList.toggle('is-active', opt.querySelector('input')?.checked);
                });
            });
        });
    });
</script>
@endsection
