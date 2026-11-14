<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountEmailVerification
{
    public const TYPE = 'account_verify';

    public const OTP_TTL_MINUTES = 30;

    public static function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public static function pruneExpired(): void
    {
        DB::table('email_verification_tokens')
            ->where('expires_at', '<', now())
            ->orWhere(function ($query) {
                $query->where('verified', true)
                    ->where('updated_at', '<', now()->subHour());
            })
            ->delete();
    }

    public static function send(User $user): string
    {
        self::pruneExpired();

        $email = self::normalizeEmail($user->email);
        if ($email === '') {
            throw new \InvalidArgumentException('Email wajib diisi sebelum verifikasi.');
        }

        $lastToken = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', self::TYPE)
            ->orderByDesc('created_at')
            ->first();

        if ($lastToken) {
            $elapsed = now()->diffInSeconds(\Carbon\Carbon::parse($lastToken->created_at), true);
            if ($elapsed < 60) {
                $remaining = (int) ceil(60 - $elapsed);
                throw new \RuntimeException("Tunggu {$remaining} detik sebelum mengirim ulang kode verifikasi.");
            }
        }

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', self::TYPE)
            ->delete();

        DB::table('email_verification_tokens')->insert([
            'email' => $email,
            'token' => '',
            'otp_code' => $otpCode,
            'type' => self::TYPE,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AsyncMailer::dispatchOtp($email, $otpCode, $user->nama_lengkap, self::TYPE);

        return "Kode verifikasi sedang dikirim ke {$email}. Silakan cek email Anda dalam beberapa detik.";
    }

    public static function verify(string $email, string $otpCode): ?User
    {
        self::pruneExpired();

        $email = self::normalizeEmail($email);

        $record = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('otp_code', $otpCode)
            ->where('type', self::TYPE)
            ->orderByDesc('id')
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->expires_at)->isPast()) {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return null;
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', self::TYPE)
            ->delete();

        return $user->fresh();
    }
}
