<?php

namespace App\Services\AdminAi;

use RuntimeException;
use Throwable;

final class CopilotProviderError extends RuntimeException
{
    /**
     * @param  'timeout'|'http'|'network'|'empty'|'rate_limit'|'unknown'  $kind
     */
    public function __construct(public readonly string $kind, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $kind, 0, $previous);
    }

    public function userMessage(): string
    {
        return match ($this->kind) {
            'timeout' => 'Layanan AI tidak merespons tepat waktu.',
            'rate_limit' => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
            'empty' => 'Layanan AI tidak mengembalikan jawaban.',
            default => 'Copilot gagal menghubungi layanan AI.',
        };
    }
}
