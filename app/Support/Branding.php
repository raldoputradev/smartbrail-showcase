<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Branding aplikasi global — logo & nama yang diunggah admin sekali,
 * dipakai semua shell (admin, mahasiswa, auth, landing) tanpa ganti satu-satu.
 */
class Branding
{
    public const KEY = 'app_branding';
    public const GENERAL_KEY = 'app_general';

    /** Path logo kustom di public/ (null bila belum ada upload). */
    public static function customLogoPath(): ?string
    {
        $saved = SystemSetting::valueFor(self::KEY, []);
        $path = is_array($saved) ? ($saved['logo_path'] ?? null) : null;

        if (! is_string($path) || ! preg_match('#^branding/app-logo\.[a-z0-9]+$#i', $path)) {
            return null;
        }

        if (! is_file(public_path($path))) {
            return null;
        }

        $webp = preg_replace('/\.[a-z0-9]+$/i', '.webp', $path);
        if ($webp !== $path && is_file(public_path($webp))) {
            return $webp;
        }

        return $path;
    }

    /** URL logo kustom dengan cache-bust filemtime; '' bila tidak ada. */
    public static function logoUrl(): string
    {
        $path = self::customLogoPath();
        if (! $path) {
            return '';
        }

        return asset($path) . '?v=' . (int) @filemtime(public_path($path));
    }

    /** URL logo kustom, atau $default bila belum diunggah. */
    public static function logoUrlOr(string $default): string
    {
        return self::logoUrl() ?: $default;
    }

    /** Nama sistem yang dapat diubah admin (fallback SmartBrail). */
    public static function appName(): string
    {
        $general = SystemSetting::valueFor(self::GENERAL_KEY, []);
        $name = is_array($general) ? trim((string) ($general['nama_sistem'] ?? '')) : '';

        return $name !== '' ? $name : 'SmartBrail';
    }

    /** Nama laboratorium (fallback nama bawaan). */
    public static function labName(): string
    {
        $general = SystemSetting::valueFor(self::GENERAL_KEY, []);
        $name = is_array($general) ? trim((string) ($general['nama_laboratorium'] ?? '')) : '';

        return $name !== '' ? $name : 'Laboratorium BRAIL';
    }

    /** @return array{nama_sistem: string, nama_laboratorium: string} */
    public static function general(): array
    {
        return [
            'nama_sistem' => self::appName(),
            'nama_laboratorium' => self::labName(),
        ];
    }
}
