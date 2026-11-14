<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Form saran — landing publik (tanpa login)
    |--------------------------------------------------------------------------
    */
    'public' => [
        'ip_max_per_hour' => (int) env('SUGGEST_PUBLIC_IP_PER_HOUR', 3),
        'email_max_per_day' => (int) env('SUGGEST_PUBLIC_EMAIL_PER_DAY', 2),
        'duplicate_within_hours' => (int) env('SUGGEST_PUBLIC_DUPLICATE_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Form saran — dosen / mahasiswa (login)
    |--------------------------------------------------------------------------
    */
    'account' => [
        'max_per_hour' => (int) env('SUGGEST_ACCOUNT_PER_HOUR', 8),
        'max_per_day' => (int) env('SUGGEST_ACCOUNT_PER_DAY', 15),
        'duplicate_within_hours' => (int) env('SUGGEST_ACCOUNT_DUPLICATE_HOURS', 12),
    ],

];
