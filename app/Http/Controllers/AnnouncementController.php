<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    use Concerns\AdminShellProps;

    public function index(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();
        $now = now();

        $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 50], true)
            ? (int) $request->query('per_page', 10) : 10;
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'target' => (string) $request->query('target', ''),
            'prioritas' => (string) $request->query('prioritas', ''),
            'status' => (string) $request->query('status', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'per_page' => $perPage,
        ];

        $query = Announcement::query()->pengumumanSaja()->with('author:id,nama_lengkap,role');

        if ($filters['q'] !== '') {
            $kw = $filters['q'];
            $query->where(function ($qb) use ($kw) {
                $qb->where('judul', 'like', "%{$kw}%")
                    ->orWhere('isi', 'like', "%{$kw}%");
            });
        }
        if (in_array($filters['target'], ['semua', 'dosen', 'mahasiswa'], true)) {
            $query->where('target', $filters['target']);
        }
        if (in_array($filters['prioritas'], ['normal', 'penting', 'urgent'], true)) {
            $query->where('prioritas', $filters['prioritas']);
        }
        if ($filters['status'] === 'aktif') {
            $query->where('is_active', true)
                ->where(fn ($qb) => $qb->whereNull('expires_at')->orWhere('expires_at', '>', $now));
        } elseif ($filters['status'] === 'draft') {
            $query->where('is_active', false);
        } elseif ($filters['status'] === 'kadaluarsa') {
            $query->where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<=', $now);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $paginator = $query->latest()->paginate($perPage)->withQueryString();
        $this->markVisibleAsRead($paginator->getCollection()->pluck('id'), $user);

        $targetLabel = ['semua' => 'Semua Pengguna', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'];
        $rows = $paginator->getCollection()->map(function (Announcement $a) use ($now, $targetLabel) {
            $expired = $a->expires_at && $a->expires_at->lte($now);
            $status = ! $a->is_active ? 'draft' : ($expired ? 'kadaluarsa' : 'aktif');
            return [
                'id' => $a->id,
                'judul' => $a->judul,
                'isi' => $a->isi,
                'preview' => \Illuminate\Support\Str::limit(strip_tags((string) $a->isi), 80),
                'target' => $a->target,
                'target_label' => $targetLabel[$a->target] ?? ucfirst((string) $a->target),
                'prioritas' => $a->prioritas,
                'status' => $status,
                'author' => $a->author?->nama_lengkap ?? 'Admin',
                'created' => $a->created_at?->translatedFormat('d M Y, H:i'),
                'expires' => $a->expires_at?->translatedFormat('d M Y, H:i'),
                'expires_input' => $a->expires_at?->format('Y-m-d\TH:i'),
            ];
        })->values()->all();

        $bulanIni = Announcement::query()->pengumumanSaja()
            ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count();
        $bulanLalu = Announcement::query()->pengumumanSaja()
            ->whereBetween('created_at', [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()])->count();

        $stats = [
            'total' => Announcement::query()->pengumumanSaja()->count(),
            'aktif' => Announcement::query()->pengumumanSaja()->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->count(),
            'draft' => Announcement::query()->pengumumanSaja()->where('is_active', false)->count(),
            'kadaluarsa' => Announcement::query()->pengumumanSaja()
                ->where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<=', $now)->count(),
            'bulan_ini' => $bulanIni,
            'delta_vs_bulan_lalu' => $bulanIni - $bulanLalu,
        ];
        $stats['aktif_pct'] = $stats['total'] > 0 ? round($stats['aktif'] / $stats['total'] * 100) : 0;
        $stats['kadaluarsa_pct'] = $stats['total'] > 0 ? round($stats['kadaluarsa'] / $stats['total'] * 100) : 0;

        // Tab arsip: draft + kadaluarsa (item yang tidak lagi tayang).
        $arsip = Announcement::query()->pengumumanSaja()->with('author:id,nama_lengkap')
            ->where(function ($qb) use ($now) {
                $qb->where('is_active', false)
                    ->orWhere(fn ($q2) => $q2->whereNotNull('expires_at')->where('expires_at', '<=', $now));
            })
            ->latest()->limit(12)->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'judul' => $a->judul,
                'target_label' => $targetLabel[$a->target] ?? ucfirst((string) $a->target),
                'status' => $a->is_active ? 'kadaluarsa' : 'draft',
                'created' => $a->created_at?->translatedFormat('d M Y'),
                'expires' => $a->expires_at?->translatedFormat('d M Y, H:i'),
                'author' => $a->author?->nama_lengkap ?? 'Admin',
            ])->values()->all();

        return \Inertia\Inertia::render('Pengumuman', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'filters' => $filters,
            'stats' => $stats,
            'items' => [
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
            'arsip' => $arsip,
            'can' => [
                'create' => \App\Support\PermissionRegistry::canCurrent('announcements', 'create'),
                'update' => \App\Support\PermissionRegistry::canCurrent('announcements', 'update'),
                'delete' => \App\Support\PermissionRegistry::canCurrent('announcements', 'delete'),
            ],
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'pengumuman')
                    : route('admin.announcements.index'),
                'store' => route('admin.announcements.store'),
                'base' => route('admin.announcements.index') . '/',
            ],
        ]);
    }

    private function markVisibleAsRead($announcementIds, $user): void
    {
        if (! $user || empty($announcementIds) || count($announcementIds) === 0) {
            return;
        }
        $now = Carbon::now();
        $rows = collect($announcementIds)->map(fn ($id) => [
            'announcement_id' => $id,
            'user_id'         => $user->id,
            'read_at'         => $now,
        ])->all();
        AnnouncementRead::query()->upsert($rows, ['announcement_id', 'user_id'], ['read_at']);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('expires_at') === '') {
            $request->merge(['expires_at' => null]);
        }

        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'isi' => ['required', 'string'],
            'target' => ['required', 'in:semua,dosen,mahasiswa'],
            'prioritas' => ['required', 'in:normal,penting,urgent'],
            'expires_at' => ['nullable', 'date'],
            'action' => ['nullable', 'in:draft,publish'],
        ]);

        $publish = ($validated['action'] ?? 'publish') === 'publish';

        $row = Announcement::create([
            'judul' => $validated['judul'],
            'isi' => $validated['isi'],
            'target' => $validated['target'],
            'channel' => Announcement::CHANNEL_PENGUMUMAN,
            'prioritas' => $validated['prioritas'],
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $publish,
            'dibuat_oleh' => auth()->id(),
        ]);

        // Bangunkan semua klien yang tersambung — lonceng menyegarkan diri.
        if ($publish) {
            try {
                broadcast(new \App\Events\NotifikasiBaru('pengumuman', broadcastSemua: true, broadcastStaff: true));
            } catch (\Throwable $e) {
                // Broadcast opsional — kegagalan Reverb tidak menggagalkan simpan.
            }
        }

        return $this->redirectToComposer($request, $row->id)
            ->with('success', $publish ? 'Pengumuman dipublikasikan.' : 'Draft pengumuman disimpan.');
    }

    public function showJson(Announcement $announcement): \Illuminate\Http\JsonResponse
    {
        abort_if($announcement->isLockerNotification(), 404);

        $announcement->load('author:id,nama_lengkap');

        $query = http_build_query(array_filter(request()->only(['q', 'target', 'prioritas', 'page']), fn ($v) => $v !== null && $v !== ''));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $announcement->id,
                'judul' => $announcement->judul,
                'isi' => $announcement->isi,
                'target' => $announcement->target,
                'prioritas' => $announcement->prioritas,
                'is_active' => (bool) $announcement->is_active,
                'expires_at' => $announcement->expires_at?->format('Y-m-d\TH:i'),
                'created_label' => $announcement->created_at?->translatedFormat('d M Y, H:i'),
                'author' => $announcement->author->nama_lengkap ?? 'Admin',
                'expires_label' => $announcement->expires_at?->translatedFormat('d M Y H:i'),
                'update_url' => route('admin.announcements.update', $announcement) . ($query ? '?' . $query . '&id=' . $announcement->id : '?id=' . $announcement->id),
                'toggle_url' => route('admin.announcements.toggle', $announcement) . ($query ? '?' . $query . '&id=' . $announcement->id : '?id=' . $announcement->id),
                'destroy_url' => route('admin.announcements.destroy', $announcement) . ($query ? '?' . $query . '&id=' . $announcement->id : '?id=' . $announcement->id),
            ],
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->isLockerNotification(), 404);

        if ($request->input('expires_at') === '') {
            $request->merge(['expires_at' => null]);
        }

        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'isi' => ['required', 'string'],
            'target' => ['required', 'in:semua,dosen,mahasiswa'],
            'prioritas' => ['required', 'in:normal,penting,urgent'],
            'expires_at' => ['nullable', 'date'],
            'action' => ['nullable', 'in:draft,publish'],
        ]);

        $publish = ($validated['action'] ?? 'publish') === 'publish';

        $announcement->update([
            'judul' => $validated['judul'],
            'isi' => $validated['isi'],
            'target' => $validated['target'],
            'prioritas' => $validated['prioritas'],
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $publish,
        ]);

        return $this->redirectToComposer($request, $announcement->id)
            ->with('success', $publish ? 'Pengumuman diperbarui & dipublikasikan.' : 'Draft disimpan.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->isLockerNotification(), 404);

        $announcement->delete();

        return $this->redirectToComposer($request)
            ->with('success', 'Pengumuman dihapus.');
    }

    public function toggle(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->isLockerNotification(), 404);

        $announcement->update(['is_active' => ! $announcement->is_active]);

        return $this->redirectToComposer($request, $announcement->id)
            ->with('success', 'Status pengumuman diperbarui.');
    }

    private function redirectToComposer(Request $request, ?int $selectedId = null): RedirectResponse
    {
        $params = array_filter([
            'q'         => $request->query('q'),
            'target'    => $request->query('target'),
            'prioritas' => $request->query('prioritas'),
            'id'        => $selectedId,
            'new'       => $selectedId ? null : ($request->boolean('new') ? 1 : null),
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('admin.announcements.index', $params);
    }
}
