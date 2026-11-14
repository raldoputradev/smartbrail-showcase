<?php

namespace App\Support;

use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\TapReceipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tap dengan event_id menyimpan keputusan pertama secara atomik.
 * Permintaan tanpa event_id tetap dilayani untuk firmware lama dan tidak punya jaminan idempotensi.
 */
class AbsensiTapIdempotency
{
    public function __construct(
        private readonly AbsensiTapEvaluator $evaluator
    ) {}

    public function handle(Request $request, MesinIot $mesin): JsonResponse
    {
        $validated = $request->validate([
            'id_mesin' => ['nullable', 'string', 'max:100'],
            'finger_id' => ['required', 'integer', 'min:1'],
            'waktu_tap' => ['nullable', 'date'],
            'event_id' => ['nullable', 'string', 'max:64', 'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/'],
        ]);

        $bodyId = trim((string) ($validated['id_mesin'] ?? ''));
        if ($bodyId !== '' && $bodyId !== $mesin->id_mesin) {
            return $this->json(
                false,
                422,
                'ID_MESIN_MISMATCH',
                'id_mesin pada body tidak sama dengan mesin yang diautentikasi.',
                false,
                false,
                null,
                [],
                'unsupported'
            );
        }

        $eventId = trim((string) ($validated['event_id'] ?? ''));
        if ($eventId === '') {
            return $this->legacy($mesin, $validated);
        }

        $fingerId = (int) $validated['finger_id'];
        $requested = $this->requestedWaktu($validated);
        $hash = $this->payloadHash($mesin->id_mesin, $fingerId, $requested);
        $mesin->touchHeartbeat();

        try {
            return DB::transaction(function () use ($mesin, $fingerId, $requested, $eventId, $hash) {
                $existing = TapReceipt::query()->where('event_id', $eventId)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->responseFromReceipt($existing, $hash, true);
                }

                $delayUs = (int) (getenv('SIMALAS_TAP_RACE_DELAY_US') ?: 0);
                if ($delayUs > 0) {
                    usleep($delayUs);
                }

                $decision = $this->decide($mesin, $fingerId, $requested);
                if (! $decision['store']) {
                    return $this->json(
                        $decision['success'],
                        $decision['http'],
                        $decision['code'],
                        $decision['message'],
                        false,
                        false,
                        $eventId,
                        $decision['data'],
                        'enforced'
                    );
                }

                $receipt = $this->storeReceipt($decision, $eventId, $mesin->id_mesin, $fingerId, $hash);
                if ($decision['success']) {
                    AbsensiSnapshotSignals::committed(['rekap', 'log'], 'staff', $eventId);
                }

                return $this->responseFromReceipt($receipt, $hash, false);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->replayAfterRace($eventId, $hash);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return $this->replayAfterRace($eventId, $hash);
            }

            report($e);

            return $this->internal($eventId);
        } catch (Throwable $e) {
            report($e);

            return $this->internal($eventId);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function legacy(MesinIot $mesin, array $validated): JsonResponse
    {
        $user = User::query()->where('finger_id', (int) $validated['finger_id'])->first();
        if (! $user) {
            return $this->json(
                false,
                404,
                'FINGER_NOT_FOUND',
                'finger_id tidak ditemukan.',
                false,
                false,
                null,
                [],
                'unsupported'
            );
        }

        $mesin->touchHeartbeat();

        $at = null;
        if (! empty($validated['waktu_tap'])) {
            $at = Carbon::parse($validated['waktu_tap'])->timezone('Asia/Jakarta');
            $now = now('Asia/Jakarta');
            if ($at->lt($now->copy()->subDay()) || $at->gt($now->copy()->addMinutes(5))) {
                return $this->json(
                    false,
                    422,
                    'WAKTU_TAP_INVALID',
                    'waktu_tap di luar rentang yang diizinkan (max 24 jam lalu).',
                    false,
                    false,
                    null,
                    [],
                    'unsupported'
                );
            }
        }

        try {
            $result = $this->evaluator->evaluate($user, $mesin->id_mesin, $at);
        } catch (Throwable $e) {
            report($e);

            return $this->internal(null);
        }

        if (! $result['ok']) {
            return $this->json(
                false,
                $result['http'],
                $result['code'],
                $result['message'],
                false,
                false,
                null,
                [],
                'unsupported'
            );
        }

        AbsensiSnapshotSignals::committed(['rekap', 'log'], 'staff', null);

        return $this->json(
            true,
            200,
            $result['code'],
            $result['message'],
            false,
            false,
            null,
            $result['data'] ?? [],
            'unsupported'
        );
    }

    /**
     * @return array{
     *     store: bool,
     *     success: bool,
     *     http: int,
     *     code: string,
     *     message: string,
     *     data: array<string, mixed>,
     *     log_id: int|null,
     *     waktu_tap: Carbon|null,
     *     diterima_pada: Carbon
     * }
     */
    private function decide(MesinIot $mesin, int $fingerId, ?Carbon $requested): array
    {
        $receivedAt = now('Asia/Jakarta');
        $user = User::query()->where('finger_id', $fingerId)->first();
        if (! $user) {
            return $this->terminalDecision(
                false,
                404,
                'FINGER_NOT_FOUND',
                'finger_id tidak ditemukan.',
                [],
                null,
                $requested,
                $receivedAt
            );
        }

        if ($requested !== null) {
            $now = now('Asia/Jakarta');
            if ($requested->gt($now->copy()->addMinutes(5))) {
                return [
                    'store' => false,
                    'success' => false,
                    'http' => 422,
                    'code' => 'WAKTU_TAP_INVALID',
                    'message' => 'waktu_tap masih di depan jam server. Tap disimpan di mesin sampai acknowledgement.',
                    'data' => [],
                    'log_id' => null,
                    'waktu_tap' => $requested,
                    'diterima_pada' => $receivedAt,
                ];
            }

            if ($requested->lt($now->copy()->subDay())) {
                return $this->terminalDecision(
                    false,
                    422,
                    'WAKTU_TAP_INVALID',
                    'waktu_tap di luar rentang yang diizinkan (max 24 jam lalu).',
                    [],
                    null,
                    $requested,
                    $receivedAt
                );
            }
        }

        $result = $this->evaluator->evaluate($user, $mesin->id_mesin, $requested);
        if (! $result['ok']) {
            return $this->terminalDecision(
                false,
                $result['http'],
                $result['code'],
                $result['message'],
                [],
                null,
                $requested,
                $receivedAt
            );
        }

        $data = $result['data'] ?? [];
        $logId = isset($data['log_id']) ? (int) $data['log_id'] : null;
        if ($logId === null) {
            throw new \RuntimeException('Keputusan sukses tanpa log_absensi.');
        }

        $waktuKeputusan = isset($data['waktu_tap'])
            ? Carbon::parse($data['waktu_tap'])->timezone('Asia/Jakarta')
            : ($requested ?? $receivedAt);

        return $this->terminalDecision(
            true,
            200,
            $result['code'],
            $result['message'],
            $data,
            $logId,
            $waktuKeputusan,
            $receivedAt
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     store: bool,
     *     success: bool,
     *     http: int,
     *     code: string,
     *     message: string,
     *     data: array<string, mixed>,
     *     log_id: int|null,
     *     waktu_tap: Carbon|null,
     *     diterima_pada: Carbon
     * }
     */
    private function terminalDecision(
        bool $success,
        int $http,
        string $code,
        string $message,
        array $data,
        ?int $logId,
        ?Carbon $waktuTap,
        Carbon $receivedAt
    ): array {
        return [
            'store' => true,
            'success' => $success,
            'http' => $http,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'log_id' => $logId,
            'waktu_tap' => $waktuTap,
            'diterima_pada' => $receivedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function storeReceipt(array $decision, string $eventId, string $idMesin, int $fingerId, string $hash): TapReceipt
    {
        if ($decision['log_id'] !== null) {
            LogAbsensi::query()->whereKey($decision['log_id'])->update([
                'event_id' => $eventId,
                'diterima_pada' => $decision['diterima_pada'],
            ]);
        }

        return TapReceipt::query()->create([
            'event_id' => $eventId,
            'id_mesin' => $idMesin,
            'finger_id' => $fingerId,
            'waktu_tap' => $decision['waktu_tap'],
            'payload_hash' => $hash,
            'success' => $decision['success'],
            'terminal' => true,
            'code' => $decision['code'],
            'http_status' => $decision['http'],
            'message' => $decision['message'],
            'response_data' => $decision['data'] === [] ? null : $decision['data'],
            'log_absensi_id' => $decision['log_id'],
            'diterima_pada' => $decision['diterima_pada'],
        ]);
    }

    private function responseFromReceipt(TapReceipt $receipt, string $hash, bool $replay): JsonResponse
    {
        if (! hash_equals($receipt->payload_hash, $hash)) {
            return $this->json(
                false,
                409,
                'EVENT_CONFLICT',
                'event_id sudah dipakai payload berbeda.',
                true,
                false,
                $receipt->event_id,
                [],
                'enforced'
            );
        }

        return $this->json(
            (bool) $receipt->success,
            (int) $receipt->http_status,
            (string) $receipt->code,
            (string) $receipt->message,
            true,
            $replay,
            (string) $receipt->event_id,
            $receipt->response_data ?? [],
            'enforced'
        );
    }

    private function replayAfterRace(string $eventId, string $hash): JsonResponse
    {
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $existing = TapReceipt::query()->where('event_id', $eventId)->first();
            if ($existing !== null) {
                return $this->responseFromReceipt($existing, $hash, true);
            }

            usleep(50000);
        }

        return $this->internal($eventId);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function requestedWaktu(array $validated): ?Carbon
    {
        if (! array_key_exists('waktu_tap', $validated) || $validated['waktu_tap'] === null || $validated['waktu_tap'] === '') {
            return null;
        }

        return Carbon::parse($validated['waktu_tap'])->timezone('Asia/Jakarta');
    }

    private function payloadHash(string $idMesin, int $fingerId, ?Carbon $requested): string
    {
        $payload = [
            'finger_id' => $fingerId,
            'id_mesin' => $idMesin,
            'waktu_tap' => $requested?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
        ];
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = $e->getMessage();

        return $sqlState === '23000' && (
            str_contains($message, 'UNIQUE')
            || str_contains($message, 'Duplicate')
            || str_contains($message, 'constraint failed')
        );
    }

    private function internal(?string $eventId): JsonResponse
    {
        return $this->json(
            false,
            500,
            'INTERNAL',
            'Gagal menyimpan tap. Mesin harus mengulang event yang sama.',
            false,
            false,
            $eventId,
            [],
            $eventId === null ? 'unsupported' : 'enforced'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(
        bool $success,
        int $http,
        string $code,
        string $message,
        bool $terminal,
        bool $idempotent,
        ?string $eventId,
        array $data,
        string $idempotency
    ): JsonResponse {
        $body = [
            'success' => $success,
            'terminal' => $terminal,
            'idempotent' => $idempotent,
            'idempotency' => $idempotency,
            'code' => $code,
            'message' => $message,
        ];

        if ($eventId !== null) {
            $body['event_id'] = $eventId;
        }

        if ($data !== []) {
            $body['data'] = $data;
        }

        return response()->json($body, $http);
    }
}
