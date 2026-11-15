<?php

namespace App\Http\Controllers;

use App\Models\MesinIot;
use App\Models\Ruangan;
use App\Models\RuanganAccessEvent;
use App\Models\RuanganPetugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Kelola ruangan (aturan booking), petugas non-portal, dan binding perangkat.
 * Binding perangkat ke ruangan butuh is_admin (kewenangan sistem), bukan laboran operasional.
 */
class AdminRuanganController extends Controller
{
    use Concerns\AdminShellProps;

    public function index(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25], true)) {
            $perPage = 10;
        }
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'per_page' => $perPage,
        ];

        $query = Ruangan::query()->orderBy('nama');
        if ($filters['q'] !== '') {
            $kw = $filters['q'];
            $query->where(fn ($qb) => $qb->where('nama', 'like', "%{$kw}%")
                ->orWhere('kode', 'like', "%{$kw}%")
                ->orWhere('lokasi', 'like', "%{$kw}%"));
        }
        if ($filters['status'] === 'aktif') {
            $query->where('aktif', true);
        } elseif ($filters['status'] === 'nonaktif') {
            $query->where('aktif', false);
        }

        $perangkatCount = MesinIot::query()->whereNotNull('ruangan_id')
            ->selectRaw('ruangan_id, COUNT(*) as total')->groupBy('ruangan_id')
            ->pluck('total', 'ruangan_id');
        $lastEvent = RuanganAccessEvent::whereIn('ruangan_id', Ruangan::pluck('id'))
            ->orderByDesc('terjadi_pada')->get()->groupBy('ruangan_id');
        $mesinTerikat = MesinIot::whereNotNull('ruangan_id')->pluck('id_mesin', 'ruangan_id');

        $paginator = $query->paginate($perPage)->withQueryString();
        $rows = collect($paginator->items())->map(function (Ruangan $r) use ($perangkatCount, $lastEvent) {
            $aturan = $r->aturan();
            $ev = $lastEvent->get($r->id)?->first();
            return [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'lokasi' => $r->lokasi,
                'kapasitas' => $r->kapasitas,
                'fasilitas' => $r->fasilitas ?? [],
                'foto_url' => $r->foto ? asset($r->foto) : null,
                'aktif' => (bool) $r->aktif,
                'aturan' => $aturan,
                'perangkat_total' => (int) ($perangkatCount[$r->id] ?? 0),
                'jadwal_label' => $this->jadwalLabel($aturan),
                'last_event' => $ev ? [
                    'jenis' => $ev->jenis,
                    'label' => $ev->terjadi_pada?->diffForHumans(),
                ] : null,
            ];
        })->values()->all();

        $all = Ruangan::query()->get(['id', 'aktif']);
        $stats = [
            'total' => $all->count(),
            'aktif' => $all->where('aktif', true)->count(),
            'nonaktif' => $all->where('aktif', false)->count(),
            'terikat' => $perangkatCount->keys()->count(),
        ];
        $stats['terikat_pct'] = $stats['total'] > 0 ? round($stats['terikat'] / $stats['total'] * 100) : 0;

        return \Inertia\Inertia::render('Ruangan', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'filters' => $filters,
            'stats' => $stats,
            'ruangan' => [
                'data' => $rows,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ],
            'mesin_list' => MesinIot::where('tipe', '!=', 'loker')->orderBy('id_mesin')
                ->get(['id', 'id_mesin', 'nama_mesin', 'tipe', 'is_active', 'ruangan_id'])
                ->map(fn ($m) => [
                    'id' => $m->id, 'id_mesin' => $m->id_mesin,
                    'nama' => $m->nama_mesin ?: $m->id_mesin,
                    'tipe' => (string) $m->tipe, 'is_active' => (bool) $m->is_active,
                    'ruangan_id' => $m->ruangan_id,
                ]),
            'ruangan_options' => Ruangan::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'kode'])
                ->map(fn ($r) => ['id' => $r->id, 'nama' => $r->nama, 'kode' => $r->kode]),
            'can' => [
                'create' => \App\Support\PermissionRegistry::canCurrent('ruangan', 'create'),
                'update' => \App\Support\PermissionRegistry::canCurrent('ruangan', 'update'),
            ],
            'is_admin' => (bool) $user->isAdmin(),
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'ruangan')
                    : route('admin.ruangan.index'),
                'store' => route('admin.ruangan.store'),
                'update_base' => route('admin.ruangan.index') . '/',
                'bind_base' => route('admin.mesin.index') . '/',
            ],
        ]);
    }

    /** Label jadwal operasional singkat dari aturan ruangan. */
    private function jadwalLabel(array $aturan): string
    {
        $hari = $aturan['hari'] ?? [];
        $nama = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $hariLabel = empty($hari) ? 'Setiap hari' : implode(', ', array_map(fn ($h) => $nama[$h] ?? $h, $hari));

        return $hariLabel . ' · ' . ($aturan['jam_buka'] ?? '07:00') . '–' . ($aturan['jam_tutup'] ?? '21:00');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => 'required|string|max:40|unique:ruangan,kode',
            'nama' => 'required|string|max:120',
            'lokasi' => 'nullable|string|max:160',
            'kapasitas' => 'nullable|integer|min:1|max:1000',
            'fasilitas' => 'nullable|string|max:500',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'hari' => 'nullable|array',
            'hari.*' => 'integer|min:1|max:7',
            'jam_buka' => 'nullable|date_format:H:i',
            'jam_tutup' => 'nullable|date_format:H:i',
            'min_advance_days' => 'nullable|integer|min:0|max:90',
            'max_advance_days' => 'nullable|integer|min:0|max:90',
            'max_duration_minutes' => 'nullable|integer|min:15|max:720',
            'max_pending_per_team' => 'nullable|integer|min:1|max:10',
            'max_peserta' => 'nullable|integer|min:1|max:1000',
        ]);

        $fotoBaru = $this->simpanFoto($request);
        try {
            Ruangan::create([
                'kode' => strtoupper($data['kode']),
                'nama' => $data['nama'],
                'lokasi' => $data['lokasi'] ?? null,
                'kapasitas' => $data['kapasitas'] ?? null,
                'fasilitas' => $this->fasilitasDari($data['fasilitas'] ?? null),
                'foto' => $fotoBaru,
                'aturan' => $this->aturanDari($data),
            ]);
        } catch (\Throwable $e) {
            $this->hapusFotoBaru($fotoBaru);
            return $this->gagalSimpanRuangan($e);
        }

        return back()->with('status', 'Ruangan ditambahkan.');
    }

    public function update(Request $request, Ruangan $ruangan)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:120',
            'lokasi' => 'nullable|string|max:160',
            'kapasitas' => 'nullable|integer|min:1|max:1000',
            'fasilitas' => 'nullable|string|max:500',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'hapus_foto' => 'nullable|boolean',
            'aktif' => 'nullable|boolean',
            'hari' => 'nullable|array',
            'hari.*' => 'integer|min:1|max:7',
            'jam_buka' => 'nullable|date_format:H:i',
            'jam_tutup' => 'nullable|date_format:H:i',
            'min_advance_days' => 'nullable|integer|min:0|max:90',
            'max_advance_days' => 'nullable|integer|min:0|max:90',
            'max_duration_minutes' => 'nullable|integer|min:15|max:720',
            'max_pending_per_team' => 'nullable|integer|min:1|max:10',
            'max_peserta' => 'nullable|integer|min:1|max:1000',
        ]);

        $fotoLama = $ruangan->foto;
        $foto = $fotoLama;
        $fotoBaru = null;
        if ($request->hasFile('foto')) {
            $fotoBaru = $this->simpanFoto($request);
            $foto = $fotoBaru;
        } elseif (!empty($data['hapus_foto'])) {
            $foto = null;
        }

        try {
            $ruangan->update([
                'nama' => $data['nama'],
                'lokasi' => $data['lokasi'] ?? null,
                'kapasitas' => array_key_exists('kapasitas', $data) ? $data['kapasitas'] : $ruangan->kapasitas,
                'fasilitas' => array_key_exists('fasilitas', $data) ? $this->fasilitasDari($data['fasilitas']) : $ruangan->fasilitas,
                'foto' => $foto,
                'aktif' => (bool) ($data['aktif'] ?? $ruangan->aktif),
                'aturan' => $this->aturanDari($data),
            ]);
        } catch (\Throwable $e) {
            $this->hapusFotoBaru($fotoBaru);
            return $this->gagalSimpanRuangan($e);
        }

        // Hapus foto lama hanya setelah update DB berhasil — mencegah file hilang saat update gagal.
        // PENTING: pakai path lama yang dicapture sebelum update — $ruangan->foto kini berisi path baru.
        if ($fotoBaru || !empty($data['hapus_foto'])) {
            $this->hapusFotoPath($fotoLama);
        }

        return back()->with('status', 'Ruangan diperbarui.');
    }

    private function aturanDari(array $data): array
    {
        return array_filter([
            'hari' => isset($data['hari']) ? array_map('intval', $data['hari']) : [],
            'jam_buka' => $data['jam_buka'] ?? null,
            'jam_tutup' => $data['jam_tutup'] ?? null,
            'min_advance_days' => isset($data['min_advance_days']) ? (int) $data['min_advance_days'] : null,
            'max_advance_days' => isset($data['max_advance_days']) ? (int) $data['max_advance_days'] : null,
            'max_duration_minutes' => isset($data['max_duration_minutes']) ? (int) $data['max_duration_minutes'] : null,
            'max_pending_per_team' => isset($data['max_pending_per_team']) ? (int) $data['max_pending_per_team'] : null,
            'max_peserta' => isset($data['max_peserta']) ? (int) $data['max_peserta'] : null,
        ], fn ($v) => $v !== null);
    }

    /** Daftar fasilitas dari input teks (pisah koma/baris baru) → array bersih. */
    private function fasilitasDari(?string $input): ?array
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $items = preg_split('/[\r\n,]+/', $input) ?: [];
        $items = array_values(array_filter(array_map(
            fn ($s) => mb_substr(trim($s), 0, 60),
            $items
        )));

        return $items ?: null;
    }

    private function simpanFoto(Request $request): ?string
    {
        if (!$request->hasFile('foto')) {
            return null;
        }

        $dir = public_path('uploads/ruangan');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $request->file('foto');
        $name = 'ruangan-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);

        return 'uploads/ruangan/' . $name;
    }

    private function hapusFotoPath(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/ruangan/')) {
            \Illuminate\Support\Facades\File::delete(public_path($path));
        }
    }

    /** Bersihkan foto yang baru diupload bila penyimpanan DB gagal (mis. kolom profil belum termigrasi). */
    private function hapusFotoBaru(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/ruangan/')) {
            \Illuminate\Support\Facades\File::delete(public_path($path));
        }
    }

    /**
     * Respon kegagalan persistensi ruangan. Jika penyebabnya kolom profil
     * (kapasitas/fasilitas/foto) yang belum termigrasi, beri pesan jelas;
     * selain itu lempar ulang agar tidak menutupi bug sungguhan.
     */
    private function gagalSimpanRuangan(\Throwable $e)
    {
        $kolomProfil = ['kapasitas', 'fasilitas', 'foto'];
        $isSchema = str_contains($e->getMessage(), 'Unknown column')
            && collect($kolomProfil)->contains(fn ($c) => str_contains($e->getMessage(), "'{$c}'"));
        if ($isSchema) {
            return back()->withErrors([
                'foto' => 'Data ruangan belum tersimpan: kolom profil ruangan (kapasitas/fasilitas/foto) belum ada di database. '
                    . 'Jalankan migrasi add_ruangan_profil_fields terlebih dahulu.',
            ]);
        }
        throw $e;
    }

    // ---------- Petugas (cleaning service, tanpa portal) ----------

    public function petugasIndex(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();

        $q = trim((string) $request->query('q', ''));
        $tab = (string) $request->query('tab', 'daftar');
        if (! in_array($tab, ['daftar', 'akses', 'riwayat'], true)) {
            $tab = 'daftar';
        }

        $ruanganMap = Ruangan::query()->get(['id', 'nama', 'kode'])->keyBy('id');
        $namaHari = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $kredLabel = ['pin' => 'PIN', 'rfid' => 'RFID', 'fingerprint' => 'Fingerprint'];

        $petugasQuery = RuanganPetugas::orderBy('nama');
        if ($q !== '') {
            $petugasQuery->where(fn ($qb) => $qb->where('nama', 'like', "%{$q}%")
                ->orWhere('id_internal', 'like', "%{$q}%"));
        }
        $petugasAll = $petugasQuery->get();

        $petugas = $petugasAll->map(function (RuanganPetugas $p) use ($ruanganMap, $namaHari, $kredLabel) {
            $jadwal = collect($p->jadwal ?? [])->map(function ($j) use ($ruanganMap, $namaHari) {
                $r = $ruanganMap->get($j['ruangan_id'] ?? 0);
                $hari = $j['hari'] ?? [];
                return [
                    'ruangan_id' => $j['ruangan_id'] ?? null,
                    'ruangan' => $r?->nama ?? '—',
                    'hari' => $hari,
                    'hari_label' => empty($hari) ? 'Setiap hari' : implode(', ', array_map(fn ($h) => $namaHari[$h] ?? $h, $hari)),
                    'jam_mulai' => $j['jam_mulai'] ?? null,
                    'jam_selesai' => $j['jam_selesai'] ?? null,
                    'jam_label' => ($j['jam_mulai'] ?? '—') . '–' . ($j['jam_selesai'] ?? '—'),
                ];
            })->values()->all();

            return [
                'id' => $p->id,
                'nama' => $p->nama,
                'id_internal' => $p->id_internal,
                'tipe_kredensial' => $p->tipe_kredensial,
                'kredensial_label' => $kredLabel[$p->tipe_kredensial] ?? $p->tipe_kredensial,
                'status' => $p->status,
                'berlaku_mulai' => $p->berlaku_mulai?->translatedFormat('d M Y'),
                'berlaku_selesai' => $p->berlaku_selesai?->translatedFormat('d M Y'),
                'jadwal' => $jadwal,
                'ruangan_akses' => collect($jadwal)->pluck('ruangan')->unique()->values()->all(),
            ];
        })->values()->all();

        // Matriks akses ruangan — flatten jadwal per petugas × ruangan.
        $akses = collect($petugas)->flatMap(fn ($p) => collect($p['jadwal'])->map(fn ($j) => [
            'petugas' => $p['nama'], 'id_internal' => $p['id_internal'], 'status' => $p['status'],
            'ruangan' => $j['ruangan'], 'hari_label' => $j['hari_label'], 'jam_label' => $j['jam_label'],
        ]))->values()->all();

        $riwayat = RuanganAccessEvent::query()
            ->with(['ruangan:id,nama', 'user:id,nama_lengkap', 'mesin:id,nama_mesin,id_mesin'])
            ->orderByDesc('terjadi_pada')->limit(12)->get()
            ->map(function (RuanganAccessEvent $e) use ($petugasAll) {
                $ptg = $e->petugas_id ? $petugasAll->firstWhere('id', $e->petugas_id) : null;
                return [
                    'waktu' => $e->terjadi_pada?->translatedFormat('d M Y, H:i') ?? '—',
                    'nama' => $ptg?->nama ?? $e->user?->nama_lengkap ?? ($e->mesin?->nama_mesin ?: $e->mesin?->id_mesin) ?? '—',
                    'jenis' => $e->jenis,
                    'jenis_label' => match ($e->jenis) {
                        'buka' => 'Buka Ruangan', 'tutup' => 'Tutup Ruangan',
                        'check' => 'Verifikasi Izin', 'tolak' => 'Akses Ditolak',
                        'telemetri' => 'Telemetri', default => (string) $e->jenis,
                    },
                    'hasil' => $e->hasil,
                    'ruangan' => $e->ruangan?->nama ?? '—',
                    'keterangan' => $e->meta['keterangan'] ?? $e->meta['catatan']
                        ?? self::alasanAksesLabel($e->meta['alasan'] ?? null, $e->meta['tipe'] ?? null),
                ];
            })->values()->all();

        $all = RuanganPetugas::query()->get(['id', 'status']);
        $stats = [
            'total' => $all->count(),
            'aktif' => $all->where('status', 'aktif')->count(),
            'nonaktif' => $all->where('status', '!=', 'aktif')->count(),
            'akses_bulan_ini' => RuanganAccessEvent::query()
                ->whereMonth('terjadi_pada', now()->month)
                ->whereYear('terjadi_pada', now()->year)->count(),
        ];

        return \Inertia\Inertia::render('Petugas', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'filters' => ['q' => $q],
            'tab' => $tab,
            'stats' => $stats,
            'petugas' => $petugas,
            'akses' => $akses,
            'riwayat' => $riwayat,
            'ruangan_options' => Ruangan::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'kode'])
                ->map(fn ($r) => ['id' => $r->id, 'nama' => $r->nama, 'kode' => $r->kode]),
            'can' => [
                'create' => \App\Support\PermissionRegistry::canCurrent('petugas', 'create'),
                'update' => \App\Support\PermissionRegistry::canCurrent('petugas', 'update'),
            ],
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'petugas')
                    : route('admin.petugas.index'),
                'store' => route('admin.petugas.store'),
                'update_base' => route('admin.petugas.index') . '/',
            ],
        ]);
    }

    public function petugasStore(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:120',
            'id_internal' => 'required|string|max:60|unique:ruangan_petugas,id_internal',
            'tipe_kredensial' => 'required|in:pin,rfid,fingerprint',
            'kredensial' => 'required|string|max:128',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_selesai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'jadwal_ruangan_id' => 'required|exists:ruangan,id',
            'jadwal_hari' => 'nullable|array',
            'jadwal_hari.*' => 'integer|min:1|max:7',
            'jadwal_jam_mulai' => 'required|date_format:H:i',
            'jadwal_jam_selesai' => 'required|date_format:H:i|after:jadwal_jam_mulai',
        ]);

        RuanganPetugas::create([
            'nama' => $data['nama'],
            'id_internal' => $data['id_internal'],
            'tipe_kredensial' => $data['tipe_kredensial'],
            'kredensial_hash' => $this->hashKredensial($data['tipe_kredensial'], $data['kredensial']),
            'berlaku_mulai' => $data['berlaku_mulai'] ?? null,
            'berlaku_selesai' => $data['berlaku_selesai'] ?? null,
            'jadwal' => [[
                'ruangan_id' => (int) $data['jadwal_ruangan_id'],
                'hari' => array_map('intval', $data['jadwal_hari'] ?? []),
                'jam_mulai' => $data['jadwal_jam_mulai'],
                'jam_selesai' => $data['jadwal_jam_selesai'],
            ]],
        ]);

        return back()->with('status', 'Petugas ditambahkan.');
    }

    public function petugasUpdate(Request $request, RuanganPetugas $petugas)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:120',
            'status' => 'required|in:aktif,nonaktif',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_selesai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'kredensial' => 'nullable|string|max:128',
            'jadwal' => 'nullable|array',
        ]);

        $petugas->update([
            'nama' => $data['nama'],
            'status' => $data['status'],
            'berlaku_mulai' => $data['berlaku_mulai'] ?? null,
            'berlaku_selesai' => $data['berlaku_selesai'] ?? null,
            'kredensial_hash' => isset($data['kredensial'])
                ? $this->hashKredensial($petugas->tipe_kredensial, $data['kredensial'])
                : $petugas->kredensial_hash,
            'jadwal' => $data['jadwal'] ?? $petugas->jadwal,
        ]);

        return back()->with('status', 'Petugas diperbarui.');
    }

    /** Terjemahkan kode alasan akses mesin menjadi teks yang dibaca manusia. */
    private static function alasanAksesLabel(?string $alasan, ?string $tipe): string
    {
        $map = [
            'izin_aktif' => 'Izin pengajuan aktif',
            'akses_staf' => 'Hak akses staf (bebas ruangan)',
            'tidak_ada_izin' => 'Tidak ada izin ruangan',
            'izin_kedaluwarsa' => 'Izin sudah kedaluwarsa',
            'izin_selesai' => 'Izin sudah selesai',
            'izin_dicabut' => 'Izin dicabut',
            'jadwal_petugas' => 'Sesuai jadwal petugas',
            'diluar_jadwal_petugas' => 'Di luar jadwal petugas',
            'petugas_tidak_berlaku' => 'Masa berlaku petugas habis',
            'identitas_tidak_dikenal' => 'Kredensial tidak dikenal',
        ];
        $label = $map[$alasan ?? ''] ?? $alasan ?? '—';
        $tipeLabel = match ($tipe) { 'pin' => 'PIN', 'rfid' => 'kartu', 'fingerprint' => 'sidik jari', default => null };

        return $tipeLabel ? "{$label} (via {$tipeLabel})" : $label;
    }

    private function hashKredensial(string $tipe, string $nilai): string
    {
        return match ($tipe) {
            'rfid' => hash('sha256', Str::upper(trim($nilai))),
            'fingerprint' => (string) $nilai,   // referensi finger_id, bukan template
            default => Hash::make($nilai),       // pin → bcrypt
        };
    }

    // ---------- Binding perangkat (is_admin) ----------

    public function bindMesin(Request $request, MesinIot $mesin)
    {
        // Kewenangan sistem/perangkat: butuh role admin (atau flag is_admin transisi), bukan sekadar laboran operasional.
        abort_unless($request->user()?->isAdmin(), 403, 'Binding perangkat ke ruangan memerlukan kewenangan admin.');
        $data = $request->validate(['ruangan_id' => 'nullable|exists:ruangan,id']);
        $mesin->update(['ruangan_id' => $data['ruangan_id'] ?? null]);

        return back()->with('status', $data['ruangan_id'] ? 'Perangkat diikat ke ruangan.' : 'Binding perangkat dilepas.');
    }
}
