<?php

namespace App\Http\Controllers;

use App\Models\Ruangan;
use App\Models\RuanganPengajuan;
use App\Models\User;
use App\Support\AdminAudit;
use App\Support\RuanganBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Pengajuan ruangan SMART BRAIL — mahasiswa mengajukan untuk timnya,
 * dosen memantau tim bimbingan, laboran memutuskan.
 */
class RuanganPengajuanController extends Controller
{
    use Concerns\AdminShellProps;

    // ---------- Mahasiswa ----------

    public function indexMahasiswa(Request $request)
    {
        $user = $request->user();
        $tim = RuanganBookingService::timMahasiswa($user);

        $pengajuan = RuanganPengajuan::with(['ruangan', 'diputusOleh:id,nama_lengkap,role', 'project:id,nama', 'pengaju:id,nama_lengkap', 'izin' => fn ($q) => $q->where('user_id', $user->id)])
            ->when($tim, fn ($q) => $q->where('pbl_project_id', $tim->id))
            ->when(!$tim, fn ($q) => $q->where('pengaju_id', $user->id))
            ->latest('tanggal')->latest('jam_mulai')
            ->limit(50)->get()
            ->each(function (RuanganPengajuan $p) {
                $p->setAttribute('diputus_oleh_nama', $p->diputusOleh?->nama_lengkap);
                $p->setAttribute('diputus_oleh_role', $p->diputusOleh?->role);
                $p->setAttribute('tim_nama', $p->project?->nama);
                $p->setAttribute('pengaju_nama', $p->snapshot['pengaju']['name'] ?? $p->pengaju?->nama_lengkap);
                $p->ruangan?->setAttribute('foto_url', $p->ruangan->foto ? asset($p->ruangan->foto) : null);
            });

        $anggota = $tim ? RuanganBookingService::anggotaTim($tim)
            ->map(fn ($u) => [
                'id' => $u->id,
                'nama_lengkap' => $u->nama_lengkap,
                'nitk_nidn' => $u->nitk_nidn,
                'foto' => $u->profile_photo_url,
            ])->values() : collect();

        $ruanganList = Ruangan::where('aktif', true)->orderBy('nama')->get()
            ->map(fn (Ruangan $r) => [
                'model' => tap($r, fn ($m) => $m->foto_url = $r->foto ? asset($r->foto) : null),
                'aturan' => $r->aturan(),
            ]);

        return \Inertia\Inertia::render('Student/RoomRequest', [
            'tim' => $tim,
            'anggota' => $anggota,
            'ruanganList' => $ruanganList,
            'pengajuan' => $pengajuan,
            'tabRiwayat' => $request->query('tab') === 'riwayat',
        ]);
    }

    /**
     * Ketersediaan slot satu ruangan/tanggal/durasi (JSON) — read-only,
     * tanpa identitas pemesan lain. Dipakai pemilih waktu halaman pengajuan.
     */
    public function ketersediaanMahasiswa(Request $request)
    {
        $data = $request->validate([
            'ruangan_id' => 'required|integer|exists:ruangan,id',
            'tanggal' => 'required|date_format:Y-m-d',
            'durasi' => 'nullable|integer|min:15|max:720',
        ]);

        $ruangan = Ruangan::findOrFail((int) $data['ruangan_id']);
        $durasi = min((int) ($data['durasi'] ?? 60), (int) $ruangan->aturan()['max_duration_minutes']);

        return response()->json([
            'success' => true,
            'ketersediaan' => RuanganBookingService::ketersediaanHarian(
                $ruangan,
                Carbon::parse($data['tanggal'], 'Asia/Jakarta'),
                max(15, $durasi)
            ),
        ]);
    }

