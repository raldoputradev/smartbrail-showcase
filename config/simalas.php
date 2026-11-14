<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard demo / dummy charts
    |--------------------------------------------------------------------------
    |
    | Saat true, KPI + grafik dashboard admin & dosen di-overlay data contoh
    | agar tampilan terisi (gambaran data asli). Matikan / hapus setelah
    | data produksi cukup: SIMALAS_DASHBOARD_DEMO=false
    |
    | DEMO-DASHBOARD — cari tag ini untuk menghapus seluruh fitur demo.
    */
    'dashboard_demo' => (bool) env('SIMALAS_DASHBOARD_DEMO', true),

    /*
    | Masa berlaku kode aktivasi akun hasil import (hari). Kode lama batal saat dibuat ulang.
    */
    'activation_code_ttl_days' => (int) env('SIMALAS_ACTIVATION_CODE_TTL_DAYS', 14),

    /*
    | Validasi domain email (DNS) di halaman aktivasi. Dimatikan hanya di PHPUnit (tanpa jaringan).
    */
    'activation_email_dns_check' => (bool) env('SIMALAS_ACTIVATION_EMAIL_DNS_CHECK', true),
];
