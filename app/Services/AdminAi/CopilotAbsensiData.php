<?php

namespace App\Services\AdminAi;

use App\Http\Controllers\AbsensiManagementController;
use App\Models\User;
use App\Support\PeriodicReportBuilder;
use App\Support\PermissionRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Data absensi/laporan untuk Copilot dari sumber yang sama dengan halaman SIMALAS.
 *
 * Setiap hasil dipisah:
 * - provider: hanya agregat (tanpa nama/NIM) — boleh dikirim ke model AI;
 * - blocks: tabel/statistik/tautan — hanya dikirim ke browser pengguna.
 */
final class CopilotAbsensiData
{
    public const MAX_TABLE_ROWS = 50;

    private const STATUS_LABELS = [
        'lengkap' => 'Lengkap',
        'tidak_lengkap' => 'Tidak lengkap',
        'masuk' => 'Hanya masuk',
        'alfa' => 'Alfa',
        'belum' => 'Belum tap',
        'tidak_wajib' => 'Tidak wajib',
        'libur' => 'Libur',
        'sakit' => 'Sakit',
        'izin' => 'Izin',
    ];

    private const PERIODE = ['minggu_ini', 'minggu_lalu', 'bulan_ini', 'bulan_lalu', 'tahun_bulan', 'custom'];

