<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotDisposableEmail implements ValidationRule
{
    /** @var list<string> */
    private const BLOCKED_DOMAINS = [
        'mailinator.com',
        'guerrillamail.com',
        'tempmail.com',
        '10minutemail.com',
        'yopmail.com',
        'throwaway.email',
        'trashmail.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));
        $domain = substr(strrchr($email, '@') ?: '', 1);

        if ($domain === '') {
            $fail('Format email tidak valid.');

            return;
        }

        foreach (self::BLOCKED_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
                $fail('Gunakan email aktif (bukan layanan email sementara).');

                return;
            }
        }
    }
}