    public function storeMahasiswa(Request $request)
    {
        $user = $request->user();
        $tim = RuanganBookingService::timMahasiswa($user);
        if (!$tim) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum tergabung dalam tim — hubungi dosen pembimbing atau laboran.',
                ], 422);
            }

            return back()->withErrors(['pengajuan' => 'Anda belum tergabung dalam tim — hubungi dosen pembimbing atau laboran.']);
        }

        $data = $request->validate([
            'ruangan_id' => 'required|exists:ruangan,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'tujuan' => 'required|string|max:500',
            'peserta' => 'nullable|array',
            'peserta.*' => 'integer',
        ]);
        $data['peserta'] = $data['peserta'] ?? [$user->id];

        $ruangan = Ruangan::findOrFail($data['ruangan_id']);

        try {
            $pengajuan = RuanganBookingService::ajukan($user, $ruangan, $tim, $data);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Pengajuan tidak valid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengajuan ruangan terkirim — menunggu keputusan petugas.',
                'pengajuan' => [
                    'id' => $pengajuan->id,
                    'ruangan' => $ruangan->nama,
                    'tanggal' => $pengajuan->tanggal->toDateString(),
                    'jam_mulai' => substr((string) $pengajuan->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $pengajuan->jam_selesai, 0, 5),
                    'status' => $pengajuan->statusEfektif(),
                    'status_label' => 'Menunggu keputusan',
                ],
                'history_url' => route('mahasiswa.ruangan'),
            ]);
        }

        return redirect()->route('mahasiswa.ruangan')->with('terkirim', [
            'id' => $pengajuan->id,
            'ruangan' => $ruangan->nama,
            'tanggal' => $pengajuan->tanggal->toDateString(),
            'jam_mulai' => substr((string) $pengajuan->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $pengajuan->jam_selesai, 0, 5),
            'tujuan' => $pengajuan->tujuan,
            'peserta' => collect($pengajuan->snapshot['peserta'] ?? [])->pluck('name')->all(),
        ]);
    }

    public function terimaPengganti(Request $request, RuanganPengajuan $pengajuan)
    {
        try {
            $prioritas = RuanganBookingService::terimaPengganti($pengajuan, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('mahasiswa.ruangan')->with('status', $prioritas
            ? 'Konfirmasi diterima — pengajuan kembali ke antrean prioritas petugas.'
            : 'Waktu pengganti diterima — izin aktif.');
    }

    public function tolakPengganti(Request $request, RuanganPengajuan $pengajuan)
    {
        $data = $request->validate([
            'alasan' => 'required|string|min:3|max:500',
        ], [
            'alasan.required' => 'Isi alasan Anda menolak tawaran ini — petugas perlu tahu kenapa.',
        ]);
        try {
            RuanganBookingService::tolakPengganti($pengajuan, $request->user(), $data['alasan']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('mahasiswa.ruangan')->with('status', 'Tawaran ditolak — pengajuan diakhiri.');
    }

    public function batalkan(Request $request, RuanganPengajuan $pengajuan)
    {
        try {
            RuanganBookingService::batalkan($pengajuan, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('mahasiswa.ruangan')->with('status', 'Pengajuan dibatalkan.');
    }

    // ---------- Dosen (tim bimbingan) ----------

    public function indexDosen(Request $request)
    {
        $timIds = RuanganBookingService::timBimbingan($request->user())->pluck('id');

        $pengajuan = RuanganPengajuan::with(['ruangan', 'pengaju'])
            ->whereIn('pbl_project_id', $timIds)
            ->latest('tanggal')->limit(100)->get();

        return view('dosen.ruangan', compact('pengajuan'));
    }

    // ---------- Laboran (persetujuan) ----------

    public function indexLaboran(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();

        $status = (string) $request->query('status', 'menunggu');
        if (! in_array($status, ['menunggu', 'disetujui', 'ditolak', 'dibatalkan', 'semua'], true)) {
            $status = 'menunggu';
        }
        $q = trim((string) $request->query('q', ''));
        $perPage = in_array((int) $request->query('per_page', 10), [5, 10, 25], true)
            ? (int) $request->query('per_page', 10) : 10;

        $countFor = fn (?array $st) => RuanganPengajuan::query()
            ->when($st, fn ($qb) => $qb->whereIn('status', $st))->count();
        $stats = [
            'total' => RuanganPengajuan::count(),
            'menunggu' => $countFor(RuanganPengajuan::STATUS_AKTIF_MENUNGGU),
            'disetujui' => $countFor([RuanganPengajuan::STATUS_DISETUJUI]),
            'ditolak' => $countFor([RuanganPengajuan::STATUS_DITOLAK]),
            'berlangsung' => RuanganPengajuan::query()
                ->where('status', RuanganPengajuan::STATUS_DISETUJUI)
                ->whereDate('tanggal', today())->count(),
        ];
        $stats['menunggu_7d'] = RuanganPengajuan::query()
            ->whereIn('status', RuanganPengajuan::STATUS_AKTIF_MENUNGGU)
            ->where('created_at', '>=', now()->subDays(7))->count();
        $stats['disetujui_pct'] = $stats['total'] > 0 ? round($stats['disetujui'] / $stats['total'] * 100) : 0;
        $stats['ditolak_pct'] = $stats['total'] > 0 ? round($stats['ditolak'] / $stats['total'] * 100) : 0;

        $query = RuanganPengajuan::with(['ruangan:id,nama,kode', 'pengaju:id,nama_lengkap,role,nitk_nidn'])->latest('tanggal');
        if ($status === 'menunggu') {
            $query->whereIn('status', RuanganPengajuan::STATUS_AKTIF_MENUNGGU);
        } elseif ($status !== 'semua') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('tujuan', 'like', "%{$q}%")
                    ->orWhereHas('pengaju', fn ($p) => $p->where('nama_lengkap', 'like', "%{$q}%")
                        ->orWhere('nitk_nidn', 'like', "%{$q}%"));
            });
        }

        $pengajuIds = RuanganPengajuan::query()->whereNotNull('diputus_oleh')->distinct()->pluck('diputus_oleh');
        $diputusOleh = User::whereIn('id', $pengajuIds)->pluck('nama_lengkap', 'id');

        $paginator = $query->paginate($perPage)->withQueryString();
        $rows = collect($paginator->items())->map(function (RuanganPengajuan $p) use ($diputusOleh) {
            $st = $p->statusEfektif();
            $snap = $p->snapshot ?? [];
            return [
                'id' => $p->id,
                'kode' => 'PR-' . $p->tanggal->format('Y') . '-' . str_pad((string) $p->id, 4, '0', STR_PAD_LEFT),
                'judul' => $p->tujuan,
                'status' => $p->status,
                'status_efektif' => $st,
                'boleh_putus' => in_array($p->statusEfektif(), RuanganPengajuan::STATUS_AKTIF_MENUNGGU, true),
                'pemohon' => [
                    'nama' => $snap['pengaju']['name'] ?? $p->pengaju?->nama_lengkap ?? '—',
                    'role' => $p->pengaju?->role ?? '',
                    'tim' => $snap['tim']['nama'] ?? null,
                ],
                'pembimbing' => $snap['pembimbing']['name'] ?? null,
                'ruangan' => $p->ruangan?->nama ?? '—',
                'tanggal' => self::dfmt($p->tanggal, 'd M Y', 'd/m/Y'),
                'tanggal_iso' => $p->tanggal->toDateString(),
                'hari' => $p->tanggal->translatedFormat('l'),
                'jam' => substr((string) $p->jam_mulai, 0, 5) . '–' . substr((string) $p->jam_selesai, 0, 5),
                'peserta_n' => count($p->peserta ?? []),
                'peserta' => collect($snap['peserta'] ?? [])->pluck('name')->all(),
                'pengganti' => ($p->pengganti_mulai || $p->pengganti_catatan) ? [
                    'mulai' => ($p->pengganti_mulai ? self::dfmt($p->pengganti_mulai, 'd M Y H:i', 'd/m/Y H:i') : null),
                    'selesai' => ($p->pengganti_selesai ? self::dfmt($p->pengganti_selesai, 'd M Y H:i', 'd/m/Y H:i') : null),
                    'catatan' => $p->pengganti_catatan,
                ] : null,
                'alasan' => $p->alasan_keputusan,
                'alasan_status' => $p->alasan_status,
                'diputus_oleh' => $p->diputus_oleh ? ($diputusOleh[$p->diputus_oleh] ?? '—') : null,
                'diputus_pada' => ($p->diputus_pada ? self::dfmt($p->diputus_pada, 'd M Y, H:i', 'd/m/Y, H:i') : null),
                'dibuat' => ($p->created_at ? self::dfmt($p->created_at, 'd M Y, H:i', 'd/m/Y, H:i') : null),
            ];
        })->values()->all();

        // Riwayat keputusan (audit) — semua yang sudah diputus.
        $riwayat = RuanganPengajuan::query()
            ->with(['ruangan:id,nama', 'pengaju:id,nama_lengkap'])
            ->whereNotNull('diputus_pada')
            ->orderByDesc('diputus_pada')->limit(8)->get()
            ->map(fn (RuanganPengajuan $p) => [
                'waktu' => self::dfmt($p->diputus_pada, 'd M Y, H:i', 'd/m/Y, H:i'),
                'kode' => 'PR-' . $p->tanggal->format('Y') . '-' . str_pad((string) $p->id, 4, '0', STR_PAD_LEFT),
                'kegiatan' => $p->tujuan,
                'ruangan' => $p->ruangan?->nama ?? '—',
                'pemohon' => $p->snapshot['pengaju']['name'] ?? $p->pengaju?->nama_lengkap ?? '—',
                'keputusan' => $p->status,
                'oleh' => $p->diputus_oleh ? ($diputusOleh[$p->diputus_oleh] ?? '—') : 'Sistem',
            ])->values()->all();

        return \Inertia\Inertia::render('PengajuanRuangan', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'filters' => ['status' => $status, 'q' => $q, 'per_page' => $perPage],
            'stats' => $stats,
            'pengajuan' => [
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
            'riwayat' => $riwayat,
            'can' => ['update' => \App\Support\PermissionRegistry::canCurrent('ruangan_persetujuan', 'update')],
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'pengajuan')
                    : route('admin.pengajuan.index'),
                'setujui_base' => route('admin.pengajuan.index') . '/',
                'tolak_base' => route('admin.pengajuan.index') . '/',
                'pengganti_base' => route('admin.pengajuan.index') . '/',
                'cabut_base' => route('admin.pengajuan.index') . '/',
            ],
        ]);
    }

    public function setujui(Request $request, RuanganPengajuan $pengajuan)
    {
        try {
            RuanganBookingService::setujui($pengajuan, $request->user(), $request->input('alasan'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        AdminAudit::catat('pengajuan.setujui', $this->labelPengajuan($pengajuan), $request->input('alasan'));

        return back()->with('status', 'Pengajuan disetujui — izin peserta diterbitkan.');
    }

    public function tolak(Request $request, RuanganPengajuan $pengajuan)
    {
        $request->validate(['alasan' => 'required|string|min:3|max:500']);
        try {
            RuanganBookingService::tolak($pengajuan, $request->user(), $request->input('alasan'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        AdminAudit::catat('pengajuan.tolak', $this->labelPengajuan($pengajuan), $request->input('alasan'));

        return back()->with('status', 'Pengajuan ditolak.');
    }

    public function tawarkanPengganti(Request $request, RuanganPengajuan $pengajuan)
    {
        $data = $request->validate([
            'pengganti_mulai' => 'nullable|date|required_with:pengganti_selesai',
            'pengganti_selesai' => 'nullable|date|after:pengganti_mulai|required_with:pengganti_mulai',
            'catatan' => 'required|string|min:5|max:500',
        ], [
            'catatan.required' => 'Alasan tawaran wajib diisi — mahasiswa perlu tahu kenapa jadwalnya dialihkan.',
        ]);
        try {
            RuanganBookingService::tawarkanPengganti(
                $pengajuan,
                $request->user(),
                $data['pengganti_mulai'] ? Carbon::parse($data['pengganti_mulai']) : null,
                $data['pengganti_selesai'] ? Carbon::parse($data['pengganti_selesai']) : null,
                $data['catatan']
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        AdminAudit::catat('pengajuan.pengganti', $this->labelPengajuan($pengajuan), $data['catatan']);

        return back()->with('status', 'Tawaran terkirim — menunggu penerimaan pengaju.');
    }

    public function cabut(Request $request, RuanganPengajuan $pengajuan)
    {
        try {
            RuanganBookingService::cabut($pengajuan, $request->user(), $request->input('alasan'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        AdminAudit::catat('pengajuan.cabut', $this->labelPengajuan($pengajuan), $request->input('alasan'));

        return back()->with('status', 'Pengajuan dicabut — izin peserta diakhiri.');
    }

    private function labelPengajuan(RuanganPengajuan $p): string
    {
        $ruangan = $p->ruangan?->nama ?? $p->ruangan?->kode;

        return 'Pengajuan #'.$p->id.($ruangan ? " · {$ruangan}" : '');
    }

    /** Format tanggal mengikuti preferensi viewer (format_tanggal: teks|angka). */
    private static function dfmt(\DateTimeInterface $c, string $teks, string $angka): string
    {
        $u = request()->user();

        return $u ? $u->dfmt($c, $teks, $angka) : Carbon::instance($c)->translatedFormat($teks);
    }
}