    /**
     * Ringkasan rekap hari ini (snapshot halaman Cek Absensi).
     *
     * @param  array<string, mixed>  $args
     * @return array{provider: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function ringkasanHariIni(array $args, User $user): array
    {
        $query = $this->rekapQuery($args);
        $snap = $this->rekapSnapshot($query + ['tab' => 'all'], $user);
        $summary = $snap['summary'];
        $text = $snap['summary_text'];
        $tanggal = now()->toDateString();
        $filterLabel = $this->filterLabel($query);

        $blocks = [[
            'type' => 'stats',
            'title' => 'Rekap absensi hari ini',
            'subtitle' => Carbon::parse($tanggal)->translatedFormat('l, d F Y').($filterLabel !== '' ? ' · '.$filterLabel : ''),
            'items' => [
                ['label' => 'Mahasiswa aktif', 'value' => number_format((int) $summary['total'])],
                ['label' => 'Hadir tepat waktu', 'value' => $text['hadir']],
                ['label' => 'Lengkap', 'value' => $text['lengkap'], 'meta' => $text['persen'].' · '.$text['persen-meta']],
                ['label' => 'Terlambat', 'value' => $text['terlambat']],
                ['label' => 'Hanya masuk', 'value' => $text['masuk']],
                ['label' => 'Tidak lengkap', 'value' => $text['tidak_lengkap']],
                ['label' => 'Alfa', 'value' => $text['alfa']],
                ['label' => 'Belum tap', 'value' => $text['belum']],
            ],
            'note' => 'Sumber: rekap '.self::rekapPage($user).' (sama dengan halaman SIMALAS). Satuan: orang.',
        ]];
        $links = $this->rekapLinks($query, $user, 'all');
        if ($links !== []) {
            $blocks[] = ['type' => 'links', 'items' => $links];
        }

        return [
            'provider' => [
                'sumber' => 'rekap_cek_absensi',
                'tanggal' => $tanggal,
                'filter' => $filterLabel !== '' ? $filterLabel : 'semua mahasiswa aktif',
                'ringkasan' => [
                    'mahasiswa_aktif' => (int) $summary['total'],
                    'hadir_tepat_waktu' => (int) $summary['hadir'],
                    'lengkap' => (int) $summary['lengkap'],
                    'persentase_lengkap' => (float) $summary['persentase_lengkap'],
                    'terlambat' => (int) $summary['terlambat'],
                    'hanya_masuk' => (int) $summary['masuk'],
                    'tidak_lengkap' => (int) $summary['tidak_lengkap'],
                    'alfa' => (int) $summary['alfa'],
                    'belum_tap' => (int) $summary['belum'],
                    'tidak_wajib' => (int) $summary['tidak_wajib'],
                ],
                'satuan' => 'orang',
                'catatan' => 'Kartu angka sudah terlihat pengguna.',
            ],
            'blocks' => $blocks,
        ];
    }

    /**
     * Daftar mahasiswa hari ini per status (alfa / tidak lengkap / hadir / semua).
     *
     * @param  array<string, mixed>  $args
     * @return array{provider: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function daftarHariIni(array $args, User $user): array
    {
        $status = (string) ($args['status'] ?? 'tidak_lengkap');
        $tab = match ($status) {
            'alfa' => 'alfa',
            'hadir' => 'hadir',
            'semua' => 'all',
            default => 'tidak_lengkap',
        };
        $judul = match ($tab) {
            'alfa' => 'Alfa hari ini',
            'hadir' => 'Sudah tap masuk hari ini',
            'all' => 'Rekap mahasiswa hari ini',
            default => 'Tidak lengkap hari ini',
        };

        $query = $this->rekapQuery($args);
        $rows = [];
        $total = 0;
        $page = 1;
        do {
            $snap = $this->rekapSnapshot($query + ['tab' => $tab, 'page' => $page], $user);
            $total = (int) $snap['pagination']['total'];
            foreach ($snap['rows'] as $row) {
                if (count($rows) >= self::MAX_TABLE_ROWS) {
                    break 2;
                }
                $rows[] = [
                    'nama' => (string) $row['nama'],
                    'nim' => (string) $row['nim'],
                    'kelas' => (string) $row['kelas'],
                    'sesi' => ucfirst((string) $row['sesi']),
                    'status' => self::STATUS_LABELS[$row['status']] ?? (string) $row['status'],
                    'masuk' => (string) $row['jam_masuk'],
                    'pulang' => (string) $row['jam_pulang'],
                ];
            }
            $page++;
        } while ($page <= (int) $snap['pagination']['last_page']);

        $filterLabel = $this->filterLabel($query);
        $blocks = [[
            'type' => 'table',
            'title' => $judul,
            'subtitle' => now()->translatedFormat('d F Y').($filterLabel !== '' ? ' · '.$filterLabel : ''),
            'columns' => [
                ['key' => 'nama', 'label' => 'Nama'],
                ['key' => 'nim', 'label' => 'NIM'],
                ['key' => 'kelas', 'label' => 'Kelas'],
                ['key' => 'sesi', 'label' => 'Sesi'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'masuk', 'label' => 'Masuk', 'align' => 'end'],
                ['key' => 'pulang', 'label' => 'Pulang', 'align' => 'end'],
            ],
            'rows' => $rows,
            'total' => $total,
            'empty' => 'Tidak ada data untuk filter ini.',
            'note' => $total > count($rows)
                ? 'Menampilkan '.count($rows).' dari '.$total.' baris. Buka halaman '.self::rekapPage($user).' untuk daftar lengkap.'
                : null,
        ]];
        $links = $this->rekapLinks($query, $user, $tab);
        if ($links !== []) {
            $blocks[] = ['type' => 'links', 'items' => $links];
        }

        return [
            'provider' => [
                'sumber' => 'rekap_cek_absensi',
                'tanggal' => now()->toDateString(),
                'status' => $tab,
                'filter' => $filterLabel !== '' ? $filterLabel : 'semua mahasiswa aktif',
                'jumlah' => $total,
                'ditampilkan_di_tabel' => count($rows),
                'catatan' => 'Nama/NIM hanya tampil di tabel UI, tidak dikirim ke model.',
            ],
            'blocks' => $blocks,
        ];
    }

    /**
     * Laporan periodik — PeriodicReportBuilder, sama dengan menu Laporan.
     *
     * @param  array<string, mixed>  $args
     * @return array{provider: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function laporanPeriode(array $args, User $user): array
    {
        $periode = (string) ($args['periode'] ?? '');
        $bulan = trim((string) ($args['bulan'] ?? ''));
        $mulai = trim((string) ($args['tanggal_mulai'] ?? ''));
        $akhir = trim((string) ($args['tanggal_akhir'] ?? ''));

        if ($periode === '' && $mulai !== '' && $akhir !== '') {
            $periode = 'custom';
        }
        if ($periode === '' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $periode = 'tahun_bulan';
        }
        $invalid = ! in_array($periode, self::PERIODE, true)
            || ($periode === 'tahun_bulan' && ! preg_match('/^\d{4}-\d{2}$/', $bulan))
            || ($periode === 'custom' && (! $this->isDate($mulai) || ! $this->isDate($akhir)));
        if ($invalid) {
            return $this->klarifikasiPeriode($args);
        }

        $query = array_filter([
            'periode' => $periode,
            'bulan' => $periode === 'tahun_bulan' ? $bulan : null,
            'tanggal_mulai' => $periode === 'custom' ? $mulai : null,
            'tanggal_akhir' => $periode === 'custom' ? $akhir : null,
            'kelas' => trim((string) ($args['kelas'] ?? '')),
            'tim' => trim((string) ($args['tim'] ?? '')),
            'rekor' => in_array($args['rekor'] ?? '', ['terajin', 'alfa', 'jarang', 'tidak_lengkap'], true) ? $args['rekor'] : 'terajin',
        ], fn ($v) => $v !== null && $v !== '');

        $report = PeriodicReportBuilder::build(Request::create('/reports', 'GET', $query), $user->isDosen() ? $user : null);
        $filter = $report['filter'];
        $summary = $report['summary'];
        $rankings = $report['rankings'];
        $rentang = Carbon::parse($filter['tanggal_mulai'])->translatedFormat('d M Y').' – '.Carbon::parse($filter['tanggal_akhir'])->translatedFormat('d M Y');
        $filterLabel = $this->filterLabel($filter);
        $dalamCakupan = (int) $summary['mahasiswa'];
        $dalamLaporan = count($report['studentRecapsActive']);
        $selisih = $this->selisihCakupan($report, $filter['tanggal_akhir']);

        $note = 'Sumber: menu '.self::laporanPage($user).'. 1 sesi-mahasiswa = 1 mahasiswa pada 1 sesi di 1 tanggal; dua sesi di tanggal yang sama dihitung dua.';
        if ($selisih['jumlah'] > 0) {
            $note .= ' '.$selisih['jumlah'].' orang dalam cakupan tidak masuk laporan karena tidak punya catatan hadir/tidak lengkap/alfa di periode ini: '
                .$this->selisihLabel($selisih).'.';
        }

        $blocks = [[
            'type' => 'stats',
            'title' => 'Laporan absensi · '.$report['periodeLabel'],
            'subtitle' => $rentang.($filterLabel !== '' ? ' · '.$filterLabel : ''),
            'items' => [
                ['label' => 'Mahasiswa dalam cakupan', 'value' => number_format($dalamCakupan), 'meta' => 'orang · semua akun sesuai filter'],
                ['label' => 'Mahasiswa dalam laporan', 'value' => number_format($dalamLaporan), 'meta' => 'orang · baris Excel/Word/cetak'],
                ['label' => 'Hadir', 'value' => number_format((int) $summary['hadir']), 'meta' => 'sesi-mahasiswa · lengkap'],
                ['label' => 'Tidak lengkap', 'value' => number_format((int) $summary['tidak_lengkap']), 'meta' => 'sesi-mahasiswa · masuk tanpa pulang'],
                ['label' => 'Alfa', 'value' => number_format((int) $summary['alfa']), 'meta' => 'sesi-mahasiswa · tanpa tap wajib'],
            ],
            'note' => $note,
        ]];

        $rankRows = collect($rankings['items'] ?? [])->map(fn (array $item) => [
            'rank' => (string) ($item['rank'] ?? ''),
            'nama' => (string) ($item['label'] ?? '-'),
            'meta' => (string) ($item['meta'] ?? ''),
            'nilai' => (string) ($item['value_label'] ?? ($item['value'] ?? '')),
        ])->all();
        $blocks[] = [
            'type' => 'table',
            'title' => (string) ($rankings['title'] ?? 'Peringkat'),
            'subtitle' => (string) ($rankings['subtitle'] ?? ''),
            'columns' => [
                ['key' => 'rank', 'label' => '#', 'align' => 'end'],
                ['key' => 'nama', 'label' => 'Nama'],
                ['key' => 'meta', 'label' => 'NIM · Kelas / Tim'],
                ['key' => 'nilai', 'label' => (string) ($rankings['metric'] ?? 'Nilai'), 'align' => 'end'],
            ],
            'rows' => $rankRows,
            'total' => count($rankRows),
            'empty' => 'Belum ada mahasiswa dengan data pada periode ini.',
        ];

        $links = $this->laporanLinks($query, $user);
        if ($links !== []) {
            $blocks[] = ['type' => 'links', 'items' => $links];
        }

        return [
            'provider' => [
                'sumber' => 'menu_laporan',
                'periode' => $report['periodeLabel'],
                'tanggal_mulai' => $filter['tanggal_mulai'],
                'tanggal_akhir' => $filter['tanggal_akhir'],
                'filter' => $filterLabel !== '' ? $filterLabel : 'semua mahasiswa',
                'ringkasan' => [
                    'mahasiswa_dalam_cakupan' => $dalamCakupan,
                    'mahasiswa_dalam_laporan' => $dalamLaporan,
                    'hadir_lengkap' => (int) $summary['hadir'],
                    'tidak_lengkap' => (int) $summary['tidak_lengkap'],
                    'alfa' => (int) $summary['alfa'],
                ],
                'satuan' => [
                    'mahasiswa_dalam_cakupan' => 'orang',
                    'mahasiswa_dalam_laporan' => 'orang',
                    'hadir_lengkap' => 'sesi-mahasiswa',
                    'tidak_lengkap' => 'sesi-mahasiswa',
                    'alfa' => 'sesi-mahasiswa',
                ],
                'selisih_cakupan' => $selisih,
                'selisih_keterangan' => $selisih['jumlah'] > 0 ? $this->selisihLabel($selisih) : null,
                'peringkat' => [
                    'judul' => (string) ($rankings['title'] ?? ''),
                    'jumlah_baris' => count($rankRows),
                ],
                'unduhan_tersedia' => $links !== [],
                'catatan' => 'Kartu angka dan tabel sudah terlihat pengguna; nama hanya ada di tabel UI.',
            ],
            'blocks' => $blocks,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{provider: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function cariMahasiswa(array $args, User $user): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            return [
                'provider' => ['perlu_klarifikasi' => 'kata_kunci', 'pertanyaan' => 'Nama atau NIM mahasiswa yang dicari?'],
                'blocks' => [],
            ];
        }

        $base = User::query()
            ->where('role', 'mahasiswa')
            ->where(fn ($w) => $w->where('nama_lengkap', 'like', '%'.$q.'%')->orWhere('nitk_nidn', 'like', '%'.$q.'%'));
        $total = (clone $base)->count();
        $found = (clone $base)->orderBy('nama_lengkap')->limit(20)->get();

        $withFinger = $user->isLaboran();
        $rows = $found->map(fn (User $u) => array_filter([
            'nama' => (string) $u->nama_lengkap,
            'nim' => (string) $u->nitk_nidn,
            'kelas' => (string) ($u->kelas ?: '—'),
            'tim' => (string) ($u->tim ?: '—'),
            'akun' => $u->is_active ? 'Aktif' : ucfirst((string) ($u->approval_status ?: 'nonaktif')),
            'sidik' => $withFinger ? ($u->hasFingerprintEnrolled() ? 'Terdaftar' : 'Belum') : null,
        ], fn ($v) => $v !== null))->all();
        $columns = array_values(array_filter([
            ['key' => 'nama', 'label' => 'Nama'],
            ['key' => 'nim', 'label' => 'NIM'],
            ['key' => 'kelas', 'label' => 'Kelas'],
            ['key' => 'tim', 'label' => 'Tim'],
            ['key' => 'akun', 'label' => 'Akun'],
            $withFinger ? ['key' => 'sidik', 'label' => 'Sidik jari'] : null,
        ]));

        return [
            'provider' => [
                'sumber' => 'data_mahasiswa',
                'jumlah' => $total,
                'ditampilkan_di_tabel' => count($rows),
                'aktif' => $found->where('is_active', true)->count(),
                'catatan' => 'Identitas hanya tampil di tabel UI, tidak dikirim ke model.',
            ],
            'blocks' => [[
                'type' => 'table',
                'title' => 'Hasil pencarian mahasiswa',
                'subtitle' => $total.' cocok',
                'columns' => $columns,
                'rows' => $rows,
                'total' => $total,
                'empty' => 'Tidak ada mahasiswa yang cocok.',
                'note' => $total > count($rows) ? 'Menampilkan '.count($rows).' dari '.$total.'. Perjelas nama/NIM.' : null,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{provider: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function klarifikasiPeriode(array $args = []): array
    {
        $extra = array_filter([
            'kelas' => trim((string) ($args['kelas'] ?? '')),
            'tim' => trim((string) ($args['tim'] ?? '')),
        ]);
        $suffix = $extra !== [] ? ' ('.implode(', ', array_map(fn ($k, $v) => $k.' '.$v, array_keys($extra), $extra)).')' : '';

        return [
            'provider' => [
                'perlu_klarifikasi' => 'periode',
                'pertanyaan' => 'Laporan untuk periode apa?',
                'pilihan' => ['minggu ini', 'bulan ini', 'bulan lalu', 'rentang tanggal tertentu'],
            ],
            'blocks' => [[
                'type' => 'choices',
                'options' => [
                    ['label' => 'Minggu ini', 'message' => 'Buat laporan absensi minggu ini'.$suffix],
                    ['label' => 'Bulan ini', 'message' => 'Buat laporan absensi bulan ini'.$suffix],
                    ['label' => 'Bulan lalu', 'message' => 'Buat laporan absensi bulan lalu'.$suffix],
                ],
            ]],
        ];
    }

    /**
     * Mahasiswa dalam cakupan yang tidak punya baris di laporan, dikelompokkan per alasan
     * (tiap orang hanya masuk satu kelompok, urut sesuai prioritas di bawah).
     *
     * @param  array<string, mixed>  $report
     * @return array{jumlah: int, terdaftar_setelah_periode: int, nonaktif: int, tanpa_catatan_terhitung: int}
     */
    private function selisihCakupan(array $report, string $tanggalAkhir): array
    {
        $aktifIds = collect($report['studentRecapsActive'])->pluck('user_id')->all();
        $ids = collect($report['studentRecaps'])->pluck('user_id')->diff($aktifIds)->values();
        $out = ['jumlah' => $ids->count(), 'terdaftar_setelah_periode' => 0, 'nonaktif' => 0, 'tanpa_catatan_terhitung' => 0];
        if ($ids->isEmpty()) {
            return $out;
        }

        $batas = Carbon::parse($tanggalAkhir)->endOfDay();
        foreach (User::query()->whereIn('id', $ids)->get(['id', 'is_active', 'created_at']) as $u) {
            $key = $u->created_at && $u->created_at->gt($batas) ? 'terdaftar_setelah_periode'
                : (! $u->is_active ? 'nonaktif' : 'tanpa_catatan_terhitung');
            $out[$key]++;
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $selisih
     */
    private function selisihLabel(array $selisih): string
    {
        return collect([
            'terdaftar_setelah_periode' => 'terdaftar setelah periode berakhir',
            'nonaktif' => 'akun nonaktif',
            'tanpa_catatan_terhitung' => 'aktif tetapi hanya berstatus belum/libur/sakit/izin atau tanpa catatan',
        ])->filter(fn ($label, $key) => ($selisih[$key] ?? 0) > 0)
            ->map(fn ($label, $key) => $selisih[$key].' '.$label)
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function rekapSnapshot(array $query, User $user): array
    {
        $request = Request::create('/admin/attendance/snapshot', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return app(AbsensiManagementController::class)->snapshot($request)->getData(true);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    private function rekapQuery(array $args): array
    {
        return array_filter([
            'kelas' => trim((string) ($args['kelas'] ?? '')),
            'tim' => trim((string) ($args['tim'] ?? '')),
            'sesi' => in_array($args['sesi'] ?? '', ['pagi', 'malam'], true) ? (string) $args['sesi'] : '',
        ], fn ($v) => $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function filterLabel(array $filter): string
    {
        return collect([
            ($filter['kelas'] ?? '') !== '' ? 'kelas '.$filter['kelas'] : null,
            ($filter['tim'] ?? '') !== '' ? 'tim '.$filter['tim'] : null,
            ($filter['sesi'] ?? '') !== '' ? 'sesi '.$filter['sesi'] : null,
        ])->filter()->implode(', ');
    }

    /**
     * @param  array<string, string>  $query
     * @return list<array{label: string, href: string, kind: string}>
     */
    private function rekapLinks(array $query, User $user, string $tab): array
    {
        $panel = $user->isLaboran()
            ? ['menu' => 'attendance', 'rekap' => 'admin.attendance', 'export' => 'admin.attendance.export']
            : ['menu' => 'dosen_absensi', 'rekap' => 'dosen.absensi', 'export' => 'dosen.absensi.export'];
        if (! PermissionRegistry::can($user->role, $panel['menu'], 'view')) {
            return [];
        }

        return [
            ['label' => 'Buka di '.self::rekapPage($user), 'href' => route($panel['rekap'], $query + ($tab !== 'all' ? ['tab' => $tab] : [])), 'kind' => 'open'],
            ['label' => 'Unduh Excel rekap hari ini', 'href' => route($panel['export'], $query), 'kind' => 'download'],
        ];
    }

    /**
     * @param  array<string, string>  $query
     * @return list<array{label: string, href: string, kind: string}>
     */
    private function laporanLinks(array $query, User $user): array
    {
        if ($user->isLaboran() && PermissionRegistry::can($user->role, 'reports', 'view')) {
            $routes = ['admin.reports', 'admin.reports.export.excel', 'admin.reports.export.word', 'admin.reports.print'];
        } elseif ($user->isDosen() && PermissionRegistry::can($user->role, 'dosen_absensi', 'view')) {
            $routes = ['dosen.reports', 'dosen.reports.export.excel', 'dosen.reports.export.word', 'dosen.reports.print'];
        } else {
            return [];
        }

        return [
            ['label' => 'Buka di menu '.self::laporanPage($user), 'href' => route($routes[0], $query), 'kind' => 'open'],
            ['label' => 'Unduh Excel', 'href' => route($routes[1], $query), 'kind' => 'download'],
            ['label' => 'Unduh Word', 'href' => route($routes[2], $query), 'kind' => 'download'],
            ['label' => 'Cetak', 'href' => route($routes[3], $query), 'kind' => 'open'],
        ];
    }

    public static function rekapPage(User $user): string
    {
        return $user->isLaboran() ? 'Cek Absensi' : 'Rekap Harian';
    }

    public static function laporanPage(User $user): string
    {
        return $user->isLaboran() ? 'Laporan' : 'Laporan Periodik';
    }

    private function isDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
