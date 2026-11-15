<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = (string) config('services.gemini.api_key');
$models = ['gemini-flash-latest', 'gemini-2.0-flash-lite', 'gemini-2.0-flash'];

foreach ($models as $m) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$m.':generateContent?key='.$key;
    $res = Illuminate\Support\Facades\Http::timeout(30)->post($url, [
        'system_instruction' => [
            'parts' => [['text' => 'Jawab singkat Bahasa Indonesia.']],
        ],
        'contents' => [['role' => 'user', 'parts' => [['text' => 'Siapa kamu? Jawab 1 kalimat.']]]],
        'generationConfig' => ['maxOutputTokens' => 120, 'temperature' => 0.4],
    ]);
    $j = $res->json();
    $txt = trim((string) data_get($j, 'candidates.0.content.parts.0.text'));
    $err = data_get($j, 'error.message');
    echo $m.' => HTTP '.$res->status().' | '.($txt !== '' ? $txt : $err).PHP_EOL;
}
