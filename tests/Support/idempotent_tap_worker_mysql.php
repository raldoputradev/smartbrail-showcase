<?php

/**
 * Worker tap konkuren. Hanya database uji simalas_cleanup_test di host lokal.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$host = (string) config('database.connections.mysql.host');
$database = (string) config('database.connections.mysql.database');
$current = (string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '');

if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "db guard menolak host\n");
    exit(2);
}
if ($database !== 'simalas_cleanup_test' || $current !== 'simalas_cleanup_test') {
    fwrite(STDERR, "db guard menolak database\n");
    exit(3);
}

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
$decoded = json_decode($response->getContent(), true);

echo json_encode([
    'http' => $response->getStatusCode(),
    'code' => is_array($decoded) ? ($decoded['code'] ?? null) : null,
    'idempotent' => is_array($decoded) ? ($decoded['idempotent'] ?? null) : null,
    'terminal' => is_array($decoded) ? ($decoded['terminal'] ?? null) : null,
    'idempotency' => is_array($decoded) ? ($decoded['idempotency'] ?? null) : null,
    'jenis' => is_array($decoded) ? ($decoded['data']['jenis'] ?? null) : null,
    'event_id' => is_array($decoded) ? ($decoded['event_id'] ?? null) : null,
], JSON_THROW_ON_ERROR);
