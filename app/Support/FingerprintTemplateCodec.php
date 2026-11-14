<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * DY50 / R307 template: 512 bytes (4 x 128-byte packets).
 * Stored in users.finger_template as standard base64 (no line breaks).
 */
class FingerprintTemplateCodec
{
    public const TEMPLATE_BYTE_LENGTH = 512;

    public const LEGACY_PLACEHOLDER = 'SENSOR_ENROLLED';

    /**
     * @param  array<string, mixed>  $input
     */
    public static function binaryFromRequest(array $input): ?string
    {
        if (!empty($input['template_base64']) && is_string($input['template_base64'])) {
            return self::decodeBase64(trim($input['template_base64']));
        }

        if (!empty($input['template']) && is_string($input['template'])) {
            return self::decodeBase64(trim($input['template']));
        }

        if (!empty($input['template_hex']) && is_string($input['template_hex'])) {
            return self::decodeHex(trim($input['template_hex']));
        }

        return null;
    }

    public static function hasValidTemplate(?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if (self::isLegacyPlaceholder($stored)) {
            return false;
        }

        try {
            $binary = self::decodeStored($stored);

            return strlen($binary) === self::TEMPLATE_BYTE_LENGTH;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function isLegacyPlaceholder(?string $stored): bool
    {
        return $stored === null
            || $stored === ''
            || $stored === self::LEGACY_PLACEHOLDER;
    }

    public static function encodeForStorage(string $binary): string
    {
        $binary = self::assertExactSize($binary);

        return base64_encode($binary);
    }

    public static function decodeStored(string $stored): string
    {
        if (self::isLegacyPlaceholder($stored)) {
            throw new InvalidArgumentException('Template legacy / belum ada blob.');
        }

        return self::decodeBase64($stored);
    }

    public static function toBase64(string $stored): string
    {
        if (self::isLegacyPlaceholder($stored)) {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $stored) === 1 && strlen($stored) < 800) {
            return $stored;
        }

        return base64_encode(self::decodeStored($stored));
    }

    public static function toHex(string $stored): string
    {
        return bin2hex(self::decodeStored($stored));
    }

    public static function crc32Hex(string $stored): string
    {
        if (!self::hasValidTemplate($stored)) {
            return '';
        }

        $crc = crc32(self::decodeStored($stored));

        return sprintf('%08x', $crc & 0xffffffff);
    }

    public static function decodeBase64(string $value): string
    {
        $value = preg_replace('/\s+/', '', $value) ?? $value;
        $binary = base64_decode($value, true);

        if ($binary === false) {
            throw new InvalidArgumentException('template_base64 tidak valid.');
        }

        return self::assertExactSize($binary);
    }

    public static function decodeHex(string $value): string
    {
        $value = strtolower(preg_replace('/\s+/', '', $value) ?? $value);

        if ($value === '' || (strlen($value) % 2) !== 0 || !ctype_xdigit($value)) {
            throw new InvalidArgumentException('template_hex tidak valid.');
        }

        $binary = hex2bin($value);

        if ($binary === false) {
            throw new InvalidArgumentException('template_hex tidak dapat di-decode.');
        }

        return self::assertExactSize($binary);
    }

    private static function assertExactSize(string $binary): string
    {
        $len = strlen($binary);
        if ($len !== self::TEMPLATE_BYTE_LENGTH) {
            throw new InvalidArgumentException(
                'Ukuran template harus '.self::TEMPLATE_BYTE_LENGTH." byte, diterima {$len} byte."
            );
        }

        return $binary;
    }
}
