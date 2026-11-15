<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AdminShellProps;
use App\Models\MesinIot;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MesinIotController extends Controller
{
    use AdminShellProps;

    public function index(Request $request)
    {
        $query = MesinIot::query()->where('tipe', '!=', 'loker')->with('ruangan:id,nama,kode');

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $query->where(function ($qb) use ($keyword) {
                $qb->where('id_mesin', 'like', "%{$keyword}%")
                    ->orWhere('nama_mesin', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $this->normalizeType((string) $request->tipe));
        }

        if ($request->filled('status')) {
            $status = (string) $request->status;
            if ($status === 'aktif') {
                $query->where('is_active', true);
            } elseif ($status === 'nonaktif') {
                $query->where('is_active', false);
            } elseif ($status === 'online') {
                $query->online();
            } elseif ($status === 'offline') {
                $query->where('is_active', true)->where(function ($qb) {
                    $qb->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', now()->subMinutes(MesinIot::ONLINE_THRESHOLD_MINUTES));
                });
            }
        }

        $mesin = $query
            ->orderByRaw('is_active DESC')
            ->orderByRaw('last_seen_at IS NULL')
            ->orderByDesc('last_seen_at')
            ->orderBy('id_mesin')
            ->paginate(15)
            ->withQueryString();

        $typeOptions = $this->typeOptions();

        $total = MesinIot::query()->where('tipe', '!=', 'loker')->count();
        $active = MesinIot::query()->where('tipe', '!=', 'loker')->where('is_active', true)->count();
        $online = MesinIot::online()->where('tipe', '!=', 'loker')->count();
        $stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => max(0, $total - $active),
            'online' => $online,
            'offline' => max(0, $active - $online),
            'by_type' => MesinIot::query()
                ->where('tipe', '!=', 'loker')
                ->selectRaw('tipe, COUNT(*) as total')
                ->groupBy('tipe')
                ->pluck('total', 'tipe')
                ->all(),
        ];

        // Kontak tap terakhir per mesin (satu query agregat untuk halaman ini).
        $lastTaps = \App\Models\LogAbsensi::query()
            ->whereIn('id_mesin', $mesin->pluck('id_mesin'))
            ->selectRaw('id_mesin, MAX(waktu_tap) as last_tap')
            ->groupBy('id_mesin')
            ->pluck('last_tap', 'id_mesin');

        $user = $request->user();
        $isAdmin = (bool) ($user?->isAdmin());
        $can = [
            'create' => $isAdmin && \App\Support\PermissionRegistry::canCurrent('mesin', 'create'),
            'update' => $isAdmin && \App\Support\PermissionRegistry::canCurrent('mesin', 'update'),
            'delete' => $isAdmin && \App\Support\PermissionRegistry::canCurrent('mesin', 'delete'),
        ];

        $items = $mesin->getCollection()->map(function (MesinIot $m) use ($lastTaps) {
            $conn = !$m->is_active ? 'inactive' : ($m->isOnline() ? 'online' : 'offline');
            $lastTap = $lastTaps[$m->id_mesin] ?? null;
            return [
                'id' => $m->id,
                'id_mesin' => $m->id_mesin,
                'nama_mesin' => $m->nama_mesin ?: $m->id_mesin,
                'tipe' => (string) $m->tipe,
                'tipe_label' => Str::title(str_replace(['_', '-'], ' ', (string) $m->tipe)),
                'ruangan' => $m->ruangan?->nama,
                'ruangan_id' => $m->ruangan_id,
                'is_active' => (bool) $m->is_active,
                'conn' => $conn,
                'conn_label' => match ($conn) {
                    'online' => 'Online',
                    'offline' => 'Offline',
                    default => 'Nonaktif',
                },
                'last_seen_label' => $m->last_seen_at?->diffForHumans() ?? 'Belum heartbeat',
                'last_seen_exact' => $m->last_seen_at?->translatedFormat('d M Y H:i:s'),
                'last_tap_label' => $lastTap ? \Carbon\Carbon::parse($lastTap)->diffForHumans() : 'Belum ada tap',
                'last_tap_exact' => $lastTap ? \Carbon\Carbon::parse($lastTap)->translatedFormat('d M Y H:i:s') : null,
                'updated_label' => $m->updated_at?->translatedFormat('d M Y · H:i'),
            ];
        })->values()->all();

        // Setup sekali tampil setelah create/regenerate — plain key hanya lewat sini.
        $setup = null;
        if (session('mesin_plain_key')) {
            $setup = [
                'key' => session('mesin_plain_key'),
                'id' => session('mesin_plain_id'),
                'name' => session('mesin_plain_name', session('mesin_plain_id')),
                'tipe' => session('mesin_plain_tipe', 'absensi'),
                'mode' => session('mesin_setup_mode', 'create'),
            ];
        }

        Inertia::setRootView('app-admin');

        return Inertia::render('Mesin', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'stats' => $stats,
            'type_options' => $typeOptions,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'tipe' => (string) $request->query('tipe', ''),
                'status' => (string) $request->query('status', ''),
            ],
            'mesin' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $mesin->currentPage(),
                    'last_page' => $mesin->lastPage(),
                    'total' => $mesin->total(),
                    'per_page' => $mesin->perPage(),
                    'from' => $mesin->firstItem(),
                    'to' => $mesin->lastItem(),
                ],
                'links' => $mesin->linkCollection()->map(fn ($l) => [
                    'url' => $l['url'], 'label' => $l['label'], 'active' => $l['active'],
                ])->all(),
            ],
            'can' => $can,
            'ruangan_options' => $isAdmin
                ? \App\Models\Ruangan::query()->where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'kode'])
                : [],
            'setup' => $setup,
            'threshold_minutes' => MesinIot::ONLINE_THRESHOLD_MINUTES,
            'api_base' => rtrim(url('/api/mesin'), '/'),
            'urls' => [
                'index' => route('admin.mesin.index'),
                'store' => route('admin.mesin.store'),
                'monitoring' => route('admin.monitoring'),
                'log' => route('admin.log.absensi'),
            ],
        ]);
    }

    /**
     * Halaman panduan integrasi + program mesin (Arduino IDE).
     * Referensi endpoint per tipe mesin dan unduh sketch .ino per perangkat.
     */
    public function panduan(Request $request)
    {
        $user = $request->user();

        $mesin = MesinIot::query()
            ->where('tipe', '!=', 'loker')
            ->with('ruangan:id,nama,kode')
            ->orderBy('id_mesin')
            ->get();

        // Riwayat ringkas per mesin: tap absensi atau event akses ruangan.
        $riwayatTap = \App\Models\LogAbsensi::query()
            ->whereIn('id_mesin', $mesin->pluck('id_mesin'))
            ->with('user:id,nama_lengkap')
            ->latest('waktu_tap')
            ->limit(60)
            ->get()
            ->groupBy('id_mesin');
        $riwayatEvent = \App\Models\RuanganAccessEvent::query()
            ->whereIn('mesin_iot_id', $mesin->pluck('id'))
            ->with('user:id,nama_lengkap')
            ->latest('terjadi_pada')
            ->limit(60)
            ->get()
            ->groupBy('mesin_iot_id');

        $items = $mesin->map(function (MesinIot $m) use ($riwayatTap, $riwayatEvent) {
            $conn = !$m->is_active ? 'inactive' : ($m->isOnline() ? 'online' : 'offline');
            $isAbsensi = $m->tipe === 'absensi';

            $riwayat = $isAbsensi
                ? ($riwayatTap[$m->id_mesin] ?? collect())->take(8)->map(fn ($l) => [
                    'label' => $l->user?->nama_lengkap ?? 'Tanpa identitas',
                    'hasil' => $l->jenis ?: 'tap',
                    'waktu' => $l->waktu_tap?->diffForHumans(),
                ])->values()->all()
                : ($riwayatEvent[$m->id] ?? collect())->take(8)->map(fn ($e) => [
                    'label' => $e->user?->nama_lengkap ?? ucfirst($e->jenis),
                    'hasil' => $e->hasil ?: $e->jenis,
                    'waktu' => $e->terjadi_pada?->diffForHumans(),
                ])->values()->all();

            $lastTap = $isAbsensi
                ? ($riwayatTap[$m->id_mesin] ?? collect())->first()?->waktu_tap
                : ($riwayatEvent[$m->id] ?? collect())->first()?->terjadi_pada;

            return [
                'id' => $m->id,
                'id_mesin' => $m->id_mesin,
                'nama_mesin' => $m->nama_mesin ?: $m->id_mesin,
                'tipe' => (string) $m->tipe,
                'tipe_label' => $isAbsensi ? 'Absensi' : 'Pintu',
                'ruangan' => $m->ruangan?->nama,
                'is_active' => (bool) $m->is_active,
                'conn' => $conn,
                'conn_label' => match ($conn) {
                    'online' => 'Online',
                    'offline' => 'Offline',
                    default => 'Nonaktif',
                },
                'last_seen_label' => $m->last_seen_at?->diffForHumans() ?? 'Belum heartbeat',
                'last_seen_exact' => $m->last_seen_at?->translatedFormat('d M Y, H:i'),
                'last_tap_label' => $lastTap?->diffForHumans() ?? 'Belum ada aktivitas',
                'sketch_url' => route('admin.mesin.sketch', $m),
                'conn_url' => route('admin.mesin.conn', $m),
                'riwayat' => $riwayat,
            ];
        })->values()->all();

        Inertia::setRootView('app-admin');

        return Inertia::render('PanduanMesin', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'api_base' => rtrim(url('/api/mesin'), '/'),
            'threshold_minutes' => MesinIot::ONLINE_THRESHOLD_MINUTES,
            'mesin' => $items,
            'stats' => [
                'total' => count($items),
                'online' => count(array_filter($items, fn ($m) => $m['conn'] === 'online')),
                'offline' => count(array_filter($items, fn ($m) => $m['conn'] === 'offline')),
            ],
            'urls' => [
                'mesin' => route('admin.mesin.index'),
                'monitoring' => route('admin.monitoring'),
            ],
        ]);
    }

    /** JSON ringan — tombol "Uji Koneksi" mengecek heartbeat terakhir mesin. */
    public function conn(MesinIot $mesin)
    {
        $m = $mesin->fresh();
        $conn = !$m->is_active ? 'inactive' : ($m->isOnline() ? 'online' : 'offline');

        return response()->json([
            'ok' => true,
            'conn' => $conn,
            'last_seen_label' => $m->last_seen_at?->diffForHumans() ?? 'Belum heartbeat',
            'last_seen_exact' => $m->last_seen_at?->translatedFormat('d M Y, H:i:s'),
        ]);
    }

    /** Unduh sketch .ino siap-flash — id_mesin & base URL terisi, API key placeholder. */
    public function sketch(MesinIot $mesin)
    {
        $ino = \App\Support\MesinSketchBuilder::make($mesin, rtrim(url('/api/mesin'), '/'));
        $kind = $mesin->tipe === 'absensi' ? 'absensi' : 'pintu';
        $filename = 'smartbrail_' . Str::lower($mesin->id_mesin) . '_' . $kind . '.ino';

        return response($ino, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'id_mesin' => Str::upper(trim((string) $request->input('id_mesin', ''))),
            'nama_mesin' => trim((string) $request->input('nama_mesin', '')),
        ]);

        $validated = $request->validate([
            'id_mesin' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', 'unique:mesin_iot,id_mesin'],
            'nama_mesin' => ['nullable', 'string', 'max:150'],
            'tipe' => ['required', 'string', 'max:50'],
            'tipe_custom' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'ruangan_id' => ['nullable', 'exists:ruangan,id'],
        ], [
            'id_mesin.regex' => 'ID mesin hanya boleh huruf besar, angka, strip (-), atau underscore (_).',
        ]);

        $plainKey = Str::random(40);
        $type = $this->resolveRequestedType($request);
        if ($type === 'loker') {
            return back()->with('error', 'Tipe loker tidak lagi bagian dari produk absensi.')->withInput();
        }
        $namaMesin = $validated['nama_mesin'] !== ''
            ? $validated['nama_mesin']
            : $validated['id_mesin'];

        MesinIot::query()->create([
            'id_mesin' => $validated['id_mesin'],
            'nama_mesin' => $namaMesin,
            'tipe' => $type,
            'ruangan_id' => $validated['ruangan_id'] ?? null,
            'api_key' => Hash::make($plainKey),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AdminAudit::catat('mesin.buat', $validated['id_mesin'], "tipe: {$type}");

        return redirect()->route('admin.mesin.index')
            ->with('mesin_plain_key', $plainKey)
            ->with('mesin_plain_id', $validated['id_mesin'])
            ->with('mesin_plain_name', $namaMesin)
            ->with('mesin_plain_tipe', $type)
            ->with('mesin_setup_mode', 'create')
            ->with('success', "Mesin {$validated['id_mesin']} berhasil ditambahkan.");
    }

    public function update(Request $request, MesinIot $mesin)
    {
        if ($mesin->tipe === 'loker') {
            abort(404);
        }

        $request->validate([
            'nama_mesin' => ['nullable', 'string', 'max:150'],
            'tipe' => ['required', 'string', 'max:50'],
            'tipe_custom' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'ruangan_id' => ['nullable', 'exists:ruangan,id'],
        ]);

        $type = $this->resolveRequestedType($request);
        $payload = [
            'nama_mesin' => $request->input('nama_mesin') ?: $mesin->id_mesin,
            'tipe' => $type,
            'is_active' => $request->boolean('is_active'),
        ];
        // Binding ruangan hanya boleh diubah admin (sama seperti bindMesin).
        if ($request->user()?->isAdmin() && $request->has('ruangan_id')) {
            $payload['ruangan_id'] = $request->input('ruangan_id');
        }
        if ($type === 'loker') {
            return back()->with('error', 'Tipe loker tidak lagi bagian dari produk absensi.');
        }

        $mesin->update($payload);
        AdminAudit::catat('mesin.ubah', $mesin->id_mesin);

        return back()->with('success', "Mesin {$mesin->id_mesin} berhasil diperbarui.");
    }

    public function toggleActive(MesinIot $mesin)
    {
        $mesin->update(['is_active' => !$mesin->is_active]);

        $status = $mesin->is_active ? 'diaktifkan' : 'dinonaktifkan';
        AdminAudit::catat('mesin.toggle', $mesin->id_mesin, $status);
        return back()->with('success', "Mesin {$mesin->id_mesin} berhasil {$status}.");
    }

    public function regenerate(MesinIot $mesin)
    {
        $plainKey = Str::random(40);

        $mesin->update([
            'api_key' => Hash::make($plainKey),
        ]);
        AdminAudit::catat('mesin.regenerate', $mesin->id_mesin, 'API key di-generate ulang');

        return redirect()->route('admin.mesin.index')
            ->with('mesin_plain_key', $plainKey)
            ->with('mesin_plain_id', $mesin->id_mesin)
            ->with('mesin_plain_name', $mesin->nama_mesin ?: $mesin->id_mesin)
            ->with('mesin_plain_tipe', $mesin->tipe)
            ->with('mesin_setup_mode', 'regenerate')
            ->with('success', "API key mesin {$mesin->id_mesin} berhasil di-generate ulang.");
    }

    public function destroy(MesinIot $mesin)
    {
        $idMesin = $mesin->id_mesin;
        $mesin->delete();
        AdminAudit::catat('mesin.hapus', $idMesin);

        return back()->with('success', "Mesin {$idMesin} berhasil dihapus.");
    }

    private function typeOptions(): array
    {
        $base = [
            'absensi' => 'Absensi',
        ];

        $existing = MesinIot::query()
            ->select('tipe')
            ->whereNotNull('tipe')
            ->distinct()
            ->pluck('tipe')
            ->filter()
            ->mapWithKeys(fn ($type) => [$type => Str::headline(str_replace(['-', '_'], ' ', $type))])
            ->all();

        return $base + $existing;
    }

    private function resolveRequestedType(Request $request): string
    {
        $type = (string) $request->input('tipe', 'absensi');
        if ($type === '__custom') {
            $type = (string) $request->input('tipe_custom', '');
        }

        return $this->normalizeType($type);
    }

    private function normalizeType(string $type): string
    {
        $normalized = Str::of($type)
            ->lower()
            ->replace([' ', '.'], '_')
            ->replaceMatches('/[^a-z0-9_-]/', '')
            ->trim('_-')
            ->toString();

        return $normalized !== '' ? $normalized : 'absensi';
    }

}
