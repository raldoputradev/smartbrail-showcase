<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$default = (string) config('database.default');
$database = (string) config("database.connections.{$default}.database");
$normalized = str_replace('\\', '/', strtolower($database));

if ($default !== 'sqlite' || ! str_contains($database, 'simalas-idempotency-test')) {
    fwrite(STDERR, "db guard menolak {$default} {$database}\n");
    exit(2);
}

if (str_contains($normalized, '/htdocs/simalas/') || basename($normalized) === 'simalas') {
    fwrite(STDERR, "db guard menolak path operasional {$database}\n");
    exit(3);
}

DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 8000');

$fixturePath = getenv('SIMALAS_TAP_FIXTURE');
$readyPath = getenv('SIMALAS_TAP_READY');
$goPath = getenv('SIMALAS_TAP_GO');
if (! is_string($fixturePath) || ! is_string($readyPath) || ! is_string($goPath)) {
    fwrite(STDERR, "fixture env tidak lengkap\n");
    exit(4);
}

$fixture = json_decode((string) file_get_contents($fixturePath), true);
if (! is_array($fixture)) {
    fwrite(STDERR, "fixture rusak\n");
    exit(5);
}

file_put_contents($readyPath, 'ready');

$deadline = microtime(true) + 20;
while (! is_file($goPath)) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "batas tunggu go\n");
        exit(6);
    }
    usleep(2000);
}

$server = [
    'HTTP_X_API_KEY' => (string) $fixture['api_key'],
    'HTTP_X_ID_MESIN' => (string) $fixture['id_mesin'],
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
];

$request = Request::create(
    '/api/mesin/tap',
    'POST',
    [],
    [],
    [],
    $server,
    json_encode($fixture['body'], JSON_THROW_ON_ERROR)
);

$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

echo json_encode([
    'http' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR);
