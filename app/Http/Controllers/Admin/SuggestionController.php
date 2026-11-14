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
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request)->with([
            'user.programStudi:id,nama',
            'repliedBy:id,nama_lengkap',
        ]);

        $items = $query->latest()->paginate(30)->withQueryString();

        $selected = null;
        if ($request->filled('id')) {
            $selected = Suggestion::query()
                ->with([
                    'user:id,nama_lengkap,role,nitk_nidn,program_studi_id,email,profile_photo_path',
                    'user.programStudi:id,nama',
                    'repliedBy:id,nama_lengkap',
                ])
                ->find((int) $request->id);
        }
        if (! $selected && $items->isNotEmpty()) {
            $selected = $items->first();
        }

        if ($selected && $selected->status === 'baru') {
            $selected->update(['status' => 'dibaca']);
            $selected->refresh();
        }

        $counts = [
            'baru'       => Suggestion::where('status', 'baru')->count(),
            'dibaca'     => Suggestion::where('status', 'dibaca')->count(),
            'ditanggapi' => Suggestion::where('status', 'ditanggapi')->count(),
            'total'      => Suggestion::count(),
        ];

        return view('admin.suggestions', compact('items', 'counts', 'selected'));
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
            'Balasan admin atas saran Anda',
            Str::limit(strip_tags($reply), 120),
            [
                'type' => 'suggestion_reply',
                'suggestion_id' => (string) $suggestion->id,
            ]
        );
    }
}
