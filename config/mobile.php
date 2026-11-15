<?php

return [

    /*
    |--------------------------------------------------------------------------
    | APK Mahasiswa (Flutter)
    |--------------------------------------------------------------------------
    | Setelah upload APK baru ke public/downloads/, update versi di sini.
    | version_code harus naik setiap rilis (1.2.1+4 → version_code 4).
    */
    'apk' => [
        // Matikan distribusi APK sementara (fokus portal web). Set true saat rilis mobile siap.
        'available' => filter_var(env('MOBILE_APK_AVAILABLE', false), FILTER_VALIDATE_BOOL),
        'version' => env('MOBILE_APK_VERSION', '1.3.16'),
        'version_code' => (int) env('MOBILE_APK_VERSION_CODE', 19),
        'min_version_code' => (int) env('MOBILE_APK_MIN_VERSION_CODE', 4),
        'filename' => env('MOBILE_APK_FILENAME', 'AIoT-SIMALAS-Mobile.apk'),
        'changelog' => env('MOBILE_APK_CHANGELOG', 'Perbaikan CAPTCHA daftar akun: kode gambar tidak lagi dianggap kedaluwarsa terlalu cepat, dan pesan email/NIM sudah terdaftar lebih jelas.'),
        'force_update' => filter_var(env('MOBILE_APK_FORCE_UPDATE', false), FILTER_VALIDATE_BOOL),
    ],

    'fcm' => [
        // Legacy (project Firebase lama) — project baru biasanya tidak punya Server Key.
        'server_key' => env('FCM_SERVER_KEY'),
        // FCM HTTP v1 — unduh JSON dari Firebase → Service accounts → Generate new private key.
        'service_account_path' => ($p = env('FCM_SERVICE_ACCOUNT_PATH'))
            ? (str_starts_with($p, '/') || preg_match('#^[A-Za-z]:\\\\#', $p) ? $p : base_path($p))
            : storage_path('app/firebase-service-account.json'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

];
