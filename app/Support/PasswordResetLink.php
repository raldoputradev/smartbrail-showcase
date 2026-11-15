<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tautan pemulihan password sekali pakai via email.
 *
 * Token acak disimpan sebagai hash SHA-256 pada kolom `token` di
 * `email_verification_tokens` (type=password_reset, otp_code=''),
 * terikat pada email akun — tidak pernah dikirim ke alamat lain.
 */
class PasswordResetLink
{
    public const TYPE = 'password_reset';

    public const TTL_MINUTES = 60;

    public static function pruneExpired(): void
    {
        DB::table('email_verification_tokens')
            ->where('type', self::TYPE)
            ->where('token', '!=', '')
            ->where(function ($q) {
                $q->where('expires_at', '<', now()->subDay())
                    ->orWhere(function ($q2) {
                        $q2->where('verified', true)->where('updated_at', '<', now()->subHour());
                    });
            })
            ->delete();
    }

    /**
     * Terbitkan token baru; token lama untuk email yang sama dibatalkan.
     *
     * @return string plain token untuk URL
     */
    public static function issue(User $user): string
    {
        $plain = Str::random(64);
        $email = AccountEmailVerification::normalizeEmail($user->email);

        DB::table('email_verification_tokens')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('type', self::TYPE)
            ->delete();

        DB::table('email_verification_tokens')->insert([
            'email' => $email,
            'token' => hash('sha256', $plain),
            'otp_code' => '',
            'type' => self::TYPE,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plain;
    }

    /**
     * Resolve token menjadi record + user. Null bila invalid/kedaluwarsa/sudah dipakai.
     *
     * @return array{record: object, user: \App\Models\User}|null
     */
    public static function resolve(?string $plain): ?array
    {
        if (! is_string($plain) || ! preg_match('/^[A-Za-z0-9]{40,80}$/', $plain)) {
            return null;
        }

        $record = DB::table('email_verification_tokens')
            ->where('token', hash('sha256', $plain))
            ->where('type', self::TYPE)
            ->orderByDesc('id')
            ->first();

        if (! $record || \Carbon\Carbon::parse($record->expires_at)->isPast() || $record->verified) {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $record->email)])->first();
        if (! $user) {
            return null;
        }

        return ['record' => $record, 'user' => $user];
    }

    /** Hapus token setelah berhasil dipakai (sekali pakai). */
    public static function consume(int $id): void
    {
        DB::table('email_verification_tokens')->where('id', $id)->delete();
    }
}
