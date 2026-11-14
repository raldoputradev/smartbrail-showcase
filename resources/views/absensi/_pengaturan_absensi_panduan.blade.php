@php
    $pagi = $jadwalSesi['pagi'] ?? null;
    $malam = $jadwalSesi['malam'] ?? null;
    $fmt = fn ($t) => $t ? \Illuminate\Support\Str::of($t)->substr(0, 5) : '—';
    $tutupPagi = $pagi
        ? $fmt($pagi->jam_pulang) . ' + ' . (int) $pagi->grace_alfa_menit . ' menit'
        : '17:00 + 120 menit';
    $tutupMalam = $malam
        ? $fmt($malam->jam_pulang) . ' + ' . (int) $malam->grace_alfa_menit . ' menit'
        : '22:00 + 60 menit';
    $kebijakan = $kebijakanLabel ?? \App\Support\AbsensiKebijakan::labelMode();
@endphp

<div class="iot-modal-backdrop" id="absensiHelpModal" role="dialog" aria-modal="true" aria-labelledby="absensiHelpTitle">
    <div class="iot-modal abs-help-modal">
        <button type="button" class="iot-modal-close" onclick="closeAbsensiHelp()" aria-label="Tutup">×</button>
        <div class="abs-help-modal__badge">PANDUAN</div>
        <h3 id="absensiHelpTitle">Cara kerja Atur Absensi</h3>
        <p class="abs-help-modal__lead">
            Atur jam sesi, hari wajib, dan libur di sini. Tap tetap di mesin ESP32;
            hasilnya di <a href="{{ route('admin.attendance') }}">Rekap Harian</a>.
            Kebijakan aktif: <strong>{{ $kebijakan }}</strong>.
        </p>

        <div class="abs-help-steps">
            <div class="abs-help-step">
                <span class="abs-help-step__n">1</span>
                <div>
                    <strong>Tap di mesin</strong>
                    <small>Mahasiswa tap masuk &amp; pulang di sensor ESP32 — bukan lewat aplikasi.</small>
                </div>
            </div>
            <div class="abs-help-step">
                <span class="abs-help-step__n">2</span>
                <div>
                    <strong>Aturan waktu</strong>
                    <small>Jam standar, toleransi telat, dan tutup sesi diatur di form halaman ini.</small>
                </div>
            </div>
            <div class="abs-help-step">
                <span class="abs-help-step__n">3</span>
                <div>
                    <strong>Rekap &amp; koreksi</strong>
                    <small>Lengkap / tidak lengkap / alfa muncul otomatis. Koreksi hanya di Rekap Harian.</small>
                </div>
            </div>
        </div>

        <div class="abs-help-section">
            <h4>Yang diatur di halaman ini</h4>
            <ul class="abs-help-list">
                <li><strong>Kesiapan</strong> — status scheduler VPS &amp; aksi tutup sesi manual</li>
                <li><strong>Kebijakan</strong> — Kamis saja atau setiap hari</li>
                <li><strong>Jadwal sesi</strong> — jam tap pagi &amp; malam</li>
                <li><strong>Slot lab</strong> — jadwal otomatis 10 slot pagi</li>
            </ul>
            <p class="abs-help-note" style="margin-top:8px;">Kalender libur &amp; program studi ada di menu <strong>Pengaturan</strong> (sidebar / dropdown profil).</p>
        </div>

        <div class="abs-help-section" id="absensiHelpSlot">
            <h4>Slot lab pagi</h4>
            <p>Dihitung otomatis dari jam standar masuk. Tap sebelum jam standar masuk = slot 1. Setiap slot 55 menit (10 slot).</p>
        </div>

        <div class="abs-help-section">
            <h4>Contoh sesi pagi (nilai aktif)</h4>
            <table class="abs-help-table">
                <thead>
                    <tr><th>Field</th><th>Fungsi</th><th>Nilai</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mulai tap masuk</td>
                        <td>Tap masuk paling awal</td>
                        <td>{{ $fmt($pagi?->jam_mulai_tap_masuk ?? '06:30') }}</td>
                    </tr>
                    <tr>
                        <td>Jam standar masuk</td>
                        <td>Patokan terlambat</td>
                        <td>{{ $fmt($pagi?->jam_masuk ?? '07:50') }}</td>
                    </tr>
                    <tr>
                        <td>Jam pulang</td>
                        <td>Tap pulang mulai jam ini</td>
                        <td>{{ $fmt($pagi?->jam_pulang ?? '17:00') }}</td>
                    </tr>
                    <tr>
                        <td>Tutup sesi</td>
                        <td>Hitung alfa / tidak lengkap</td>
                        <td>{{ $tutupPagi }}</td>
                    </tr>
                    <tr>
                        <td>Tutup malam</td>
                        <td>Sesi malam</td>
                        <td>{{ $tutupMalam }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="abs-help-section">
            <h4>Status akhir (hari wajib)</h4>
            <ul class="abs-help-list">
                <li><strong>Masuk + pulang</strong> sebelum tutup → <span class="abs-help-ok">lengkap</span></li>
                <li><strong>Hanya masuk</strong>, tidak pulang → <span class="abs-help-warn">tidak lengkap</span></li>
                <li><strong>Tanpa tap</strong> setelah tutup → <span class="abs-help-bad">alfa</span> (butuh scheduler aktif)</li>
            </ul>
        </div>

        <div class="abs-help-section abs-help-section--vps" id="absensiHelpVps">
            <h4>Scheduler VPS (produksi)</h4>
            <p>Otomatisasi tutup sesi &amp; alfa memakai Laravel Scheduler di server — bukan XAMPP lokal.</p>
            <div class="abs-help-code">
                <div><span>Cron tiap menit</span><code>* * * * * cd /var/www/simalas &amp;&amp; php8.3 artisan schedule:run</code></div>
                <div><span>Diagnosa SSH</span><code>php8.3 artisan simalas:absensi-readiness</code></div>
            </div>
            <p class="abs-help-note">Kotak <strong>Scheduler VPS</strong> di halaman harus <strong class="abs-help-ok">Aktif</strong> dan ter-update tiap menit.</p>
        </div>

        <div class="mesin-modal-actions abs-help-actions">
            <button type="button" class="abs-btn abs-btn--primary" onclick="closeAbsensiHelp()">Mengerti</button>
        </div>
    </div>
</div>
