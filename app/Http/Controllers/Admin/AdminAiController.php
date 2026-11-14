<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AdminAi\AdminAiActionExecutor;
use App\Services\AdminAi\AdminAiOrchestrator;
use App\Services\AdminAi\AdminAiToolRegistry;
use App\Services\AdminAi\CopilotProviderError;
use App\Services\AdminAi\CopilotStreamAborted;
use App\Services\AdminAi\CopilotTurn;
use App\Support\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAiController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'title', 'last_message_at', 'updated_at']);

        $dosen = $user->isDosen();
        [$absensiMenu, $cariMenu, $laporanMenu] = $dosen
            ? ['dosen_absensi', 'dosen_mahasiswa', 'dosen_absensi']
            : ['attendance', 'users', 'reports'];
        $suggestions = array_values(array_filter([
            PermissionRegistry::can($user->role, $absensiMenu, 'view')
                ? ['label' => 'Ringkas absensi', 'hint' => 'Rekap hari ini', 'message' => 'Ringkas absensi hari ini'] : null,
            PermissionRegistry::can($user->role, $cariMenu, 'view')
                ? ['label' => 'Cari mahasiswa', 'hint' => 'Nama atau NIM', 'fill' => 'Cari mahasiswa '] : null,
            PermissionRegistry::can($user->role, $absensiMenu, 'view')
                ? ['label' => 'Cek tidak lengkap', 'hint' => 'Hari ini', 'message' => 'Siapa yang tidak lengkap hari ini?'] : null,
            PermissionRegistry::can($user->role, $laporanMenu, 'view')
                ? ['label' => 'Buat laporan', 'hint' => 'Pilih periode', 'message' => 'Buat laporan absensi'] : null,
        ]));

        // Masuk dari menu selalu percakapan baru; ?c= hanya untuk percakapan yang dibuka eksplisit (milik sendiri).
        $initial = null;
        if ($request->filled('c') && ctype_digit((string) $request->query('c'))) {
            $initial = $conversations->firstWhere('id', (int) $request->query('c'))?->id
                ?? AiConversation::query()->where('user_id', $user->id)->whereKey((int) $request->query('c'))->value('id');
        }
        $prefix = $dosen ? 'dosen' : 'admin';

        return view('admin.ai_assistant', [
            'aiEnabled' => app(AdminAiOrchestrator::class)->isGeminiConfigured(),
            'conversations' => $conversations,
            'suggestions' => $suggestions,
            'backUrl' => $dosen ? route('dosen.dashboard') : route('admin.dashboard'),
            'cpConfig' => [
                'pageUrl' => route($prefix.'.ai'),
                'streamUrl' => route($prefix.'.ai.chat.stream'),
                'conversationsUrl' => route($prefix.'.ai.conversations'),
                'initialConversation' => $initial,
                'placeholder' => $dosen
                    ? 'Tanyakan absensi, rekap, laporan, atau cari mahasiswa…'
                    : 'Tanyakan absensi, laporan, atau cari mahasiswa…',
            ],
        ]);
    }

    /**
     * Jawaban bertahap (SSE lewat fetch + sesi login biasa).
     * Event: meta, status, delta, block, error, done.
     */
    public function stream(Request $request, AdminAiOrchestrator $orchestrator): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'client_id' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{8,64}$/'],
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

        return response()->stream(function () use ($orchestrator, $conversation, $userMessage, $existingReply, $history, $prompt, $user, $lock): void {
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
                    $source = $orchestrator->streamChat($prompt, $history, $user, $turn, $alive);
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
        if (AdminAiToolRegistry::READ_ONLY) {
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
