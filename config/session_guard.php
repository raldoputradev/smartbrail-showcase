<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idle timeout (menit tanpa aktivitas)
    |--------------------------------------------------------------------------
    | Default mengikuti SESSION_LIFETIME. Browser akan logout otomatis + redirect login.
    */
    'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', env('SESSION_LIFETIME', 90)),

    /*
    | Peringatan sebelum logout otomatis (menit).
    */
    'warn_before_minutes' => (int) env('SESSION_IDLE_WARN_MINUTES', 5),

    /*
    | Batas maksimal sesi sejak login (jam). 0 = nonaktif.
    | Setelah lewat, wajib login ulang meski masih aktif.
    */
    'absolute_hours' => (int) env('SESSION_ABSOLUTE_HOURS', 8),

    /*
    | Aktifkan guard idle di layout dashboard / mahasiswa web.
    */
    'enabled' => env('SESSION_GUARD_ENABLED', true),

];
