<?php

namespace App\Support;

use Illuminate\Http\Request;

class AbsensiSnapshot
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function envelope(string $surface, string $scope, array $payload): array
    {
        return array_merge([
            'ok' => true,
            'transport' => 'http-snapshot',
            'realtime' => false,
            'surface' => $surface,
            'scope' => $scope,
            'generated_at' => now('Asia/Jakarta')->toIso8601String(),
        ], $payload);
    }

    public static function canonicalQuery(Request $request): string
    {
        $params = $request->query();
        unset($params['token']);
        ksort($params);

        return http_build_query($params);
    }
}
