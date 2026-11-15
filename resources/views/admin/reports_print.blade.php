<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan SmartBrail</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 0;
            background: #f1f5f9;
        }
        .page {
            width: min(1120px, calc(100% - 36px));
            margin: 18px auto 36px;
            background: #fff;
            border: 1px solid #dbe3ef;
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.10);
        }
        .toolbar {
            width: min(1120px, calc(100% - 36px));
            margin: 18px auto 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar-label { color: #64748b; font-size: 12px; font-weight: 700; margin-right: 2px; }
        .tb-btn {
            display: inline-flex; align-items: center; gap: 7px;
            border: 0; border-radius: 10px; padding: 10px 16px;
            color: #fff; font-weight: 800; font-size: 12.5px; cursor: pointer;
            text-decoration: none; font-family: inherit;
            transition: filter .15s, transform .15s;
        }
        .tb-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
        .tb-btn--excel { background: linear-gradient(135deg, #15803d, #22c55e); }
        .tb-btn--word { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
        .tb-btn--pdf { background: linear-gradient(135deg, #1e3a5f, #4a90d9); }
        .report-hero {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 28px 32px;
            color: #fff;
            background: radial-gradient(circle at top left, rgba(45, 212, 191, 0.34), transparent 35%),
                linear-gradient(135deg, #0f172a, #1e3a5f 52%, #4a90d9);
        }
        .brand {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 16px;
        }
        .brand-mark {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.24);
            overflow: hidden;
            flex-shrink: 0;
        }
        .brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        h1 { margin: 0; font-size: 26px; letter-spacing: 0.02em; }
        .subtitle { margin-top: 4px; color: rgba(255, 255, 255, 0.78); font-size: 12px; }
        .hero-meta {
            min-width: 260px;
            border-radius: 16px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .hero-meta div {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 6px 0;
            font-size: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        }
        .hero-meta div:last-child { border-bottom: 0; }
        .content { padding: 24px 32px 32px; }
        h2 {
            margin: 26px 0 10px;
            font-size: 15px;
            color: #1e3a5f;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin: 0 0 8px;
        }
        .summary-note { margin: 0 0 20px; color: #64748b; font-size: 10.5px; }
        .box {
            border: 1px solid #dbe3ef;
            border-radius: 14px;
            padding: 14px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .box span { display: block; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
        .box strong { display: block; margin-top: 6px; font-size: 24px; color: #0f172a; }
        .box small { display: block; margin-top: 2px; color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10.5px; }
        th, td { border: 1px solid #dbe3ef; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #1e3a5f; color: #fff; font-weight: 800; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .empty { color: #64748b; text-align: center; padding: 16px; }
        .nw { white-space: nowrap; }
        .muted { color: #64748b; font-size: 9.5px; }
        .chip-st {
            display: inline-block; padding: 3px 9px; border-radius: 999px;
            font-size: 9.5px; font-weight: 800; text-transform: capitalize;
            background: #e2e8f0; color: #475569;
        }
        .chip-st--hadir, .chip-st--masuk, .chip-st--pulang, .chip-st--lengkap { background: #dcfce7; color: #15803d; }
        .chip-st--tidak_lengkap, .chip-st--terlambat { background: #fef3c7; color: #b45309; }
        .chip-st--alfa, .chip-st--ditolak { background: #fee2e2; color: #b91c1c; }
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #dbe3ef;
            color: #64748b;
            font-size: 10px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none; }
            .page { width: 100%; margin: 0; border: 0; box-shadow: none; }
            .report-hero { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            th { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    @php
        $exportRoute = $reportPanel['routes']['export_excel'] ?? 'admin.reports.export.excel';
        $wordRoute = $reportPanel['routes']['export_word'] ?? 'admin.reports.export.word';
    @endphp
    <div class="toolbar no-print">
        <span class="toolbar-label">Simpan laporan:</span>
        <a href="{{ route($exportRoute, request()->query()) }}" class="tb-btn tb-btn--excel">⬇ Excel (.xls)</a>
        <a href="{{ route($wordRoute, request()->query()) }}" class="tb-btn tb-btn--word">⬇ Word (.doc)</a>
        <button class="tb-btn tb-btn--pdf" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
    </div>

    <main class="page">
        <section class="report-hero">
            <div>
                <div class="brand">
                    <div class="brand-mark" style="background:#fff;">
                        <img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/smartbrail-logo.png')) }}" alt="Logo SmartBrail" onerror="this.style.display='none';this.parentElement.textContent='SB';">
                    </div>
                    <div>
                        <h1>Laporan SmartBrail</h1>
                        <div class="subtitle">SmartBrail · Sistem Manajemen Laboratorium Pintar (BRAIL)</div>
                    </div>
                </div>
                <div class="subtitle">Rekap sesuai filter periode · kelas · tim PBL (bukan dump semua).</div>
            </div>
            <div class="hero-meta">
                <div><span>Mode</span><strong>{{ $activityLabel }}</strong></div>
                <div><span>Label</span><strong>{{ $periodeLabel ?? 'Custom' }}</strong></div>
                <div><span>Periode</span><strong>{{ \Carbon\Carbon::parse($filter['tanggal_mulai'])->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($filter['tanggal_akhir'])->translatedFormat('d M Y') }}</strong></div>
                <div><span>Kelas</span><strong>{{ $filter['kelas'] ?: 'Semua' }}</strong></div>
                <div><span>Tim</span><strong>{{ $filter['tim'] ?: 'Semua' }}</strong></div>
            </div>
        </section>

        @php
            $printRows = $studentRecapsActive ?? $studentRecaps;
        @endphp

        <section class="content">
            <div class="summary">
                <div class="box"><span>Mahasiswa dalam cakupan</span><strong>{{ number_format($summary['mahasiswa'] ?? 0) }}</strong><small>orang</small></div>
                <div class="box"><span>Mahasiswa dalam laporan</span><strong>{{ number_format($printRows->count()) }}</strong><small>orang</small></div>
                
                    <div class="box"><span>Hadir</span><strong>{{ number_format($summary['hadir'] ?? 0) }}</strong><small>sesi-mahasiswa</small></div>
                    <div class="box"><span>Tidak Lengkap</span><strong>{{ number_format($summary['tidak_lengkap'] ?? 0) }}</strong><small>sesi-mahasiswa</small></div>
                    <div class="box"><span>Alfa</span><strong>{{ number_format($summary['alfa'] ?? 0) }}</strong><small>sesi-mahasiswa</small></div>
                
            </div>
            <p class="summary-note">Dalam laporan = mahasiswa dalam cakupan yang punya catatan hadir/tidak lengkap/alfa di periode. Hadir, tidak lengkap, dan alfa dihitung per sesi-mahasiswa (1 mahasiswa pada 1 sesi di 1 tanggal; dua sesi di tanggal yang sama dihitung dua).</p>

            <h2>Rekap Per Mahasiswa (dalam laporan)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Kelas</th>
                        <th>Tim</th>
                        
                            <th class="right">Hadir (sesi)</th>
                            <th class="right">Tidak Lengkap (sesi)</th>
                            <th class="right">Alfa (sesi)</th>
                        
                        <th>Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($printRows as $row)
                        <tr>
                            <td>{{ $row['nama'] }}</td>
                            <td>{{ $row['nim'] }}</td>
                            <td>{{ $row['kelas'] }}</td>
                            <td>{{ $row['tim'] }}</td>
                            
                                <td class="right">{{ $row['hadir'] ?? 0 }}</td>
                                <td class="right">{{ $row['tidak_lengkap'] ?? 0 }}</td>
                                <td class="right">{{ $row['alfa'] ?? 0 }}</td>
                            
                            <td>{{ $row['terakhir'] ? $row['terakhir']->format('Y-m-d H:i:s') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">Tidak ada data sesuai filter periode/kelas/tim.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2>Detail Aktivitas</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:120px;">Waktu</th>
                        <th>Aktivitas</th>
                        <th>Mahasiswa</th>
                        <th>Kelas · Tim</th>
                        <th>Mesin</th>
                        <th style="width:150px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detailRows as $row)
                        <tr>
                            <td class="nw">{{ optional($row['waktu'])->format('d M Y') }}<br><small class="muted">{{ optional($row['waktu'])->format('H:i:s') }}</small></td>
                            <td>{{ $row['aktivitas'] }}</td>
                            <td><strong>{{ $row['nama'] }}</strong><br><small class="muted">{{ $row['nim'] }}</small></td>
                            <td>{{ $row['kelas'] }}@if(!empty($row['tim']) && $row['tim'] !== '-')<br><small class="muted">{{ $row['tim'] }}</small>@endif</td>
                            <td>{{ $row['id_mesin'] }}</td>
                            <td><span class="chip-st chip-st--{{ $row['status'] ?? 'lain' }}">{{ $row['status'] }}</span>@if(!empty($row['status_meta']))<br><small class="muted">{{ $row['status_meta'] }}</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Belum ada detail aktivitas pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="footer">
                <span>Dicetak otomatis dari SmartBrail pada {{ now()->translatedFormat('d M Y H:i') }}</span>
                <span>Politeknik Negeri Batam</span>
            </div>
        </section>
    </main>
</body>
</html>
