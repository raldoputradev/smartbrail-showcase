<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AdminAi\AdminAiActionExecutor;
use App\Services\AdminAi\AdminAiOrchestrator;
use App\Services\AdminAi\AdminAiToolRegistry;
use App\Services\AdminAi\CopilotProviderError;
use App\Services\AdminAi\CopilotStreamAborted;
use App\Services\AdminAi\CopilotTurn;
use App\Support\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAiController extends Controller
{
    use \App\Http\Controllers\Concerns\AdminShellProps;

    public function index(Request $request)
    {
        $user = $request->user();
        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'title', 'last_message_at', 'updated_at']);

        $dosen = $user->isDosen();

        // Masuk dari menu selalu percakapan baru; ?c= hanya untuk percakapan yang dibuka eksplisit (milik sendiri).
        $initial = null;
        if ($request->filled('c') && ctype_digit((string) $request->query('c'))) {
            $initial = $conversations->firstWhere('id', (int) $request->query('c'))?->id
                ?? AiConversation::query()->where('user_id', $user->id)->whereKey((int) $request->query('c'))->value('id');
        }
        $prefix = $dosen ? 'dosen' : 'admin';

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('AsistenAi', [
            'menu' => $dosen ? $this->dosenSidebarMenu($user) : $this->adminSidebarMenu($user),
            'notif' => $dosen ? $this->dosenNotifications($user) : $this->adminNotifications($user),
            'ai_enabled' => app(AdminAiOrchestrator::class)->isGeminiConfigured(),
            'greeting_name' => (string) ($user->nama_lengkap ?? ($dosen ? 'Bapak/Ibu' : 'Admin')),
            'conversations' => $conversations->map(fn ($c) => [
                'id' => $c->id,
                'title' => (string) $c->title,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ])->all(),
            'actions' => $this->aiActionCards($user),
            'examples' => $this->aiExampleQuestions($user),
            'initial_conversation' => $initial,
            'cp' => [
                'stream' => route($prefix.'.ai.chat.stream'),
                'attachment' => route($prefix.'.ai.chat.attachment'),
                'attachments_enabled' => (bool) config('services.ai.attachments', true),
                'conversations' => $request->is('dev/preview/*')
                    ? url('dev/preview/admin/ai/conversations')
                    : route($prefix.'.ai.conversations'),
                'conversation_store' => route($prefix.'.ai.conversations.store'),
                'proposal_confirm' => route($prefix.'.ai.confirm', ['proposal' => 0]),
                'proposal_cancel' => route($prefix.'.ai.cancel', ['proposal' => 0]),
                'import_page' => $dosen ? null : route('admin.users.import'),
                'role' => (string) ($user->role ?? 'admin'),
            ],
        ]);
    }

    /**
     * Dashboard pelatihan asisten mahasiswa — pertanyaan tak terjawab,
     * umpan balik per topik, dan editor file pengetahuan.
     */
    public function training(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();
        $dosen = $user->isDosen();

        [$unmatched, $feedback] = $this->trainingLogs();

        $kbPath = storage_path('app/assistant-knowledge.md');
        $knowledge = is_file($kbPath) ? (string) @file_get_contents($kbPath) : '';

        return \Inertia\Inertia::render('AiTraining', [
            'menu' => $dosen ? $this->dosenSidebarMenu($user) : $this->adminSidebarMenu($user),
            'notif' => $dosen ? $this->dosenNotifications($user) : $this->adminNotifications($user),
            'unmatched' => $unmatched,
            'feedback' => $feedback,
            'knowledge' => $knowledge,
            'can_edit' => PermissionRegistry::canCurrent('ai_assistant', 'update'),
            'urls' => [
                'index' => route('admin.ai.training'),
                'save_knowledge' => route('admin.ai.training.knowledge'),
                'delete_respons' => route('admin.ai.training.respons.delete'),
            ],
        ]);
    }

    /**
     * Hapus satu entri umpan balik dari log pelatihan (dicocokkan via waktu + teks pertanyaan).
     */
    public function deleteFeedback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'waktu' => ['required', 'string', 'max:40'],
            'q' => ['required', 'string', 'max:400'],
        ]);

        $f = storage_path('logs/assistant-feedback.log');
        if (is_file($f)) {
            $target = trim((string) $validated['q']);
            $lines = file($f, FILE_IGNORE_NEW_LINES) ?: [];
            $out = [];
            $removed = false;
            foreach ($lines as $line) {
                if (! $removed
                    && str_contains($line, '['.$validated['waktu'].']')
                    && preg_match('/Q:\s*(.*?)\s*\|\s*A:/u', $line, $m)
                    && trim($m[1]) === $target
                ) {
                    $removed = true;
                    continue;
                }
                $out[] = $line;
            }
            if ($removed) {
                file_put_contents($f, implode("\n", $out)."\n", LOCK_EX);
            }
        }

        return back()->with('success', 'Entri respons dihapus dari log pelatihan.');
    }

    public function saveKnowledge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'knowledge' => ['nullable', 'string', 'max:50000'],
        ]);
        $isi = (string) ($validated['knowledge'] ?? '');
        @file_put_contents(storage_path('app/assistant-knowledge.md'), $isi, LOCK_EX);
        return back()->with('success', 'File pengetahuan asisten disimpan — berlaku langsung untuk pertanyaan berikutnya.');
    }

    /**
     * Parse dua log pelatihan lokal.
     *
     * @return array{0: array<int,array{q:string,n:int,terakhir:string}>, 1: array{ringkasan:array<string,int>,per_topik:array<int,array{topik:string,bad:int,ok:int,great:int}>,bad_terbaru:array<int,array{waktu:string,q:string,a:string}>}}
     */
    private function trainingLogs(): array
    {
        // --- Pertanyaan yang jatuh ke fallback ---
        $unmatched = [];
        $f = storage_path('logs/assistant-unmatched.log');
        if (is_file($f)) {
            $lines = array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -300);
            foreach ($lines as $line) {
                if (preg_match('/^\[(?<ts>[^\]]+)\]\s*u(?<uid>\d+):\s*(?<q>.+)$/u', $line, $m)) {
                    $key = mb_strtolower(trim($m['q']));
                    if (! isset($unmatched[$key])) {
                        $unmatched[$key] = ['q' => trim($m['q']), 'n' => 0, 'terakhir' => $m['ts']];
                    }
                    $unmatched[$key]['n']++;
                    $unmatched[$key]['terakhir'] = $m['ts'];
                }
            }
            usort($unmatched, fn ($a, $b) => $b['n'] <=> $a['n']);
            $unmatched = array_slice($unmatched, 0, 40);
        }

        // --- Umpan balik bertingkat ---
        $ringkasan = ['bad' => 0, 'ok' => 0, 'great' => 0];
        $perTopik = [];
        $badTerbaru = [];
        $respons = [];
        $f = storage_path('logs/assistant-feedback.log');
        if (is_file($f)) {
            $lines = array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -500);
            foreach (array_reverse($lines) as $line) {
                if (! preg_match('/^\[(?<ts>[^\]]+)\]\s*(?<lv>BAD|OK|GREAT)\s+u(?<uid>\d+)\s+topik=(?<topik>\S+):\s*Q:\s*(?<q>.*?)\s*\|\s*A:\s*(?<a>.*)$/u', $line, $m)) {
                    continue;
                }
                $lv = strtolower($m['lv']);
                $ringkasan[$lv] = ($ringkasan[$lv] ?? 0) + 1;
                $topik = $m['topik'];
                $perTopik[$topik] ??= ['topik' => $topik, 'bad' => 0, 'ok' => 0, 'great' => 0];
                $perTopik[$topik][$lv]++;
                if ($lv === 'bad' && count($badTerbaru) < 25) {
                    $badTerbaru[] = ['waktu' => $m['ts'], 'q' => trim($m['q']), 'a' => Str::limit(trim($m['a']), 220)];
                }
                if (count($respons) < 80) {
                    $respons[] = [
                        'waktu' => $m['ts'],
                        'q' => trim($m['q']),
                        'a' => Str::limit(trim($m['a']), 800),
                        'topik' => $topik,
                        'level' => $lv,
                    ];
                }
            }
            $perTopik = array_values($perTopik);
            usort($perTopik, fn ($a, $b) => $b['bad'] <=> $a['bad']);
        }

        return [$unmatched, [
            'ringkasan' => $ringkasan,
            'per_topik' => $perTopik,
            'bad_terbaru' => $badTerbaru,
            'respons' => $respons,
        ]];
    }

    /**
     * Jawaban bertahap (SSE lewat fetch + sesi login biasa).
     * Event: meta, status, delta, block, error, done.
     */
    /**
     * Upload lampiran staged (gambar/teks) sebelum pesan dikirim.
     * File disimpan di direktori privat per-pengguna dan baru dipakai
     * saat pesan berikutnya mengirim id-nya.
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        if (! config('services.ai.attachments', true)) {
            return response()->json(['ok' => false, 'message' => 'Fitur lampiran asisten sedang dinonaktifkan sementara.'], 422);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,txt,md,csv,log'],
        ]);

        $file = $validated['file'];
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = Str::uuid()->toString().'.'.$ext;
        \Illuminate\Support\Facades\Storage::disk('local')
            ->putFileAs('ai-lampiran/'.$request->user()->id, $file, $name);

        return response()->json([
            'ok' => true,
            'id' => $name,
            'name' => Str::limit($file->getClientOriginalName() ?: $name, 120, ''),
        ]);
    }

    /**
     * Sajikan lampiran — hanya file di direktori milik pengguna sendiri.
     */
    public function serveAttachment(Request $request, string $id)
    {
        $id = basename($id);
        abort_unless(preg_match('/^[A-Fa-f0-9\-]{36}\.[A-Za-z0-9]{1,10}$/', $id), 404);
        $rel = 'ai-lampiran/'.$request->user()->id.'/'.$id;
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($rel), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($rel), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Validasi id lampiran staged → info file. Hanya file milik pengguna
     * sendiri yang boleh dipakai; tipe diperiksa ulang dari isi file.
     *
     * @param  list<array{id?: string, name?: string}>  $items
     * @return list<array{name: string, path: string, mime: string, image: bool}>
     */
    private function resolveAttachments(User $user, array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $id = basename((string) ($it['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $rel = 'ai-lampiran/'.$user->id.'/'.$id;
            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($rel)) {
                continue;
            }
            $abs = \Illuminate\Support\Facades\Storage::disk('local')->path($rel);
            $mime = (string) (mime_content_type($abs) ?: 'application/octet-stream');
            $isImage = str_starts_with($mime, 'image/');
            $isText = in_array($mime, ['text/plain', 'text/csv', 'text/markdown', 'text/x-log', 'application/json'], true);
            if (! $isImage && ! $isText) {
                continue;
            }
            $out[] = [
                'name' => Str::limit((string) ($it['name'] ?? $id), 120, ''),
                'path' => $abs,
                'mime' => $mime,
                'image' => $isImage,
            ];
        }

        return $out;
    }

    public function stream(Request $request, AdminAiOrchestrator $orchestrator): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'client_id' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{8,64}$/'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*.id' => ['required', 'string', 'regex:/^[A-Fa-f0-9\-]{36}\.[A-Za-z0-9]{1,10}$/'],
            'attachments.*.name' => ['nullable', 'string', 'max:120'],
        ]);
        $user = $request->user();
        $message = trim($validated['message']);
        if ($message === '') {
            return response()->json(['ok' => false, 'message' => 'Pesan kosong.'], 422);
        }

        $lock = Cache::lock('copilot-stream:'.$user->id.':'.$validated['client_id'], 200);
        if (! $lock->get()) {
            return response()->json(['ok' => false, 'message' => 'Pesan ini masih diproses.'], 409);
        }

        try {
            if (! empty($validated['conversation_id'])) {
                $conversation = AiConversation::query()->find((int) $validated['conversation_id']);
                abort_unless($conversation, 404);
                $this->authorizeConversation($request, $conversation);
            } else {
                $conversation = AiMessage::query()
                    ->where('role', 'user')
                    ->where('meta->client_id', $validated['client_id'])
                    ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
                    ->first()?->conversation
                    ?? AiConversation::query()->create([
                        'user_id' => $user->id,
                        'title' => Str::limit($message, 48, '…'),
                        'last_message_at' => now(),
                    ]);
            }

            $userMessage = $conversation->messages()
                ->where('role', 'user')
                ->where('meta->client_id', $validated['client_id'])
                ->first();
            $existingReply = $userMessage ? $this->replyFor($conversation, $userMessage) : null;

            if (! $userMessage) {
                $userMessage = AiMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $message,
                    'meta' => ['client_id' => $validated['client_id']],
                ]);
            }
            if ($conversation->title === 'Percakapan baru') {
                $conversation->title = Str::limit($message, 48, '…');
            }
            $conversation->last_message_at = now();
            $conversation->save();
        } catch (\Throwable $e) {
            $lock->release();
            throw $e;
        }

        $history = $this->providerHistory($conversation, $userMessage->id);
        $prompt = $userMessage->content;
        $attachments = config('services.ai.attachments', true)
            ? $this->resolveAttachments($user, $validated['attachments'] ?? [])
            : [];
        if ($attachments !== []) {
            $meta = is_array($userMessage->meta) ? $userMessage->meta : [];
            $meta['attachments'] = array_map(
                fn ($a) => ['id' => basename($a['path']), 'name' => $a['name'], 'image' => $a['image']],
                $attachments
            );
            $userMessage->meta = $meta;
            $userMessage->save();
        }

        return response()->stream(function () use ($orchestrator, $conversation, $userMessage, $existingReply, $history, $prompt, $user, $lock, $attachments): void {
            ignore_user_abort(true);
            set_time_limit(200);
            if (PHP_SAPI !== 'cli') {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
            }

            $clientGone = false;
            $lastPing = microtime(true);
            $emit = function (string $event, array $data) use (&$clientGone, &$lastPing): bool {
                if ($clientGone) {
                    return false;
                }
                echo 'event: '.$event."\n".'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                flush();
                $lastPing = microtime(true);
                if (connection_aborted()) {
                    $clientGone = true;
                }

                return ! $clientGone;
            };
            $alive = function () use (&$clientGone, &$lastPing): bool {
                if (! $clientGone && microtime(true) - $lastPing >= 1.5) {
                    echo ": ping\n\n";
                    flush();
                    $lastPing = microtime(true);
                    $clientGone = connection_aborted() === 1;
                }

                return ! $clientGone;
            };

            try {
                $emit('meta', [
                    'conversation' => [
                        'id' => $conversation->id,
                        'title' => $conversation->title,
                        'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    ],
                    'user_message_id' => $userMessage->id,
                ]);

                if ($existingReply && ($existingReply->meta['status'] ?? 'complete') === 'complete') {
                    $emit('replay', ['message' => $this->messagePayload($existingReply)]);
                    $emit('done', ['message_id' => $existingReply->id, 'status' => 'complete']);

                    return;
                }

                $turn = new CopilotTurn($emit);
                $status = 'complete';
                $source = 'local';
                $error = null;
                try {
                    $source = $orchestrator->streamChat($prompt, $history, $user, $turn, $alive, $attachments);
                } catch (CopilotStreamAborted) {
                    $status = 'stopped';
                } catch (CopilotProviderError $e) {
                    $status = 'error';
                    $error = $e->userMessage();
                    Log::warning('Copilot: jawaban gagal', ['kind' => $e->kind, 'error' => $e->getMessage()]);
                } catch (\Throwable $e) {
                    $status = 'error';
                    $error = 'Terjadi kesalahan saat menyusun jawaban.';
                    report($e);
                }

                $saved = $this->saveReply($conversation, $userMessage, $existingReply, $turn, $status, $source, $error);
                if ($status === 'error') {
                    $emit('error', ['message' => $error, 'retryable' => true, 'message_id' => $saved->id]);
                }
                $emit('done', ['message_id' => $saved->id, 'status' => $status]);
            } finally {
                $lock->release();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $rows = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'title', 'last_message_at', 'updated_at']);

        return response()->json(['ok' => true, 'conversations' => $rows]);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $conv = AiConversation::query()->create([
            'user_id' => $request->user()->id,
            'title' => 'Percakapan baru',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conv->id,
                'title' => $conv->title,
                'last_message_at' => $conv->last_message_at?->toIso8601String(),
            ],
        ]);
    }

    public function showConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $messages = $conversation->messages()
            ->get(['id', 'role', 'content', 'meta', 'created_at'])
            ->map(fn (AiMessage $m) => $this->messagePayload($m));

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
            'messages' => $messages,
        ]);
    }

    /** Ubah judul percakapan (rename topik) — milik sendiri saja. */
    public function updateConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:120'],
        ]);
        $conversation->title = trim($validated['title']);
        $conversation->save();

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroyConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        // Bersihkan proposal pending yang tersimpan di meta pesan percakapan ini
        $proposalIds = [];
        foreach ($conversation->messages()->whereNotNull('meta')->get(['meta']) as $msg) {
            $meta = is_array($msg->meta) ? $msg->meta : [];
            foreach (($meta['proposals'] ?? []) as $p) {
                if (! empty($p['id'])) {
                    $proposalIds[] = (int) $p['id'];
                }
            }
        }
        $proposalIds = array_values(array_unique(array_filter($proposalIds)));
        if ($proposalIds !== []) {
            AiActionProposal::query()
                ->whereIn('id', $proposalIds)
                ->where('requested_by', $request->user()->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'result' => ['cancelled_at' => now()->toDateTimeString(), 'reason' => 'conversation_deleted'],
                ]);
        }

        // ai_messages ikut cascadeOnDelete
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    public function chat(Request $request, AdminAiOrchestrator $orchestrator): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = AiConversation::query()->findOrFail((int) $validated['conversation_id']);
            $this->authorizeConversation($request, $conversation);
        } else {
            $conversation = AiConversation::query()->create([
                'user_id' => $user->id,
                'title' => 'Percakapan baru',
                'last_message_at' => now(),
            ]);
        }

        $history = [];
        foreach ($validated['history'] ?? [] as $row) {
            $history[] = [
                'role' => $row['role'],
                'content' => $row['content'],
            ];
        }

        // Prefer DB history if conversation already has messages
        if ($conversation->messages()->exists() && $history === []) {
            $history = $conversation->messages()
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->reverse()
                ->map(fn (AiMessage $m) => [
                    'role' => $m->role === 'assistant' ? 'assistant' : 'user',
                    'content' => $m->content,
                ])
                ->values()
                ->all();
        }

        $result = $orchestrator->chat($validated['message'], $history, $user);

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['message'],
            'meta' => null,
        ]);

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $result['reply'],
            'meta' => [
                'source' => $result['source'],
                'charts' => $result['charts'],
                'proposals' => $result['proposals'],
            ],
        ]);

        if ($conversation->title === 'Percakapan baru') {
            $conversation->title = Str::limit(trim($validated['message']), 48, '…');
        }
        $conversation->last_message_at = now();
        $conversation->save();

        return response()->json([
            'ok' => true,
            'reply' => $result['reply'],
            'source' => $result['source'],
            'proposals' => $result['proposals'],
            'charts' => $result['charts'],
            'ai_enabled' => $result['ai_enabled'],
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
        ]);
    }

    public function confirm(
        Request $request,
        AiActionProposal $proposal,
        AdminAiActionExecutor $executor
    ): JsonResponse {
        if (AdminAiToolRegistry::READ_ONLY && ! AdminAiToolRegistry::writeEnabled($proposal->tool)) {
            return response()->json([
                'ok' => false,
                'message' => 'Copilot sedang baca-saja. Lakukan perubahan dari menu SIMALAS terkait.',
                'proposal' => ['id' => $proposal->id, 'status' => $proposal->status],
            ], 422);
        }

        $result = $executor->confirm($proposal, $request->user(), $request);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'result' => $result['result'] ?? null,
            'proposal' => [
                'id' => $proposal->fresh()->id,
                'status' => $proposal->fresh()->status,
            ],
        ], $result['ok'] ? 200 : 422);
    }

    public function cancel(
        Request $request,
        AiActionProposal $proposal,
        AdminAiActionExecutor $executor
    ): JsonResponse {
        $result = $executor->cancel($proposal, $request->user());

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'proposal' => [
                'id' => $proposal->fresh()->id,
                'status' => $proposal->fresh()->status,
            ],
        ], $result['ok'] ? 200 : 422);
    }

    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 403);
    }

    /**
     * Kartu aksi welcome — kemampuan baca-saja yang difilter permission.
     * `fill` = isi input tanpa kirim; `message` = kirim langsung.
     *
     * @return list<array{label: string, desc: string, icon: string, message?: string, fill?: string}>
     */
    private function aiActionCards(User $user): array
    {
        $can = fn (string $menu) => PermissionRegistry::can($user->role, $menu, 'view');

        if ($user->isDosen()) {
            return array_values(array_filter([
                $can('dosen_absensi') ? [
                    'label' => 'Rekap Absensi', 'icon' => 'bar-chart-3',
                    'desc' => 'Ringkasan absensi kelas & tim PBL Anda',
                    'message' => 'Ringkas absensi hari ini',
                ] : null,
                $can('dosen_mahasiswa') ? [
                    'label' => 'Cari Mahasiswa', 'icon' => 'users',
                    'desc' => 'Cari mahasiswa bimbingan Anda',
                    'fill' => 'Cari mahasiswa ',
                ] : null,
                $can('dosen_pbl') ? [
                    'label' => 'Tim PBL', 'icon' => 'clipboard-list',
                    'desc' => 'Cek tim PBL yang Anda bimbing',
                    'message' => 'Tampilkan tim PBL yang saya bimbing',
                ] : null,
                $can('dosen_absensi') ? [
                    'label' => 'Laporan Periodik', 'icon' => 'file-text',
                    'desc' => 'Susun rekap periode untuk evaluasi',
                    'message' => 'Buat laporan absensi bulan ini',
                ] : null,
            ]));
        }

        return array_values(array_filter([
            $can('attendance') ? [
                'label' => 'Rekap Absensi', 'icon' => 'bar-chart-3',
                'desc' => 'Lihat ringkasan absensi berdasarkan periode',
                'message' => '/menu rekap',
            ] : null,
            $can('users') ? [
                'label' => 'Kelola Pengguna', 'icon' => 'users',
                'desc' => 'Cari data akun dan panduan pengelolaan',
                'message' => '/menu akun',
            ] : null,
            $can('ruangan') ? [
                'label' => 'Cek Ruangan', 'icon' => 'door-open',
                'desc' => 'Lihat status dan jadwal penggunaan ruangan',
                'message' => '/menu ruangan',
            ] : null,
            $can('mesin') ? [
                'label' => 'Status Perangkat', 'icon' => 'cpu',
                'desc' => 'Pantau mesin IoT dan konektivitasnya',
                'message' => '/menu perangkat',
            ] : null,
            $can('reports') ? [
                'label' => 'Buat Laporan', 'icon' => 'file-text',
                'desc' => 'Susun dan ekspor laporan sesuai kebutuhan',
                'message' => '/menu laporan',
            ] : null,
        ]));
    }

    /**
     * @return list<array{icon: string, text: string}>
     */
    private function aiExampleQuestions(User $user): array
    {
        $can = fn (string $menu) => PermissionRegistry::can($user->role, $menu, 'view');

        if ($user->isDosen()) {
            return array_values(array_filter([
                $can('dosen_absensi') ? ['icon' => 'calendar-check', 'text' => 'Rekap absensi minggu ini untuk kelas saya'] : null,
                $can('dosen_absensi') ? ['icon' => 'clipboard-check', 'text' => 'Siapa yang tidak lengkap hari ini?'] : null,
                $can('dosen_mahasiswa') ? ['icon' => 'users', 'text' => 'Berapa mahasiswa yang saya bimbing?'] : null,
                $can('dosen_pbl') ? ['icon' => 'clipboard-list', 'text' => 'Tampilkan tim PBL yang saya bimbing'] : null,
            ]));
        }

        return array_values(array_filter([
            $can('attendance') ? ['icon' => 'calendar-check', 'text' => 'Tampilkan rekap absensi minggu ini'] : null,
            $can('users') ? ['icon' => 'users', 'text' => 'Berapa total pengguna aktif sekarang?'] : null,
            $can('mesin') ? ['icon' => 'cpu', 'text' => 'Cek mesin absensi yang sedang offline'] : null,
            $can('ruangan_persetujuan') ? ['icon' => 'door-open', 'text' => 'Tampilkan pengajuan ruangan yang menunggu'] : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(AiMessage $m): array
    {
        $meta = is_array($m->meta) ? $m->meta : [];
        $blocks = $meta['blocks'] ?? [];
        foreach ($meta['charts'] ?? [] as $chart) {
            $blocks[] = ['type' => 'chart', 'chart' => $chart];
        }
        foreach ($meta['proposals'] ?? [] as $p) {
            $blocks[] = ['type' => 'archived_action', 'title' => (string) ($p['title'] ?? 'Aksi'), 'status' => (string) ($p['status'] ?? 'arsip')];
        }

        return [
            'id' => $m->id,
            'role' => $m->role === 'user' ? 'user' : 'assistant',
            'content' => (string) $m->content,
            'status' => $m->role === 'user' ? 'complete' : (string) ($meta['status'] ?? 'complete'),
            'error' => $meta['error'] ?? null,
            'client_id' => $meta['client_id'] ?? null,
            'reply_to' => $meta['reply_to'] ?? null,
            'blocks' => $blocks,
            'attachments' => $meta['attachments'] ?? [],
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function replyFor(AiConversation $conversation, AiMessage $userMessage): ?AiMessage
    {
        return $conversation->messages()
            ->where('role', 'assistant')
            ->where('meta->reply_to', $userMessage->id)
            ->latest('id')
            ->first();
    }

    /**
     * Riwayat yang boleh dikirim ke provider: hanya jawaban selesai, dan tanpa
     * giliran yang dijawab lokal (mis. pencarian nama/NIM).
     *
     * @return list<array{role: string, content: string}>
     */
    private function providerHistory(AiConversation $conversation, int $beforeId): array
    {
        $rows = $conversation->messages()
            ->where('id', '<', $beforeId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $out = [];
        foreach ($rows as $i => $m) {
            if ($m->role !== 'user') {
                continue;
            }
            $reply = $rows->first(fn (AiMessage $r) => $r->role === 'assistant' && ($r->meta['reply_to'] ?? null) === $m->id)
                ?? (($rows[$i + 1] ?? null)?->role === 'assistant' && ! isset($rows[$i + 1]->meta['reply_to']) ? $rows[$i + 1] : null);
            $meta = $reply?->meta ?? [];
            if (! $reply || ($meta['status'] ?? 'complete') !== 'complete' || str_starts_with((string) ($meta['source'] ?? ''), 'local')) {
                continue;
            }
            $out[] = ['role' => 'user', 'content' => (string) $m->content];
            $out[] = ['role' => 'assistant', 'content' => (string) $reply->content];
        }

        return array_slice($out, -8);
    }

    private function saveReply(
        AiConversation $conversation,
        AiMessage $userMessage,
        ?AiMessage $existing,
        CopilotTurn $turn,
        string $status,
        string $source,
        ?string $error
    ): AiMessage {
        $attrs = [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $turn->reply,
            'meta' => array_filter([
                'reply_to' => $userMessage->id,
                'status' => $status,
                'source' => $source,
                'error' => $error,
                'blocks' => $turn->blocks,
            ], fn ($v) => $v !== null),
        ];
        if ($existing) {
            $existing->fill($attrs)->save();
            $reply = $existing;
        } else {
            $reply = AiMessage::query()->create($attrs);
        }
        $conversation->forceFill(['last_message_at' => now()])->save();

        return $reply;
    }
}
