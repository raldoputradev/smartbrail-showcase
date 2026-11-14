<?php

namespace App\Support;

class HumanCaptcha
{
    private const SESSION_KEY = 'human_captcha_answer';
    private const SESSION_AT = 'human_captcha_at';
    private const TTL_SECONDS = 900;

    /**
     * @return array{question: string, hint: string}
     */
    public static function refresh(): array
    {
        $a = random_int(2, 12);
        $b = random_int(2, 12);
        $answer = $a + $b;

        session([
            self::SESSION_KEY => $answer,
            self::SESSION_AT => now()->timestamp,
        ]);

        return [
            'question' => "{$a} + {$b}",
            'hint' => 'Jawab hasil penjumlahan untuk membuktikan Anda bukan bot.',
        ];
    }

    public static function validate(mixed $input): bool
    {
        $expected = session(self::SESSION_KEY);
        $at = (int) session(self::SESSION_AT, 0);

        if ($expected === null || $at <= 0) {
            return false;
        }

        if (now()->timestamp - $at > self::TTL_SECONDS) {
            return false;
        }

        if (!is_numeric($input)) {
            return false;
        }

        return (int) $input === (int) $expected;
    }

    public static function clear(): void
    {
        session()->forget([self::SESSION_KEY, self::SESSION_AT]);
    }
}
