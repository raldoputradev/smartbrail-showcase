<?php

namespace App\Casts;

use App\Support\FingerprintTemplateCodec;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Menyimpan template sidik jari terenkripsi di DB.
 * Tetap membaca nilai legacy plain (SENSOR_ENROLLED / base64 lama) tanpa error decrypt.
 */
class FingerprintTemplateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === FingerprintTemplateCodec::LEGACY_PLACEHOLDER) {
            return $value;
        }

        // Payload terenkripsi Laravel (JSON base64) diawali eyJ...
        if (is_string($value) && str_starts_with($value, 'eyJ')) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return null;
            }
        }

        // Legacy: plain base64 atau teks lain sebelum migrasi encrypted
        return is_string($value) ? $value : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        if ($value === FingerprintTemplateCodec::LEGACY_PLACEHOLDER) {
            return $value;
        }

        return Crypt::encryptString($value);
    }
}
