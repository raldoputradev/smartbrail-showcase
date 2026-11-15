<?php

// Soket uji yang menerima koneksi tetapi tidak pernah membalas (meniru Reverb macet), berhenti sendiri setelah 120 detik.
$server = stream_socket_server('tcp://127.0.0.1:8097', $errno, $errstr);
if (! $server) {
    fwrite(STDERR, "gagal listen: {$errstr}\n");
    exit(1);
}
echo "soket macet 127.0.0.1:8097\n";
$held = [];
$until = time() + 120;
while (time() < $until) {
    $client = @stream_socket_accept($server, 1);
    if ($client) {
        $held[] = $client;
    }
}
