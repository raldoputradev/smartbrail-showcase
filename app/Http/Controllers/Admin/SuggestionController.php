<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobilePushToken;
use App\Models\Suggestion;
use App\Support\MobilePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SuggestionController extends Controller
{
    use \App\Http\Controllers\Concerns\AdminShellProps;

    public function index(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();

        $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 50], true)
            ? (int) $request->query('per_page', 10) : 10;
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'source' => (string) $request->query('source', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'per_page' => $perPage,
        ];

        $paginator = $this->filteredQuery($request)->with([
            'user.programStudi:id,nama',
            'repliedBy:id,nama_lengkap',
        ])->latest()->paginate($perPage)->withQueryString();

        // Auto-tandai "baru" → "dibaca" untuk item yang dipilih — perilaku lama dipertahankan.
        $selId = $request->filled('id') ? (int) $request->id : (int) ($paginator->firstItem() ? $paginator->items()[0]->id : 0);
        $selected = Suggestion::query()
            ->with(['user:id,nama_lengkap,role,nitk_nidn,email,profile_photo_path', 'repliedBy:id,nama_lengkap'])
            ->find($selId);
        if ($selected && $selected->status === 'baru') {
            $selected->update(['status' => 'dibaca']);
            $selected->refresh();
        }

        $sourceLabel = ['public' => 'Landing Publik', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'];
        $rows = $paginator->getCollection()->map(function (Suggestion $s) use ($sourceLabel, $selected) {
            $pelapor = $s->user?->nama_lengkap ?? $s->nama ?? 'Anonim';
            return [
                'id' => $s->id,
                'kode' => 'LPR-' . str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
                'pelapor' => $pelapor,
                'pelapor_role' => $s->user ? ucfirst((string) $s->user->role) : $sourceLabel[$s->source] ?? 'Publik',
                'avatar' => $s->user?->profile_photo_url,
                'judul' => $s->subjek ?: 'Tanpa subjek',
                'ringkasan' => Str::limit(strip_tags((string) $s->pesan), 60),
                'kategori' => $s->sourceLabel(),
                'tanggal' => $s->created_at?->translatedFormat('d M Y, H:i'),
                'status' => $s->status === 'baru' && $s->id === $selected?->id ? 'dibaca' : $s->status,
                'baru' => $s->status === 'baru' && $s->id !== $selected?->id,
            ];
        })->values()->all();

        $now = now();
        $bulanIni = Suggestion::whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count();
        $bulanLalu = Suggestion::whereBetween('created_at', [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()])->count();
        $stats = [
            'total' => Suggestion::count(),
            'baru' => Suggestion::where('status', 'baru')->count(),
            'diproses' => Suggestion::where('status', 'dibaca')->count(),
            'ditanggapi' => Suggestion::where('status', 'ditanggapi')->count(),
            'bulan_ini' => $bulanIni,
            'delta_vs_bulan_lalu' => $bulanIni - $bulanLalu,
        ];

        // Tab Statistik & Analisis — agregat jujur dari data.
        $perSumber = Suggestion::query()->selectRaw('source, COUNT(*) as n')->groupBy('source')->pluck('n', 'source');
        $perStatus = Suggestion::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $tren = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $tren['labels'][] = $m->translatedFormat('M');
            $tren['values'][] = Suggestion::whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])->count();
        }
        // Rata-rata waktu respons — dihitung di PHP agar kompatibel semua driver.
        $respon = Suggestion::query()->whereNotNull('admin_replied_at')
            ->get(['created_at', 'admin_replied_at'])
            ->map(fn ($s) => $s->created_at && $s->admin_replied_at
                ? $s->created_at->diffInMinutes($s->admin_replied_at, false) / 60 : null)
            ->filter(fn ($v) => $v !== null && $v >= 0)
            ->avg();

        return \Inertia\Inertia::render('SaranMasukan', [
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
            'selected' => $selected ? [
                'id' => $selected->id,
                'kode' => 'LPR-' . str_pad((string) $selected->id, 4, '0', STR_PAD_LEFT),
                'pelapor' => $selected->user?->nama_lengkap ?? $selected->nama ?? 'Anonim',
                'pelapor_role' => $selected->user ? ucfirst((string) $selected->user->role) : $sourceLabel[$selected->source] ?? 'Publik',
                'pelapor_email' => $selected->user?->email ?? $selected->email,
                'avatar' => $selected->user?->profile_photo_url,
                'tanggal' => $selected->created_at?->translatedFormat('d M Y, H:i'),
                'status' => $selected->status,
                'judul' => $selected->subjek ?: 'Tanpa subjek',
                'isi' => $selected->pesan,
                'balasan' => $selected->admin_reply,
                'lampiran_url' => $selected->lampiran ? route('admin.suggestions.lampiran', $selected->id) : null,
                'dibalas_oleh' => $selected->repliedBy?->nama_lengkap,
                'dibalas_pada' => $selected->admin_replied_at?->translatedFormat('d M Y, H:i'),
                'user_baca_balasan' => (bool) $selected->user_read_reply_at,
            ] : null,
            'analisis' => [
                'per_sumber' => collect($sourceLabel)->map(fn ($l, $k) => [
                    'label' => $l, 'n' => (int) ($perSumber[$k] ?? 0),
                ])->values()->all(),
                'per_status' => [
                    ['label' => 'Baru', 'n' => (int) ($perStatus['baru'] ?? 0)],
                    ['label' => 'Diproses', 'n' => (int) ($perStatus['dibaca'] ?? 0)],
                    ['label' => 'Ditanggapi', 'n' => (int) ($perStatus['ditanggapi'] ?? 0)],
                ],
                'tren' => $tren,
                'respon_jam' => $respon !== null && $respon !== false ? round((float) $respon, 1) : null,
            ],
            'can' => [
                'update' => \App\Support\PermissionRegistry::canCurrent('suggestions', 'update'),
                'delete' => \App\Support\PermissionRegistry::canCurrent('suggestions', 'delete'),
            ],
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'saran')
                    : route('admin.suggestions.index'),
                'base' => route('admin.suggestions.index') . '/',
            ],
        ]);
    }

    /**
     * JSON feed untuk refresh antrian tanpa reload halaman.
     */
    public function feed(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request);
        $items = (clone $query)->latest()->limit(30)->get();

        $counts = [
            'baru'       => Suggestion::where('status', 'baru')->count(),
            'dibaca'     => Suggestion::where('status', 'dibaca')->count(),
            'ditanggapi' => Suggestion::where('status', 'ditanggapi')->count(),
            'total'      => Suggestion::count(),
        ];

        $statusColors = ['baru' => '#f43f5e', 'dibaca' => '#f59e0b', 'ditanggapi' => '#14b8a6'];

        $payload = $items->map(function (Suggestion $row) use ($statusColors) {
            $name = $row->nama;
            $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';

            return [
                'id'            => $row->id,
                'nama'          => $name,
                'subjek'        => $row->subjek ?: 'Tanpa subjek',
                'preview'       => Str::limit(strip_tags((string) $row->pesan), 72),
                'status'        => $row->status,
                'status_color'  => $statusColors[$row->status] ?? '#64748b',
                'is_unread'     => $row->status === 'baru',
                'created_human' => $row->created_at?->translatedFormat('d M H:i') ?? '',
                'avatar_url'    => $row->user?->profile_photo_url,
                'initial'       => $initial,
            ];
        });

        $fingerprint = md5(
            $items->pluck('id')->implode(',')
            . '|' . $counts['baru'] . '|' . $counts['total']
        );

        return response()->json([
            'ok'          => true,
            'counts'      => $counts,
            'items'       => $payload->values(),
            'fingerprint' => $fingerprint,
            'selected_id' => $request->filled('id') ? (int) $request->id : null,
        ]);
    }

    /** Foto bukti laporan — hanya role berhak view suggestions (gate di route). */
    public function lampiran(Suggestion $suggestion)
    {
        abort_unless($suggestion->lampiran, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($suggestion->lampiran), 404);
        return $disk->response($suggestion->lampiran);
    }

    private function filteredQuery(Request $request)
    {
        $query = Suggestion::query()->with([
            'user:id,nama_lengkap,role,nitk_nidn,program_studi_id,email,profile_photo_path',
        ]);

        if ($request->filled('status') && in_array($request->status, ['baru', 'dibaca', 'ditanggapi'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source') && in_array($request->source, ['public', 'dosen', 'mahasiswa'], true)) {
            $query->where('source', $request->source);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('from', ''))) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('to', ''))) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        if ($request->filled('q')) {
            $kw = trim((string) $request->q);
            $query->where(function ($qb) use ($kw) {
                $qb->where('nama', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%")
                    ->orWhere('subjek', 'like', "%{$kw}%")
                    ->orWhere('pesan', 'like', "%{$kw}%")
                    ->orWhere('admin_reply', 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    public function updateStatus(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:baru,dibaca,ditanggapi'],
        ]);

        $suggestion->update(['status' => $validated['status']]);

        return $this->redirectToInbox($request, $suggestion->id)
            ->with('success', 'Status saran diperbarui.');
    }

    public function reply(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $validated = $request->validate([
            'admin_reply' => ['required', 'string', 'min:2', 'max:4000'],
            'mode' => ['nullable', 'in:create,edit'],
        ], [
            'admin_reply.required' => 'Balasan admin wajib diisi.',
        ]);

        $hadReply = filled($suggestion->admin_reply);
        $mode = $validated['mode'] ?? ($hadReply ? 'edit' : 'create');

        // Sudah ada balasan → hanya boleh lewat mode edit (bukan kirim ulang sembarangan).
        if ($hadReply && $mode !== 'edit') {
            return $this->redirectToInbox($request, $suggestion->id, editReply: true)
                ->with('success', 'Tiket ini sudah dibalas. Edit atau hapus balasan dulu sebelum mengirim ulang.');
        }

        $newText = $validated['admin_reply'];
        $textChanged = !$hadReply || trim((string) $suggestion->admin_reply) !== trim($newText);

        $suggestion->update([
            'admin_reply' => $newText,
            'admin_replied_at' => now(),
            'admin_replied_by' => auth()->id(),
            'user_read_reply_at' => $textChanged ? null : $suggestion->user_read_reply_at,
            'status' => 'ditanggapi',
        ]);

        // Push hanya saat kirim pertama, atau edit dengan teks yang benar-benar berubah.
        if (!$hadReply || $textChanged) {
            $this->pushSuggestionReply($suggestion, $newText);
            if ($suggestion->user_id) {
                try {
                    broadcast(new \App\Events\NotifikasiBaru('balasan_saran', [$suggestion->user_id]));
                } catch (\Throwable $e) {
                    // Broadcast opsional.
                }
            }
        }

        $msg = $hadReply
            ? ($textChanged ? 'Balasan diperbarui.' : 'Tidak ada perubahan teks.')
            : 'Balasan terkirim. Pengirim akan melihat notifikasi di halaman Saran & Masukan.';

        return $this->redirectToInbox($request, $suggestion->id)
            ->with('success', $msg);
    }

    public function clearReply(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $suggestion->update([
            'admin_reply' => null,
            'admin_replied_at' => null,
            'admin_replied_by' => null,
            'user_read_reply_at' => null,
            'status' => $suggestion->status === 'ditanggapi' ? 'dibaca' : $suggestion->status,
        ]);

        return $this->redirectToInbox($request, $suggestion->id)
            ->with('success', 'Balasan dihapus. Kirim balasan baru jika perlu.');
    }

    public function destroy(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $suggestion->delete();

        return $this->redirectToInbox($request)
            ->with('success', 'Saran dihapus.');
    }

    private function redirectToInbox(Request $request, ?int $selectedId = null, bool $editReply = false): RedirectResponse
    {
        $params = array_filter([
            'q'          => $request->query('q'),
            'status'     => $request->query('status'),
            'source'     => $request->query('source'),
            'id'         => $selectedId ?? ($request->query('id') ? (int) $request->query('id') : null),
            'edit_reply' => $editReply ? 1 : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('admin.suggestions.index', $params);
    }

    private function pushSuggestionReply(Suggestion $suggestion, string $reply): void
    {
        $push = app(MobilePushService::class);
        if (! $push->isConfigured() || ! $suggestion->user_id) {
            return;
        }

        $tokens = MobilePushToken::query()
            ->where('user_id', $suggestion->user_id)
            ->pluck('token')
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $push->sendToTokens(
            $tokens,
            'Balasan Admin Laboran',
            Str::limit('Re: ' . ($suggestion->subjek ?: 'Laporan Anda') . ' — "' . trim(strip_tags($reply)) . '"', 120),
            [
                'type' => 'suggestion_reply',
                'suggestion_id' => (string) $suggestion->id,
            ]
        );
    }
}
