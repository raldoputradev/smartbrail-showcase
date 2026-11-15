<?php

/**
 * Router `php -S 127.0.0.1:8097` yang meniru streamGenerateContent?alt=sse.
 * Skenario dipilih dari kata pada pesan user terakhir. Tanpa jaringan keluar.
 */

$log = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copilot_fake_gemini_payloads.jsonl';
$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true) ?: [];
file_put_contents($log, json_encode([
    'at' => date('c'),
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'key_header' => isset($_SERVER['HTTP_X_GOOG_API_KEY']) ? 'ada' : 'tidak',
    'payload' => $payload,
], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);

if (! str_contains($_SERVER['REQUEST_URI'] ?? '', ':streamGenerateContent')) {
    http_response_code(404);
    echo '{"error":{"message":"bukan endpoint stream"}}';

    return true;
}

$contents = $payload['contents'] ?? [];
$lastUserText = '';
$toolResponse = null;
foreach ($contents as $c) {
    foreach ($c['parts'] ?? [] as $p) {
        if (($c['role'] ?? '') === 'user' && isset($p['text'])) {
            $lastUserText = (string) $p['text'];
            $toolResponse = null;
        }
        if (isset($p['functionResponse'])) {
            $toolResponse = $p['functionResponse'];
        }
    }
}
$q = mb_strtolower($lastUserText);

if (str_contains($q, 'gagal')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo '{"error":{"code":500,"message":"kesalahan palsu"}}';

    return true;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
while (ob_get_level() > 0) {
    ob_end_flush();
}

$send = function (array $parts, int $sleepMs = 70): void {
    echo 'data: '.json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => $parts]]]], JSON_UNESCAPED_UNICODE)."\r\n\r\n";
    flush();
    if ($sleepMs > 0) {
        usleep($sleepMs * 1000);
    }
};
$text = function (string $s, int $size = 14, int $sleepMs = 70) use ($send): void {
    $chars = mb_str_split($s);
    for ($i = 0; $i < count($chars); $i += $size) {
        $send([['text' => implode('', array_slice($chars, $i, $size))]], $sleepMs);
    }
};

if (str_contains($q, 'laporan') && $toolResponse === null) {
    $periode = str_contains($q, 'lalu') ? 'bulan_lalu' : 'bulan_ini';
    $send([['functionCall' => ['name' => 'laporan_absensi_periode', 'args' => ['periode' => $periode]]]], 300);

    return true;
}
if (preg_match('/\[(nama|nim|email)_\d+\]/', $q, $ph) && $toolResponse === null) {
    $send([['functionCall' => ['name' => 'cari_mahasiswa', 'args' => ['q' => strtoupper($ph[0])]]]], 300);

    return true;
}
if ((str_contains($q, 'ringkas') || str_contains($q, 'rekap')) && $toolResponse === null) {
    $send([['functionCall' => ['name' => 'ringkasan_absensi_hari_ini', 'args' => (object) []]]], 300);

    return true;
}

if ($toolResponse !== null) {
    $r = $toolResponse['response'] ?? [];
    $data = $r['data'] ?? $r;
    if (($toolResponse['name'] ?? '') === 'laporan_absensi_periode') {
        $text('Laporan **'.($data['periode'] ?? 'periode').'** ('.($data['tanggal_mulai'] ?? '?').' s.d. '.($data['tanggal_akhir'] ?? '?').'), '.($data['filter'] ?? 'semua mahasiswa').'.');
    } elseif (($toolResponse['name'] ?? '') === 'cari_mahasiswa') {
        $text('Pencarian untuk '.(preg_match('/\[(?:NAMA|NIM|EMAIL)_\d+\]/', $lastUserText, $m) ? $m[0] : 'kata kunci').' selesai; '.($data['jumlah'] ?? 0).' akun cocok.');
    } else {
        $s = $data['ringkasan'] ?? [];
        $text('Rekap hari ini dari Cek Absensi.'.((int) ($s['belum_tap'] ?? 0) > 0 ? ' Angka masih bisa berubah selama masih ada yang belum tap.' : ''));
    }

    return true;
}

if (str_contains($q, 'html')) {
    $text("Uji keamanan render:\n\n<script>alert('xss')</script>\n<img src=x onerror=\"alert(1)\">\n"
        ."Teks <b>tebal palsu</b> dan <iframe src=\"https://evil.example\"></iframe>.\n\n"
        ."- [tautan javascript](javascript:alert(1))\n- [tautan luar](https://evil.example/curi)\n- [menu laporan](/admin/reports)\n\n"
        ."Nama kolom `hadir_tepat_waktu` dan kata snake_case_tetap_utuh tidak boleh miring.\n", 9, 60);

    return true;
}

if (str_contains($q, 'lambat')) {
    $text('Mulai menyusun jawaban, tunggu sebentar', 10, 80);
    sleep(40);
    $text(' — seharusnya sudah timeout.');

    return true;
}

if (str_contains($q, 'panjang')) {
    $body = "## Ringkasan minggu ini\n\nKehadiran **stabil** dibanding minggu lalu, dengan beberapa catatan:\n\n"
        ."1. Kelas *TI-1A* paling konsisten.\n2. Tim Beta perlu diingatkan tap pulang.\n3. Alfa turun tipis.\n\n"
        ."| Kelas | Lengkap | Tidak lengkap | Alfa |\n|---|---:|---:|---:|\n"
        ."| TI-1A | 41 | 6 | 3 |\n| TI-1B | 37 | 9 | 4 |\n| Total | 78 | 15 | 7 |\n\n"
        ."### Saran tindak lanjut\n\n- Ingatkan **tap pulang** lewat pengumuman.\n- Cek mesin di pintu B (`ABSEN-02`).\n"
        ."  - Pastikan jam mesin sinkron.\n- Tinjau ulang di [menu Laporan](/admin/reports?periode=minggu_ini).\n\n"
        ."> Angka contoh pada skenario uji ini bukan data SIMALAS.\n\n```\nperiode=minggu_ini\nfilter=semua\n```\n\n";
    $body .= str_repeat("Paragraf tambahan untuk menguji gulir: data dibaca dari rekap yang sama dengan halaman SIMALAS, sehingga angka konsisten.\n\n", 8);
    $text($body, 6, 25);

    return true;
}

$text('Copilot siap membantu membaca **absensi** dan **laporan**. Coba tanyakan ringkasan hari ini atau laporan bulan ini.', 8, 60);

return true;
