@php
    $student = $student ?? [];
    $summary = $summary ?? [];
    $harianRows = $harianRows ?? collect();
    $taps = $taps ?? collect();
    $lokerRows = $lokerRows ?? collect();
    $periode = $periode ?? [];
    $aktivitas = $aktivitas ?? 'semua';
@endphp

<div class="periodic-student-detail">
    <div class="periodic-student-detail__head">
        <div>
            <h4 style="margin:0 0 4px;font-size:18px;">{{ $student['nama'] ?? '—' }}</h4>
            <p style="margin:0;font-size:12.5px;color:var(--text-muted);">
                NIM {{ $student['nim'] ?? '—' }}
                · {{ $student['kelas'] ?? '—' }} / {{ $student['tim'] ?? '—' }}
                · {{ $student['prodi'] ?? '—' }}
                · Semester {{ $student['semester'] ?? '—' }}
            </p>
            <p style="margin:8px 0 0;font-size:12px;color:var(--text-muted);">
                Periode {{ $periode['mulai_label'] ?? '—' }} – {{ $periode['akhir_label'] ?? '—' }}
            </p>
        </div>
        <div class="periodic-student-detail__stats">
            @if($aktivitas !== 'loker')
                <span class="absensi-badge lengkap">Hadir {{ $summary['hadir'] ?? 0 }}</span>
                <span class="absensi-badge tidak_lengkap">Tidak lengkap {{ $summary['tidak_lengkap'] ?? 0 }}</span>
                <span class="absensi-badge alfa">Alfa {{ $summary['alfa'] ?? 0 }}</span>
            @endif
        </div>
    </div>

    @if($aktivitas !== 'loker')
        <div class="periodic-student-detail__section">
            <h5>Rekap per hari</h5>
            <div style="overflow-x:auto;">
                <table class="data-table" style="min-width:860px;">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Sesi</th>
                            <th>Status</th>
                            <th>Jam masuk</th>
                            <th>Jam pulang</th>
                            <th>Telat masuk</th>
                            <th>Telat pulang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($harianRows as $row)
                            <tr>
                                <td>{{ $row['tanggal_label'] }}</td>
                                <td>{{ $row['sesi'] }}</td>
                                <td>
                                    <span class="absensi-badge {{ $row['kategori'] === 'hadir' ? 'lengkap' : $row['kategori'] }}">{{ $row['ringkasan'] }}</span>
                                    @if(!empty($row['pulang_lembur']))
                                        <span class="absensi-badge" style="margin-left:4px;font-size:10px;">lembur</span>
                                    @endif
                                </td>
                                <td style="font-family:monospace;font-size:12px;">{{ $row['jam_masuk'] }}</td>
                                <td style="font-family:monospace;font-size:12px;">{{ $row['jam_pulang'] }}</td>
                                <td style="font-size:12px;">
                                    @if($row['menit_terlambat_masuk'] > 0)
                                        <span style="color:#ea580c;font-weight:700;">+{{ $row['menit_terlambat_masuk'] }} m</span>
                                    @else
                                        <span style="color:var(--text-muted);">—</span>
                                    @endif
                                </td>
                                <td style="font-size:12px;">
                                    @if($row['menit_terlambat_pulang'] > 0)
                                        <span style="color:#ea580c;font-weight:700;">+{{ $row['menit_terlambat_pulang'] }} m</span>
                                    @else
                                        <span style="color:var(--text-muted);">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:20px;">Tidak ada rekap harian pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="periodic-student-detail__section">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                <h5 style="margin:0;">Log tap ({{ $summary['total_tap'] ?? 0 }})</h5>
                <div class="tap-jenis-legenda" style="margin:0;">
                    <span><span class="tap-jenis-badge masuk">Masuk</span> tap masuk</span>
                    <span><span class="tap-jenis-badge pulang">Pulang</span> tap pulang</span>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table" style="min-width:720px;">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis</th>
                            <th>Sesi</th>
                            <th>ID Mesin</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($taps as $tap)
                            <tr>
                                <td style="font-family:monospace;font-size:12px;">{{ $tap['waktu_label'] }}</td>
                                <td>
                                    @if($tap['jenis'] === 'masuk')
                                        <span class="tap-jenis-badge masuk">Masuk</span>
                                    @elseif($tap['jenis'] === 'pulang')
                                        <span class="tap-jenis-badge pulang">Pulang</span>
                                    @else
                                        <span>{{ $tap['jenis_label'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $tap['sesi'] }}</td>
                                <td style="font-family:monospace;">{{ $tap['id_mesin'] }}</td>
                                <td style="font-size:12px;color:var(--text-muted);">{{ $tap['terlambat_label'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">Belum ada tap pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
