@php
    $pblMeta = $pblMeta ?? ['has_pbl' => true, 'tim_keys' => collect()];
    $lokerOnly = $lokerOnly ?? false;
@endphp
@if($lokerOnly)
    @if(!($pblMeta['has_pbl'] ?? true))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px 16px;border-color:rgba(234,179,8,0.35);background:rgba(234,179,8,0.1);">
            <strong style="color:#b45309;">Pantauan loker — tim PBL belum terhubung</strong>
            <p style="margin:8px 0 0;font-size:13px;color:var(--text-muted);">
                Modul <strong>loker</strong> hanya menampilkan mahasiswa tim PBL Anda (manpro/co-manpro).
                Minta admin memilih akun dosen di menu <strong>Proyek PBL</strong>.
                Untuk <strong>absensi</strong>, gunakan menu Rekap/Tabel Alfa — data seluruh mahasiswa dengan filter kelas/tim/semester.
            </p>
        </div>
    @else
        <div style="margin-bottom:14px;font-size:12px;color:var(--text-muted);">
            Loker · tim PBL Anda: <strong>{{ ($pblMeta['tim_keys'] ?? collect())->implode(', ') }}</strong>
        </div>
    @endif
@endif
