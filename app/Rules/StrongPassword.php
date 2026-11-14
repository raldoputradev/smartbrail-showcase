<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (strlen($password) < 8) {
            $fail('Password minimal 8 karakter.');

            return;
        }

        if (!preg_match('/[a-z]/', $password)) {
            $fail('Password harus mengandung huruf kecil (a-z).');

            return;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $fail('Password harus mengandung huruf besar (A-Z).');

            return;
        }

        if (!preg_match('/[0-9]/', $password)) {
            $fail('Password harus mengandung angka.');

            return;
        }

        // Simbol/tanda baca: @ , . ! # dll. — bukan hanya huruf+angka.
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $fail('Password harus mengandung simbol (contoh: @ # ! , . _).');

            return;
        }

        $weak = [
            'password',
            'password1',
            'password123',
            '12345678',
            '123456789',
            'qwerty123',
            'admin123',
            'simalas123',
            'mahasiswa123',
            'mahasiswa@123',
        ];
        if (in_array(strtolower($password), $weak, true)) {
            $fail('Password terlalu umum. Gunakan kombinasi huruf, angka, dan simbol yang unik.');

            return;
        }

        // Tolak pola terlalu sederhana: karakter berulang.
        if (preg_match('/^(.)\1{7,}$/', $password)) {
            $fail('Password terlalu sederhana. Hindari karakter berulang.');
        }
    }
}
