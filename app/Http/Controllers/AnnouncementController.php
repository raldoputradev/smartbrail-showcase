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
    public function index(Request $request): View
    {
        $query = Announcement::query()
            ->pengumumanSaja()
            ->with('author:id,nama_lengkap,role');

        if ($request->filled('q')) {
            $kw = trim((string) $request->q);
            $query->where(function ($qb) use ($kw) {
                $qb->where('judul', 'like', "%{$kw}%")
                    ->orWhere('isi', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('target') && in_array($request->target, ['semua', 'dosen', 'mahasiswa'], true)) {
            $query->where('target', $request->target);
        }

        if ($request->filled('prioritas') && in_array($request->prioritas, ['normal', 'penting', 'urgent'], true)) {
            $query->where('prioritas', $request->prioritas);
        }

        $items = $query->latest()->paginate(30)->withQueryString();

        $this->markVisibleAsRead($items->pluck('id'), $request->user());

        $selected = null;
        $composerMode = 'create';

        if ($request->boolean('new')) {
            $composerMode = 'create';
        } elseif ($request->filled('id')) {
            $selected = Announcement::query()
                ->pengumumanSaja()
                ->with('author:id,nama_lengkap,role')
                ->find((int) $request->id);
            $composerMode = $selected ? 'edit' : 'create';
        }

        $now = now();
        $counts = [
            'total'      => Announcement::query()->pengumumanSaja()->count(),
            'aktif'      => Announcement::query()
                ->pengumumanSaja()
                ->where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                })
                ->count(),
            'draft'      => Announcement::query()->pengumumanSaja()->where('is_active', false)->count(),
            'kadaluarsa' => Announcement::query()
                ->pengumumanSaja()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->count(),
        ];

        return view('admin.announcements', compact('items', 'selected', 'composerMode', 'counts'));
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
