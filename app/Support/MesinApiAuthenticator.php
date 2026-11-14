<?php

namespace App\Support;

use App\Models\MesinIot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MesinApiAuthenticator
{
    /**
     * @return array{0: MesinIot|null, 1: JsonResponse|null}
     */
    public static function resolve(Request $request, ?string $tipe = null): array
    {
        $apiKey = (string) ($request->header('X-API-KEY') ?? $request->input('api_key', ''));
        if ($apiKey === '') {
            return [null, response()->json([
                'success' => false,
                'message' => 'API key mesin wajib dikirim.',
            ], 401)];
        }

        $headerId = trim((string) $request->header('X-ID-MESIN', ''));
        $bodyId = trim((string) $request->input('id_mesin', ''));
        if ($headerId !== '' && $bodyId !== '' && $headerId !== $bodyId) {
            return [null, response()->json([
                'success' => false,
                'terminal' => false,
                'idempotent' => false,
                'idempotency' => 'unsupported',
                'code' => 'ID_MESIN_MISMATCH',
                'message' => 'id_mesin pada body tidak sama dengan header X-ID-MESIN.',
            ], 422)];
        }

        // Header autentikasi menang. Body hanya identitas cadangan bila header kosong.
        $idMesin = $headerId !== '' ? $headerId : $bodyId;
        if ($idMesin === '') {
            return [null, response()->json([
                'success' => false,
                'message' => 'id_mesin wajib (query, body, atau header X-ID-MESIN).',
            ], 422)];
        }

        $query = MesinIot::query()->where('id_mesin', $idMesin);
        if ($tipe !== null) {
            $query->where('tipe', $tipe);
        }

        $mesin = $query->first();
        if (!$mesin || !$mesin->is_active) {
            return [null, response()->json([
                'success' => false,
                'message' => 'Mesin tidak terdaftar atau tidak aktif.',
            ], 403)];
        }

        if (!Hash::check($apiKey, $mesin->api_key)) {
            return [null, response()->json([
                'success' => false,
                'message' => 'API key mesin tidak valid.',
            ], 403)];
        }

        return [$mesin, null];
    }
}
