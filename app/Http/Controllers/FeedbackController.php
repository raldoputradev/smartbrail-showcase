<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AdminShellProps;
use App\Models\Suggestion;
use App\Support\SuggestionSubmissionLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    use AdminShellProps;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['dosen', 'mahasiswa'], true), 403);
        if ($user->role === 'mahasiswa') {
            return redirect()
                ->route('mahasiswa.gate')
                ->with('info', 'Saran & masukan mahasiswa dikirim lewat aplikasi mobile SIMALAS.');
        }

        $items = Suggestion::query()
            ->where('user_id', $user->id)
            ->with('repliedBy:id,nama_lengkap,role')
            ->latest()
            ->limit(100)
            ->get();

        $rows = $items->map(fn (Suggestion $s) => [
            'id' => $s->id,
            'tiket' => 'SM-'.$s->created_at->format('Y').'-'.str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
            'subjek' => $s->subjek ?: 'Saran & masukan',
            'pesan' => $s->pesan,
            'status' => $s->status ?: 'baru',
            'status_label' => match ($s->status) {
                'ditanggapi' => 'Ditanggapi',
                'dibaca' => 'Dalam Proses',
                default => 'Baru',
            },
            'tanggal' => optional($s->created_at)?->translatedFormat('d M Y, H:i'),
            'admin_reply' => $s->admin_reply,
            'admin_replied_by' => $s->repliedBy?->nama_lengkap,
            'admin_replied_role' => $s->repliedBy?->role,
            'admin_replied_at' => optional($s->admin_replied_at)?->translatedFormat('d M Y, H:i'),
            'unread_reply' => $s->admin_replied_at && ! $s->user_read_reply_at,
        ])->values();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('DosenSaran', [
            'menu' => $this->dosenSidebarMenu($user),
            'notif' => $this->dosenNotifications($user),
            'items' => $rows,
            'stats' => [
                'semua' => $rows->count(),
                'proses' => $rows->whereIn('status', ['baru', 'dibaca'])->count(),
                'ditanggapi' => $rows->where('status', 'ditanggapi')->count(),
                'belum_dibaca' => $rows->where('unread_reply', true)->count(),
            ],
            'store_url' => route('dosen.feedback.store'),
            'reply_url' => url('/dosen/saran/0/balas'),
            'read_url' => url('/dosen/saran/0/baca'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['dosen', 'mahasiswa'], true), 403);
        if ($user->role === 'mahasiswa') {
            return redirect()
                ->route('mahasiswa.gate')
                ->with('info', 'Saran & masukan mahasiswa dikirim lewat aplikasi mobile SIMALAS.');
        }

        $validated = $request->validate([
            'subjek' => ['nullable', 'string', 'max:180'],
            'pesan'  => ['required', 'string', 'min:10', 'max:4000'],
        ], [
            'pesan.required' => 'Pesan wajib diisi.',
            'pesan.min'      => 'Pesan terlalu singkat (minimal 10 karakter).',
        ]);

        if ($limitMsg = SuggestionSubmissionLimiter::checkAccount($user->id, $validated['pesan'])) {
            return redirect()
                ->route('dosen.feedback.index')
                ->with('error', $limitMsg);
        }

        SuggestionSubmissionLimiter::recordAccountHit($user->id);

        $row = Suggestion::query()->create([
            'user_id'    => $user->id,
            'source'     => $user->role,
            'nama'       => $user->nama_lengkap,
            'email'      => $user->email,
            'subjek'     => $validated['subjek'] ?? null,
            'pesan'      => $validated['pesan'],
            'status'     => 'baru',
            'ip'         => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()
            ->route('dosen.feedback.index')
            ->withFragment('saran-' . $row->id)
            ->with('success', 'Saran Anda terkirim. Riwayat ada di bawah — admin dapat membalas di sini.');
    }

    /**
     * Dosen membalas tanggapan admin — dibuat sebagai saran lanjutan "Re: …".
     */
    public function replySuggestion(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($suggestion->user_id === $user->id, 403);

        $validated = $request->validate([
            'pesan' => ['required', 'string', 'min:10', 'max:4000'],
        ], ['pesan.required' => 'Tanggapan wajib diisi.', 'pesan.min' => 'Tanggapan terlalu singkat.']);

        Suggestion::query()->create([
            'user_id' => $user->id,
            'source' => $user->role,
            'nama' => $user->nama_lengkap,
            'email' => $user->email,
            'subjek' => 'Re: '.($suggestion->subjek ?: 'Saran & masukan'),
            'pesan' => $validated['pesan'],
            'status' => 'baru',
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        if ($suggestion->admin_replied_at && ! $suggestion->user_read_reply_at) {
            $suggestion->update(['user_read_reply_at' => now()]);
        }

        return back()->with('success', 'Tanggapan Anda terkirim ke admin.');
    }

    public function markReplyRead(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($suggestion->user_id === $user->id, 403);

        if ($suggestion->admin_replied_at && !$suggestion->user_read_reply_at) {
            $suggestion->update(['user_read_reply_at' => now()]);
        }

        return back()->withFragment('saran-' . $suggestion->id);
    }
}
