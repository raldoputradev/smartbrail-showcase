<?php

namespace App\Services\AdminAi;

use Illuminate\Support\Str;

/**
 * Klien streamGenerateContent (SSE). Memakai callback curl agar potongan teks
 * diteruskan saat diterima, bukan setelah respons selesai.
 */
class GeminiSseClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(array<string, mixed>): void  $onChunk
     * @param  callable(): bool  $alive  false = klien sudah putus, hentikan transfer
     *
     * @throws CopilotStreamAborted
     * @throws CopilotProviderError
     */
    public function stream(string $url, string $apiKey, array $payload, callable $onChunk, callable $alive): void
    {
        // Windows menghitung batas waktu PHP secara wall-clock; reset per panggilan provider.
        @set_time_limit(200);
        $buffer = '';
        $status = 0;
        $errorBody = '';
        $aborted = false;
        $thrown = null;

        $dispatch = function (string $raw) use ($onChunk): void {
            $data = '';
            foreach (preg_split("/\r?\n/", $raw) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data .= ltrim(substr($line, 5));
                }
            }
            if ($data === '' || $data === '[DONE]') {
                return;
            }
            $json = json_decode($data, true);
            if (is_array($json)) {
                $onChunk($json);
            }
        };

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'x-goog-api-key: '.$apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => max(1, (int) config('services.gemini.connect_timeout', 5)),
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => max(1, (int) config('services.gemini.read_timeout', 30)),
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$status): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use (&$buffer, &$status, &$errorBody, &$thrown, $dispatch): int {
                if ($status >= 400) {
                    $errorBody .= substr($data, 0, 2000);

                    return strlen($data);
                }
                $buffer .= $data;
                try {
                    while (preg_match("/\r?\n\r?\n/", $buffer, $m, PREG_OFFSET_CAPTURE)) {
                        $end = $m[0][1];
                        $raw = substr($buffer, 0, $end);
                        $buffer = substr($buffer, $end + strlen($m[0][0]));
                        $dispatch($raw);
                    }
                } catch (\Throwable $e) {
                    $thrown = $e;

                    return 0;
                }

                return strlen($data);
            },
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => function () use (&$aborted, $alive): int {
                if (! $alive()) {
                    $aborted = true;

                    return 1;
                }

                return 0;
            },
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($thrown !== null) {
            throw $thrown;
        }
        if ($aborted) {
            throw new CopilotStreamAborted('Klien menghentikan permintaan');
        }
        if ($status >= 400) {
            throw new CopilotProviderError('http', 'Gemini HTTP '.$status.': '.Str::limit(trim($errorBody), 200));
        }
        if ($errno !== 0) {
            $kind = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network';
            throw new CopilotProviderError($kind, 'Gemini '.$kind.': '.$error);
        }
        if (trim($buffer) !== '') {
            $dispatch($buffer);
        }
    }
}
