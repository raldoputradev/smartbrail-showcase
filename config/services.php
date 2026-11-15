<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'GEMINI_FALLBACK_MODELS',
                'gemini-3.5-flash,gemini-flash-lite-latest,gemini-3.6-flash,gemini-flash-latest'
            ))
        ))),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 5),
        // Batas jeda antar potongan stream; lewat dari ini dianggap timeout.
        'read_timeout' => (int) env('GEMINI_READ_TIMEOUT', 30),
    ],

    // Fallback AI gratis saat kuota Gemini habis.
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    // Fallback analisis gambar via endpoint OpenAI-compatible (Ollama lokal,
    // OpenRouter, dsb). Ollama tidak butuh api_key; gagal cepat bila server mati.
    // Kill-switch lampiran/gambar di semua asisten (admin, dosen, laboran,
    // mahasiswa) — matikan untuk menghemat kuota/AI vision semester ini.
    'ai' => [
        'attachments' => env('AI_ATTACHMENTS_ENABLED', true),
    ],

    'vision_fallback' => [
        'enabled' => env('VISION_FALLBACK_ENABLED', true),
        'base_url' => env('VISION_FALLBACK_BASE_URL', 'http://127.0.0.1:11434/v1'),
        'api_key' => env('VISION_FALLBACK_API_KEY', ''),
        'model' => env('VISION_FALLBACK_MODEL', 'qwen2.5vl:3b'),
        'connect_timeout' => (int) env('VISION_FALLBACK_CONNECT_TIMEOUT', 2),
        'timeout' => (int) env('VISION_FALLBACK_TIMEOUT', 45),
    ],


    'webpush' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
