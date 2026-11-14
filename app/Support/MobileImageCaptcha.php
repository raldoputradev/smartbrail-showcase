<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * CAPTCHA gambar angka untuk aplikasi mobile (mirip pola JKN/BPJS).
 */
class MobileImageCaptcha
{
    private const CACHE_PREFIX = 'mob-captcha:';

    private const TTL_SECONDS = 600;

    /**
     * @return array{token: string, image_base64: string, expires_in: int}
     */
    public static function issue(): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$token, $code, now()->addSeconds(self::TTL_SECONDS));

        return [
            'token' => $token,
            'image_base64' => 'data:image/png;base64,'.base64_encode(self::renderPng($code)),
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    public static function verify(?string $token, ?string $answer): bool
    {
        $token = trim((string) $token);
        $answer = preg_replace('/\D/', '', (string) $answer) ?? '';

        if ($token === '' || strlen($answer) !== 6) {
            return false;
        }

        $key = self::CACHE_PREFIX.$token;
        $expected = Cache::get($key);

        if ($expected === null) {
            return false;
        }

        // Cast ketat: driver cache kadang mengembalikan angka sebagai int.
        $expectedDigits = preg_replace('/\D/', '', (string) $expected) ?? '';

        if ($expectedDigits === '' || !hash_equals($expectedDigits, $answer)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    private static function renderPng(string $code): string
    {
        $width = 240;
        $height = 80;
        $img = imagecreatetruecolor($width, $height);
        imageantialias($img, true);

        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        // Warna teks bergaya JKN (hijau tua) + variasi.
        $textColors = [
            imagecolorallocate($img, 21, 128, 61),   // green-700
            imagecolorallocate($img, 22, 101, 52),   // green-800
            imagecolorallocate($img, 4, 120, 87),     // emerald-700
        ];
        $noiseSoft = imagecolorallocate($img, 190, 220, 205);
        $noiseDot = imagecolorallocate($img, 120, 170, 145);

        $font = self::fontPath();

        // Garis lengkung/acak di belakang (samar).
        for ($i = 0; $i < 6; $i++) {
            imageline(
                $img,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $noiseSoft
            );
        }
        for ($i = 0; $i < 3; $i++) {
            imagearc(
                $img,
                random_int(0, $width),
                random_int(0, $height),
                random_int(40, 120),
                random_int(30, 90),
                random_int(0, 180),
                random_int(180, 360),
                $noiseSoft
            );
        }

        $digits = str_split($code);
        $slot = intdiv($width - 24, count($digits));

        foreach ($digits as $index => $char) {
            $color = $textColors[array_rand($textColors)];
            $baseX = 16 + ($index * $slot);

            if ($font !== null) {
                $size = random_int(24, 30);
                $angle = random_int(-18, 18);
                $y = random_int(52, 62);
                $x = $baseX + random_int(-2, 4);
                imagettftext($img, $size, $angle, $x, $y, $color, $font, $char);
            } else {
                // Fallback jika TTF tidak tersedia.
                imagestring($img, 5, $baseX, random_int(24, 36), $char, $color);
            }
        }

        // Titik-titik noise ringan agar sulit dibaca mesin.
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($img, random_int(0, $width - 1), random_int(0, $height - 1), $noiseDot);
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private static function fontPath(): ?string
    {
        if (!function_exists('imagettftext')) {
            return null;
        }

        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\segoeuib.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
