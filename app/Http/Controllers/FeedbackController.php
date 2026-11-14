<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Support\SuggestionSubmissionLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View|RedirectResponse
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
            ->with('repliedBy:id,nama_lengkap')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $unreadReplies = Suggestion::query()
            ->where('user_id', $user->id)
            ->whereNotNull('admin_replied_at')
            ->whereNull('user_read_reply_at')
            ->count();

        $view = $user->role === 'mahasiswa' ? 'feedback.mahasiswa' : 'feedback.dosen';

        return view($view, [
            'items' => $items,
            'unreadReplies' => $unreadReplies,
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
