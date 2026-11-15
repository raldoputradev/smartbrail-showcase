<?php

namespace App\Support;

use App\Models\AbsensiHarian;
use App\Models\Announcement;
use App\Models\Ruangan;
use App\Models\RuanganPengajuan;
use App\Models\Suggestion;
use App\Models\User;
use Carbon\Carbon;

/**
 * Responden lokal "terlatih" untuk Asisten SmartBrail (mahasiswa).
 * Pipeline: normalisasi slang/typo → resolusi lanjutan dari riwayat →
 * scoring intent berbobot → handler berbasis data nyata.
 */
class StudentLocalAssistant
{
    private const SLANG = [
        'knp' => 'kenapa', 'gmn' => 'bagaimana', 'gmana' => 'bagaimana',
        'gbsa' => 'tidak bisa', 'gabisa' => 'tidak bisa', 'gaboleh' => 'tidak boleh',
        'nggak' => 'tidak', 'gak' => 'tidak', 'gk' => 'tidak', 'kaga' => 'tidak', 'tdk' => 'tidak',
        'blm' => 'belum', 'belom' => 'belum', 'blom' => 'belum',
        'udh' => 'sudah', 'udah' => 'sudah', 'sdh' => 'sudah', 'dah' => 'sudah',
        'lg' => 'lagi', 'lgi' => 'lagi', 'dgn' => 'dengan', 'dg' => 'dengan',
        'tlg' => 'tolong', 'bgt' => 'banget', 'jg' => 'juga', 'kalo' => 'kalau', 'klw' => 'kalau',
        'utk' => 'untuk', 'yg' => 'yang', 'dpt' => 'dapat', 'hrs' => 'harus',
        'jdi' => 'jadi', 'msh' => 'masih', 'bkn' => 'bukan', 'trs' => 'terus', 'trus' => 'terus',
        'tnp' => 'tanpa', 'org' => 'orang', 'tmn' => 'teman', 'tgl' => 'tanggal',
        'skrg' => 'sekarang', 'bsk' => 'besok', 'kemaren' => 'kemarin', 'kmrn' => 'kemarin',
        'dlm' => 'dalam', 'emg' => 'memang', 'kpn' => 'kapan', 'dmna' => 'dimana', 'dimna' => 'dimana',
        'bsa' => 'bisa', 'mw' => 'mau', 'mao' => 'mau', 'pengen' => 'ingin', 'pngen' => 'ingin',
        'pw' => 'password', 'pasword' => 'password', 'paswd' => 'password',
        'msin' => 'mesin', 'mesen' => 'mesin', 'ruangn' => 'ruangan', 'ruwang' => 'ruang',
        'gitu' => 'begitu', 'gtu' => 'begitu', 'kyk' => 'seperti', 'kek' => 'seperti',
        'krn' => 'karena', 'dr' => 'dari', 'pd' => 'pada', 'brp' => 'berapa',
        'byk' => 'banyak', 'tp' => 'tapi', 'tpi' => 'tapi', 'sy' => 'saya',
    ];

    /** Bobot kata kunci per intent; urutan array = prioritas saat seri. */
    private const INTENTS = [
        'report_status' => [
            'status laporan' => 6, 'laporan saya' => 5, 'riwayat laporan' => 6, 'tiket' => 4,
            'sudah dibalas' => 5, 'dibalas belum' => 5, 'laporan terakhir' => 6,
            'respons' => 4, 'respon' => 4, 'direspon' => 5, 'dijawab' => 5, 'jawaban' => 5,
            'balasan' => 5, 'ditanggapi' => 5, 'ditindaklanjuti' => 6, 'tindak lanjut' => 5,
            'kelanjutan' => 4, 'hasil laporan' => 7, 'hasil' => 2, 'laporan' => 2, 'terkirim' => 4,
            'diproses' => 3, 'diterima' => 3, 'update laporan' => 6, 'perkembangan' => 3,
            'barusan' => 4, 'tadi' => 2, 'kirim tadi' => 5, 'yang tadi' => 3,
        ],
        'room_status' => [
            'status pengajuan' => 6, 'pengajuan saya' => 6, 'peminjaman saya' => 5,
            'pengajuan' => 3, 'disetujui' => 3, 'diterima' => 2, 'ditolak' => 3,
            'diputuskan' => 4, 'keputusan' => 3,
        ],
        'room_howto' => [
            'ajukan ruangan' => 8, 'pengajuan ruangan' => 8, 'pinjam ruangan' => 8,
            'ajukan lab' => 8, 'pinjam lab' => 8, 'booking' => 5,
            'sewa ruangan' => 6, 'pakai ruangan' => 5, 'memakai ruangan' => 5,
            'cara pinjam' => 6, 'ajukan' => 2, 'mengajukan' => 2, 'pinjam' => 3, 'sewa' => 2,
        ],
        'room_access' => [
            'kartu akses' => 6, 'kartu' => 3, 'rfid' => 6, 'pin akses' => 7, 'akses ruangan' => 6,
            'buka pintu' => 6, 'buka ruangan' => 5, 'masuk ruangan' => 5, 'tap kartu' => 6,
            'kunci pintu' => 5, 'akses lab' => 5, 'nomor kartu' => 6,
        ],
        'team' => [
            'tim pbl' => 7, 'tim' => 3, 'pbl' => 5, 'penugasan' => 5, 'ditugaskan' => 4,
            'manpro' => 6, 'pembimbing' => 4, 'kelompok' => 5, 'anggota tim' => 6,
            'proyek pbl' => 6, 'punya tim' => 6, 'tim saya' => 5,
        ],
        'attend_today' => [
            'absensi hari ini' => 7, 'absen hari ini' => 7, 'sudah absen' => 6, 'belum absen' => 6,
            'sudah tap' => 6, 'belum tap' => 6, 'kehadiran hari ini' => 7,
            'absen kemarin' => 6, 'kehadiran saya' => 6, 'tadi tap' => 5,
        ],
        'attend_rekap' => [
            'rekap' => 6, 'ringkasan absen' => 7, 'riwayat absen' => 6, 'absen bulan' => 6,
            'kehadiran bulan' => 6, 'total absen' => 6, 'berapa kali absen' => 7,
        ],
        'attend_rules' => [
            'telat' => 4, 'terlambat' => 4, 'toleransi' => 6, 'jam tap' => 6, 'jam masuk' => 5,
            'jam pulang' => 5, 'pulang cepat' => 6, 'ketentuan absen' => 6, 'aturan absen' => 6,
            'tidak lengkap' => 5, 'kapan tap' => 6, 'jam berapa absen' => 7, 'tap masuk' => 4,
            'tap pulang' => 4, 'alfa' => 3,
        ],
        'issue' => [
            'kendala' => 4, 'keluhan' => 4, 'rusak' => 4, 'bermasalah' => 4, 'gagal' => 3,
            'masalah' => 7, 'masaalah' => 7, 'msalah' => 7, 'permasalahan' => 7,
            'error' => 4, 'eror' => 4, 'tidak bisa' => 4, 'tidak tercatat' => 6,
            'belum tercatat' => 6, 'salah status' => 6, 'koreksi' => 4,
            'tidak terbaca' => 5, 'tidak kebaca' => 5,
            'buat laporan' => 7, 'bikin laporan' => 7, 'kirim laporan' => 4, 'laporkan' => 6,
            'mau lapor' => 6, 'ingin lapor' => 6,
        ],
        'upload_help' => [
            'foto gagal' => 6, 'gambar gagal' => 6, 'gagal upload' => 6, 'gagal unggah' => 6,
            'lampiran gagal' => 6, 'tidak bisa upload' => 7, 'gabisa upload' => 7,
            'tidak bisa kirim foto' => 7, 'gak bisa kirim foto' => 7,
            'tidak bisa kirim gambar' => 7, 'gak bisa kirim gambar' => 7,
            'tidak bisa unggah foto' => 7, 'tidak bisa lampirkan' => 7,
            'foto tidak terkirim' => 6, 'foto bukti' => 4, 'ukuran file' => 4,
            'format file' => 4, 'format foto' => 4, 'lampirkan foto' => 5,
            'cara upload foto' => 6, 'upload foto' => 4, 'unggah foto' => 4,
            'kirim gambar' => 3, 'lampiran laporan' => 5,
        ],
        'account_pw' => [
            'password' => 5, 'sandi' => 4, 'ganti password' => 6, 'ubah password' => 6,
            'lupa password' => 7, 'password sementara' => 7, 'peringatan kuning' => 7,
            'titik kuning' => 7, 'amankan akun' => 7, 'reset password' => 6,
        ],
        'account_login' => [
            'tidak bisa login' => 7, 'gagal login' => 7, 'login error' => 6, 'terkunci' => 6,
            'dikunci' => 6, 'tidak bisa masuk' => 6, 'akun ditolak' => 6, 'akun nonaktif' => 6,
        ],
        'account_data' => [
            'email' => 3, 'no hp' => 5, 'nomor hp' => 5, 'whatsapp' => 4, 'ubah email' => 6,
            'ganti email' => 6, 'foto profil' => 6, 'ubah profil' => 5, 'verifikasi email' => 6,
            'otp' => 4, 'kode otp' => 6,
        ],
        'fingerprint' => [
            'sidik jari' => 6, 'fingerprint' => 6, 'finger' => 4, 'enroll' => 6,
            'daftar jari' => 6, 'rekam jari' => 6, 'biometrik' => 6, 'sensor jari' => 5,
        ],
        'announcement' => [
            'pengumuman' => 7, 'info terbaru' => 6, 'berita' => 4, 'update terbaru' => 5,
            'ada pengumuman' => 7,
        ],
        'schedule' => [
            'jadwal' => 4, 'hari wajib' => 6, 'wajib' => 3, 'libur' => 4, 'jadwal hari ini' => 6,
            'jadwal besok' => 6, 'jadwal minggu' => 5, 'tanggal merah' => 5, 'jadwal lab' => 5,
        ],
        'calendar' => [
            'kalender' => 4, 'agenda' => 5, 'acara' => 3, 'event' => 3,
        ],
        'room_location' => [
            'lokasi ruangan' => 7, 'di mana ruangan' => 7, 'letak ruangan' => 7,
            'lantai' => 4, 'gedung' => 4, 'denah' => 5, 'fasilitas' => 4,
            'ruangan di mana' => 7, 'dimana ruangan' => 7, 'posisi ruangan' => 6,
            'di mana' => 3, 'dimana' => 3,
        ],
        'card_lost' => [
            'kartu hilang' => 7, 'kartu akses hilang' => 8, 'kehilangan kartu' => 7,
            'kartu rusak' => 6, 'ganti kartu' => 6, 'kartu baru' => 5,
            'tidak punya kartu' => 5, 'kartu tertinggal' => 6, 'kartu ketinggalan' => 6,
            'hilang' => 4, 'dompet hilang' => 4,
        ],
        'room_list' => [
            'ruangan apa' => 6, 'daftar ruangan' => 7, 'list ruangan' => 7, 'semua ruangan' => 6,
            'ada ruangan' => 5, 'ruangan tersedia' => 5, 'lab apa saja' => 7,
        ],
        'contact' => [
            'hubungi' => 4, 'kontak' => 3, 'lapor ke' => 5, 'tanya laboran' => 6, 'tanya dosen' => 6,
            'minta bantuan' => 6, 'butuh bantuan' => 5, 'nomor laboran' => 6, 'ketemu laboran' => 6,
            'dimana laboran' => 6, 'ruang laboran' => 5,
        ],
        'help' => [
            'panduan' => 6, 'cara pakai' => 6, 'tutorial' => 6, 'cara menggunakan' => 7,
            'cara kerja' => 4, 'cara pakai portal' => 7,
        ],
        'about' => [
            'smartbrail' => 6, 'simalas' => 6, 'apa itu' => 3, 'tentang' => 2,
        ],
        'capabilities' => [
            'bisa apa' => 7, 'bisa bantu' => 3, 'siapa kamu' => 7, 'kamu siapa' => 7,
            'fitur apa' => 6, 'bantuan apa' => 6, 'kemampuan' => 4, 'ngapain' => 4,
        ],
        'frustration' => [
            'parah' => 5, 'kesal' => 5, 'kesel' => 5, 'marah' => 5, 'kecewa' => 5,
            'lama banget' => 5, 'lama sekali' => 5, 'tidak kunjung' => 6,
            'kenapa lama' => 6, 'kok lama' => 6, 'dari tadi' => 4,
            'frustrasi' => 5, 'sebel' => 5, 'ribet' => 4, 'susah' => 3,
            'bukannya' => 3, 'bukan itu' => 4, 'salah terus' => 5,
        ],
        'thanks' => [
            'terima kasih' => 7, 'makasih' => 7, 'thanks' => 6, 'thank you' => 6,
            'mantap' => 3, 'oke' => 1, 'ok' => 1, 'siap' => 1, 'sip' => 1,
        ],
        'greeting' => [
            'halo' => 4, 'hai' => 4, 'hei' => 4, 'pagi' => 3, 'siang' => 3, 'sore' => 3,
            'malam' => 3, 'permisi' => 5, 'assalamu' => 6, 'hey' => 3, 'hallo' => 4,
        ],
    ];

    private const FOLLOWUP_RE = '/^(lalu|terus|lanjut|trus|gimana|bagaimana|yang tadi|maksudnya|kok|kenapa|emang|memang|oh|iya|ya|ok|oke|kalau gitu|terus gimana|gmn|cara|kalau|kalo|jadi|nah|hmm|hm)\b/i';

    /** Topik/intent sebuah pertanyaan — dipakai endpoint umpan balik untuk analisis pelatihan. */
    public static function topicOf(string $message): ?string
    {
        $q = self::normalize($message);
        $intent = self::bestIntent($q);
        return $intent !== null && ! str_starts_with($intent, 'ambig:') ? $intent : null;
    }

    /**
     * @param  array{hari_nama?:string,label_jadwal?:string,label_status_hari?:string,is_wajib?:bool}  $todaySchedule
     * @param  array<int,array{role:string,text:string}>  $history
     */
    public static function reply(User $user, string $message, array $todaySchedule, array $history = []): string
    {
        $raw = trim($message);
        $q = self::normalize($raw);
        $first = explode(' ', trim($user->nama_lengkap))[0] ?? 'Mahasiswa';

        // Pesan lanjutan ambigu → gabung dengan pertanyaan pengguna sebelumnya.
        // TAPI: lewati penggabungan bila pesan ini sendiri sudah punya topik nyata
        // ("kalau cek pengajuan lain bisa?" → room_status, bukan topik sebelumnya).
        $own = self::bestIntent($q);
        $ownIsRealTopic = $own !== null
            && ! str_starts_with($own, 'ambig:')
            && ! in_array($own, ['greeting', 'thanks', 'about', 'capabilities', 'help'], true);
        if (! $ownIsRealTopic && (self::isFollowUp($q) || (mb_strlen($q) <= 6 && $own === null))) {
            $prev = self::lastUserText($history);
            if ($prev !== '') {
                $merged = self::normalize($prev . ' ' . $raw);
                $mergedIntent = self::bestIntent($merged);
                if ($mergedIntent !== null && ! str_starts_with($mergedIntent, 'ambig:')) {
                    $q = $merged;
                }
            }
        }

        $intent = self::bestIntent($q) ?? 'fallback';
        if (str_starts_with($intent, 'ambig:')) {
            [$a, $b] = explode('|', substr($intent, 6));
            return self::rClarify($a, $b, $first);
        }

        // Multi-pertanyaan: "jadwal saya dan status pengajuan" → jawab keduanya.
        {
            $multi = self::multiIntents($q);
            if (count($multi) >= 2) {
                $parts = [];
                foreach ($multi as $partIntent => $partQ) {
                    $parts[] = self::handle($partIntent, $user, $partQ, $partQ, $todaySchedule, $first);
                }
                return implode("\n\n— — —\n\n", $parts);
            }
        }

        if ($intent === 'fallback') {
            // File pengetahuan yang bisa diperbarui admin untuk "melatih" asisten.
            if ($kb = self::knowledgeAnswer($q)) {
                return $kb;
            }
        }

        return self::handle($intent, $user, $q, $raw, $todaySchedule, $first);
    }

    private static function normalize(string $text): string
    {
        $q = ' ' . mb_strtolower($text) . ' ';
        $q = preg_replace('/[?!.,;:()\[\]{}"“”' . "'" . ']/u', ' ', $q) ?? $q;
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        // Akhiran posesif -ku/-mu/-nya pada kata ≥4 huruf → bentuk dasar
        // ("laporanku"→laporan, "pengajuannya"→pengajuan, "jawabannya"→jawaban).
        $q = preg_replace('/\b(\w{4,}?)(ku|mu|nya)\b/u', '$1', (string) $q) ?? $q;
        foreach (self::SLANG as $from => $to) {
            $q = str_replace(' ' . $from . ' ', ' ' . $to . ' ', $q);
        }
        return self::fixTypos((string) $q);
    }

    /**
     * Koreksi salah ketik: token ≥4 huruf yang belum dikenal dibandingkan
     * dengan kosakata kata kunci; bila mirip (jarak edit dekat) → diganti.
     * Contoh: "abensi"→absensi, "pengajuaan"→pengajuan, "sidik jri"→sidik jari.
     */
    private static ?array $vocab = null;

    /** Kata fungsi umum — JANGAN pernah "dikoreksi" (mis. gimana→dimana). */
    private const PROTECTED_WORDS = [
        'gimana', 'bagaimana', 'kenapa', 'mengapa', 'kapan', 'kemana', 'siapa',
        'berapa', 'mana', 'apakah', 'kapan', 'siapakah', 'apa', 'siapa',
        'sudah', 'belum', 'bisa', 'ingin', 'harus', 'perlu', 'sekarang',
        'nanti', 'tadi', 'barusan', 'saja', 'juga', 'atau', 'dengan',
        'untuk', 'pada', 'dalam', 'tanpa', 'sampai', 'hingga', 'kalau',
        'karena', 'supaya', 'agar', 'jadi', 'menjadi', 'lebih', 'paling',
        'sangat', 'terus', 'masih', 'tidak', 'bukan', 'selalu', 'pernah',
        'semua', 'setiap', 'tiap', 'beberapa', 'banyak', 'sedikit',
    ];

    private static function fixTypos(string $q): string
    {
        if (self::$vocab === null) {
            $v = [];
            foreach (self::INTENTS as $kws) {
                foreach (array_keys($kws) as $kw) {
                    foreach (preg_split('/\s+/', (string) $kw) ?: [] as $tok) {
                        if (mb_strlen($tok) >= 4 && ctype_alpha($tok)) {
                            $v[$tok] = true;
                        }
                    }
                }
            }
            self::$vocab = array_keys($v);
        }
        $out = [];
        foreach (preg_split('/\s+/', trim($q)) ?: [] as $tok) {
            if (mb_strlen($tok) >= 4 && ctype_alpha($tok) && ! in_array($tok, self::$vocab, true)
                && ! in_array($tok, self::PROTECTED_WORDS, true)) {
                $limit = mb_strlen($tok) >= 7 ? 2 : 1;
                $best = null;
                $bestD = $limit + 1;
                foreach (self::$vocab as $word) {
                    if (abs(mb_strlen($word) - mb_strlen($tok)) > $limit) {
                        continue;
                    }
                    $d = levenshtein($tok, $word);
                    if ($d <= $limit && $d < $bestD) {
                        $bestD = $d;
                        $best = $word;
                    }
                }
                if ($best !== null) {
                    $tok = $best;
                }
            }
            $out[] = $tok;
        }
        return implode(' ', $out);
    }

    private static function isFollowUp(string $q): bool
    {
        return mb_strlen($q) <= 60 && preg_match(self::FOLLOWUP_RE, $q) === 1;
    }

    /** @param array<int,array{role:string,text:string}> $history */
    private static function lastUserText(array $history): string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'user') {
                return trim((string) ($history[$i]['text'] ?? $history[$i]['content'] ?? ''));
            }
        }
        return '';
    }

    /** Intent skor tertinggi >= 3; sapaan kecil tidak menutupi topik nyata. */
    private static function bestIntent(string $q): ?string
    {
        // Aturan konteks gabungan — mengalahkan skor kata kunci tunggal.
        if (preg_match('/(hilang|kehilangan|tertinggal|ketinggalan)/', $q) && str_contains($q, 'kartu')) {
            return 'card_lost';
        }
        $scores = [];
        foreach (self::INTENTS as $intent => $keywords) {
            $score = 0;
            foreach ($keywords as $kw => $w) {
                if (str_contains($q, $kw)) {
                    $score += $w;
                }
            }
            if ($score > 0) {
                $scores[$intent] = $score;
            }
        }
        if ($scores === []) {
            return null;
        }
        if (count($scores) > 1) {
            unset($scores['greeting'], $scores['thanks']);
        }
        if ($scores === []) {
            return null;
        }
        arsort($scores);
        $best = array_key_first($scores);
        if ($scores[$best] < 3) {
            return null;
        }
        // Dua topik berdekatan dan skornya tidak meyakinkan → klarifikasi, jangan menebak.
        $rest = array_slice($scores, 1, 1, true);
        $second = array_key_first($rest);
        if ($second !== null && $scores[$best] < 7 && ($scores[$best] - $rest[$second]) <= 2) {
            return 'ambig:' . $best . '|' . $second;
        }
        return $best;
    }

    /** Skor satu intent pada teks tertentu. */
    private static function scoreOf(string $intent, string $q): int
    {
        $score = 0;
        foreach (self::INTENTS[$intent] ?? [] as $kw => $w) {
            if (str_contains($q, $kw)) {
                $score += $w;
            }
        }
        return $score;
    }

    /**
     * Pecah "jadwal saya dan status pengajuan" menjadi beberapa intent berbeda.
     * Hanya bila tiap bagian punya intent jelas (≥3) dan berbeda satu sama lain.
     *
     * @return array<string,string> intent => teks bagian
     */
    private static function multiIntents(string $q): array
    {
        if (mb_strlen($q) > 160) {
            return [];
        }
        $parts = preg_split('/\s+(?:dan juga|serta|sekalian|terus|trus|lalu|dan)\s+/u', $q) ?: [];
        if (count($parts) < 2) {
            return [];
        }
        $out = [];
        foreach (array_slice($parts, 0, 3) as $part) {
            $part = trim((string) $part);
            if (mb_strlen($part) < 5) {
                continue;
            }
            $intent = self::bestIntent($part);
            if ($intent === null || str_starts_with($intent, 'ambig:')) {
                return [];
            }
            if (in_array($intent, ['greeting', 'thanks', 'about', 'capabilities', 'help'], true)) {
                return [];
            }
            if (! isset($out[$intent])) {
                $out[$intent] = $part;
            }
        }
        return count($out) >= 2 ? $out : [];
    }

    /**
     * File pengetahuan opsional — admin dapat "melatih" asisten tanpa kode.
     * Format storage/app/assistant-knowledge.md:
     *   ## kata kunci | frasa lain | dst
     *   Jawaban bebas (boleh multi-baris). {nama} = nama depan pengguna.
     */
    private static array $kbCache = ['mtime' => 0, 'entries' => []];

    private static function knowledgeAnswer(string $q): ?string
    {
        $path = storage_path('app/assistant-knowledge.md');
        if (! is_file($path)) {
            return null;
        }
        $mtime = (int) @filemtime($path);
        if ($mtime !== self::$kbCache['mtime']) {
            $entries = [];
            $kwLine = null;
            foreach (preg_split('/\R/', (string) @file_get_contents($path)) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '//')) {
                    continue;
                }
                if (str_starts_with($line, '##')) {
                    $kws = array_filter(array_map(
                        fn ($k) => mb_strtolower(trim($k)),
                        preg_split('/\|/', substr($line, 2)) ?: []
                    ));
                    $kwLine = $kws !== [] ? $kws : null;
                    continue;
                }
                if ($kwLine !== null) {
                    foreach ($kwLine as $kw) {
                        $entries[$kw] = isset($entries[$kw]) ? $entries[$kw] . ' ' . $line : $line;
                    }
                }
            }
            self::$kbCache = ['mtime' => $mtime, 'entries' => $entries];
        }

        $best = null;
        $bestScore = 0;
        foreach (self::$kbCache['entries'] as $kw => $answer) {
            if ($kw !== '' && str_contains($q, $kw)) {
                $score = mb_strlen($kw);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $answer;
                }
            }
        }
        return $best;
    }

    private static function handle(string $intent, User $user, string $q, string $raw, array $sched, string $first): string
    {
        return match ($intent) {
            'greeting' => self::rGreeting($first),
            'thanks' => self::rThanks($first),
            'capabilities' => self::rCapabilities($first),
            'about' => self::rAbout($first),
            'help' => self::rHelp($first),
            'contact' => self::rContact($first),
            'team' => self::rTeam($user, $q, $first),
            'room_howto' => self::rRoomHowto($user, $first),
            'room_status' => self::rRoomStatus($user, $first),
            'room_access' => self::rRoomAccess($user, $first),
            'room_list' => self::rRoomList($first),
            'room_location' => self::rRoomLocation($q, $first),
            'card_lost' => self::rCardLost($user, $first),
            'attend_today' => self::rAttendToday($user, $q, $sched, $first),
            'attend_rekap' => self::rAttendRekap($user, $sched, $first),
            'attend_rules' => self::rAttendRules($first),
            'schedule' => self::rSchedule($user, $sched, $first),
            'calendar' => self::rCalendar($first),
            'fingerprint' => self::rFingerprint($user, $first),
            'account_pw' => self::rAccountPw($user, $first),
            'account_login' => self::rAccountLogin($user, $first),
            'account_data' => self::rAccountData($user, $first),
            'report_status' => self::rReportStatus($user, $first),
            'issue' => self::rIssue($q, $first),
            'upload_help' => self::rUploadHelp($first),
            'frustration' => self::rFrustration($user, $first),
            'announcement' => self::rAnnouncement($user, $first),
            default => self::rFallback($user, $raw, $first),
        };
    }

    /** Label topik yang mudah dibaca untuk pertanyaan klarifikasi. */
    private const INTENT_LABELS = [
        'report_status' => 'status/balasan laporan Anda',
        'room_status' => 'status pengajuan ruangan',
        'room_howto' => 'cara mengajukan ruangan',
        'room_access' => 'akses pintu/kartu/PIN',
        'room_location' => 'lokasi/denah ruangan',
        'card_lost' => 'kartu akses hilang/rusak',
        'team' => 'tim PBL Anda',
        'attend_today' => 'absensi Anda',
        'attend_rekap' => 'rekap absensi bulanan',
        'attend_rules' => 'aturan absensi',
        'issue' => 'melaporkan kendala',
        'upload_help' => 'kendala upload foto/lampiran',
        'account_pw' => 'password & keamanan akun',
        'account_login' => 'masalah login',
        'account_data' => 'data profil Anda',
        'fingerprint' => 'sidik jari di mesin',
        'announcement' => 'pengumuman terbaru',
        'schedule' => 'jadwal Anda',
        'calendar' => 'kalender',
        'room_list' => 'daftar ruangan',
        'contact' => 'kontak bantuan',
        'help' => 'bantuan umum',
        'frustration' => 'keluhan/frustrasi',
        'capabilities' => 'kemampuan asisten',
        'about' => 'tentang asisten',
    ];

    private static function rClarify(string $a, string $b, string $first): string
    {
        $la = self::INTENT_LABELS[$a] ?? $a;
        $lb = self::INTENT_LABELS[$b] ?? $b;
        return "Biar saya jawab tepat, {$first} — maksud Anda tentang **{$la}** atau **{$lb}**? "
            . 'Balas dengan salah satu topiknya, misal: "status laporan saya".';
    }

    private static function rFrustration(User $user, string $first): string
    {
        $pending = Suggestion::where('user_id', $user->id)
            ->whereNull('admin_reply')->whereIn('status', ['baru', 'dibaca'])->count();
        $cek = $pending > 0
            ? "Saya cek: ada **{$pending} laporan Anda yang masih menunggu balasan** Admin Laboran. "
                . 'Balasan biasanya masuk pada jam kerja — Anda akan dapat notifikasi begitu dibalas.'
            : 'Saya tidak melihat laporan Anda yang masih menunggu balasan.';
        return "Saya paham ini bikin frustrasi, {$first} — maaf sudah bikin Anda menunggu. {$cek}\n\n"
            . "Supaya cepat ditindaklanjuti:\n"
            . "- Ceritakan kendalanya di sini — saya rangkum jadi **tiket resmi** ke Admin Laboran.\n"
            . "- Atau datang langsung ke ruang laboran pada jam kerja.\n\n"
            . 'Mau saya buatkan draft laporannya?';
    }

    private static function rGreeting(string $first): string
    {
        return "Halo **{$first}**! Senang bisa membantu Anda hari ini.\n\n"
            . 'Ada yang ingin ditanyakan — jadwal & absensi, tim PBL, pengajuan ruangan, akun, atau mau lapor kendala ke Laboran?';
    }

    private static function rThanks(string $first): string
    {
        return "Sama-sama, **{$first}**! Senang bisa membantu. Kalau ada kendala lain, jangan ragu bertanya lagi ya.";
    }

    private static function rCapabilities(string $first): string
    {
        return "Halo **{$first}**! Saya asisten virtual SmartBrail. Yang bisa saya bantu:\n"
            . "- **Jadwal & absensi** — jam tap, hari wajib, status kehadiran Anda hari ini.\n"
            . "- **Tim PBL** — cek penugasan, anggota, dan Manpro Anda.\n"
            . "- **Ruangan** — cara mengajukan, cek status, aturan akses pintu.\n"
            . "- **Akun** — password, login, kontak, sidik jari.\n"
            . "- **Laporan** — rangkum kendala jadi tiket resmi ke Admin Laboran.\n\n"
            . 'Ketik pertanyaan langsung atau pilih opsi cepat di atas ya!';
    }

    private static function rAbout(string $first): string
    {
        return "**SmartBrail** adalah portal mahasiswa untuk Laboratorium Terpadu Polibatam, {$first}. "
            . "Di sini Anda bisa:\n"
            . "- Tap absensi lewat mesin sidik jari dan pantau rekapnya.\n"
            . "- Mengajukan pemakaian ruangan atas nama tim PBL.\n"
            . "- Melihat kalender, pengumuman, dan panduan penggunaan.\n"
            . "- Melapor kendala ke Admin Laboran lewat tiket resmi.";
    }

    private static function rHelp(string $first): string
    {
        return "Cara pakai portal, {$first}:\n\n"
            . "- **Beranda** — ringkasan absensi hari ini, kalender, dan agenda terdekat.\n"
            . "- **Absensi → Riwayat** — rekap kehadiran lengkap Anda.\n"
            . "- **Ruangan** — ajukan ruangan (maks. H-1 sebelum dipakai) dan pantau statusnya.\n"
            . "- **Tim PBL** — info tim, anggota, dan Manpro.\n"
            . "- **Laporan & Bantuan** — buat tiket kendala ke Laboran.\n"
            . "- **Profil** — foto, kontak, dan keamanan akun.\n\n"
            . 'Versi lengkapnya ada di menu **Panduan** di sidebar.';
    }

    private static function rContact(string $first): string
    {
        return "Dua jalur resmi untuk minta bantuan, {$first}:\n\n"
            . "1. **Laporan resmi** — menu **Laporan & Bantuan** di sidebar (atau tombol laporan di chat ini). Tiket masuk ke Admin Laboran dan statusnya bisa dipantau.\n"
            . "2. **Langsung** — temui laboran di ruang lab saat jam operasional, atau hubungi dosen pengampu untuk urusan akademik/PBL.\n\n"
            . 'Ceritakan saja kendalanya di sini — saya bisa rangkum jadi laporan resmi otomatis.';
    }

    private static function rAttendRules(string $first): string
    {
        return "Ketentuan waktu absensi lab:\n\n"
            . "- **Tap Masuk:** mesin mulai menerima tap sejak **06:30 WIB**; jadwal masuk resmi **07:50 WIB**.\n"
            . "- **Terlambat:** tap setelah 07:50 **tetap tercatat hadir**, hanya ada catatan menit keterlambatan.\n"
            . "- **Tap Pulang:** setelah **17:00 WIB** saat jam kuliah berakhir.\n"
            . "- **Status:** tap masuk + pulang = **Lengkap**; lupa tap pulang = **Tidak Lengkap**; tidak tap sama sekali di hari wajib = **Alfa**.";
    }

    private static function rUploadHelp(string $first): string
    {
        return "Untuk foto bukti laporan: buat laporan lewat tombol \"Laporkan Kendala\" di chat ini atau menu Saran & Masukan — di kartu laporan ada **\"Lampirkan foto bukti\"**. Aturannya: JPG/PNG/WebP, **maks 3 MB**, satu foto per laporan. Tunggu preview muncul baru kirim.\n\n"
            ."Kalau gagal terus, biasanya karena: (1) ukuran foto terlalu besar — kompres dulu, (2) format tidak didukung — ubah ke JPG/PNG, atau (3) koneksi putus saat upload — coba lagi. {$first} juga bisa kirim laporan tanpa foto dan menjelaskan kendalanya di teks; lampiran bisa menyusul lewat laporan berikutnya.";
    }

    private static function rIssue(string $q, string $first): string
    {
        if (preg_match('/(absen|absensi|kehadiran|tap|finger|sidik|alfa|telat|terlambat)/i', $q)) {
            return "Halo **{$first}**! Tentu, saya siap membantu kendala absensi Anda.\n\n"
                . "Sebagai asisten virtual, saya **tidak mengirim laporan** atau mengubah rekap absensi secara sepihak tanpa konfirmasi Anda.\n\n"
                . 'Saya sudah menyiapkan kartu tiket laporan resmi di bawah — kendala Anda akan diteruskan ke Admin Laboran untuk dicocokkan dengan rekaman log mesin.';
        }
        return "Halo **{$first}**! Tentu, saya siap membantu kendala Anda.\n\n"
            . "Sebagai asisten virtual, saya **tidak mengirim laporan** apa pun secara sepihak tanpa konfirmasi Anda.\n\n"
            . 'Saya sudah menyiapkan kartu tiket laporan resmi di bawah — silakan periksa lalu kirim agar segera ditangani Admin Laboran.';
    }

    private static function rFallback(User $user, string $raw, string $first): string
    {
        // Catat pertanyaan yang belum terjawab — bahan "pelatihan" intent berikutnya.
        $line = '[' . now('Asia/Jakarta')->format('Y-m-d H:i') . "] u{$user->id}: "
            . mb_substr(preg_replace('/\s+/u', ' ', trim($raw)), 0, 200) . "\n";
        @file_put_contents(storage_path('logs/assistant-unmatched.log'), $line, FILE_APPEND | LOCK_EX);

        return "Hmm, saya belum sepenuhnya paham maksud \"{$raw}\", {$first}.\n\n"
            . "Coba pilih yang paling dekat dengan kebutuhan Anda:\n"
            . "- **Jadwal & absensi** — jam tap, hari wajib, status kehadiran.\n"
            . "- **Tim PBL** — penugasan, anggota, dan aturan tim.\n"
            . "- **Ruangan** — cara mengajukan, status pengajuan, akses pintu.\n"
            . "- **Akun** — password, login, dan keamanan.\n"
            . "- **Lapor kendala** — saya rangkum jadi tiket resmi ke Laboran.\n\n"
            . 'Atau ketik ulang dengan kalimat yang lebih spesifik ya!';
    }

    private static function rTeam(User $user, string $q, string $first): string
    {
        $tim = RuanganBookingService::timMahasiswa($user);
        if (! $tim) {
            return "Saya cek datanya, **{$first}** — Anda memang **belum ditugaskan ke tim PBL** mana pun saat ini.\n\n"
                . "Penugasan tim dilakukan oleh **dosen pengampu** atau **laboran/admin**, bukan dari sisi mahasiswa. Yang bisa Anda lakukan:\n"
                . "1. Konfirmasi langsung ke dosen pengampu mata kuliah PBL Anda.\n"
                . "2. Atau kirim laporan resmi ke Admin Laboran — kartu laporannya sudah saya siapkan di bawah.\n\n"
                . 'Begitu ditugaskan, halaman **Tim PBL** akan menampilkan nama tim, anggota, dan Manpro Anda.';
        }
        $anggota = RuanganBookingService::anggotaTim($tim);
        $manpro = $tim->manproUser?->nama_lengkap ?? $tim->manpro ?? '—';
        $kode = $tim->kode ? " ({$tim->kode})" : '';
        $aturan = preg_match('/(aturan|ketentuan|syarat|rule|hak|boleh)/i', $q)
            ? "\n\nAturan tim PBL: pengajuan ruangan dilakukan atas nama tim (paling lambat **H-1** sebelum pemakaian), diputuskan laboran/dosen, dan akses pintu hanya aktif selama izin berjalan."
            : '';
        return "Anda terdaftar di tim **{$tim->nama}{$kode}**, {$first}.\n\n"
            . "- **Manpro:** {$manpro}\n"
            . "- **Anggota:** {$anggota->count()} orang\n\n"
            . 'Detail lengkap — termasuk profil anggota tim — ada di menu **Tim PBL**.' . $aturan;
    }

    private static function rRoomHowto(User $user, string $first): string
    {
        $tim = RuanganBookingService::timMahasiswa($user);
        $syarat = $tim
            ? "Tim Anda (**{$tim->nama}**) sudah bisa mengajukan — tinggal pilih jadwalnya."
            : '**Catatan:** pengajuan ruangan dilakukan atas nama tim PBL — Anda belum punya tim, jadi minta penugasan ke dosen/laboran dulu ya.';
        return "Cara mengajukan ruangan lab, {$first}:\n\n"
            . "1. Buka menu **Ruangan → Ajukan Ruangan** (atau kartu ruangan di chat ini).\n"
            . "2. Pilih ruangan, tanggal, jam, dan tujuan pemakaian. Ingat: pengajuan paling lambat **H-1** sebelum pemakaian — tidak bisa untuk hari yang sama.\n"
            . "3. Laboran/dosen akan memutuskan — pantau statusnya di **Ruangan → Riwayat**.\n\n"
            . $syarat;
    }

    private static function rRoomStatus(User $user, string $first): string
    {
        $tim = RuanganBookingService::timMahasiswa($user);
        $p = RuanganPengajuan::with(['ruangan:id,nama', 'diputusOleh:id,nama_lengkap,role'])
            ->when($tim, fn ($qq) => $qq->where('pbl_project_id', $tim->id))
            ->when(! $tim, fn ($qq) => $qq->where('pengaju_id', $user->id))
            ->latest('tanggal')->latest('jam_mulai')
            ->first();

        if (! $p) {
            return "Anda belum punya pengajuan ruangan saat ini, {$first}. Buka menu **Ruangan → Ajukan Ruangan** untuk membuatnya — atau katakan \"ajukan ruangan\" dan saya pandu langkahnya.";
        }

        $efek = $p->statusEfektif();
        $label = [
            'diajukan' => 'sedang **menunggu keputusan Laboran**',
            'menunggu_penerimaan' => 'mendapat **tawaran jadwal pengganti** — segera terima/tolak di halaman Ruangan',
            'disetujui' => 'sudah **DISETUJUI** — ruangan bisa dipakai sesuai jadwal',
            'ditolak' => '**ditolak**',
            'dibatalkan' => '**dibatalkan**',
            'selesai' => 'sudah **selesai** digunakan',
            'kedaluwarsa' => '**kedaluwarsa** karena melewati jadwal',
        ][$efek] ?? $efek;
        $ruang = $p->ruangan?->nama ?? 'ruangan';
        $jadwal = optional($p->tanggal)->translatedFormat('d M Y') . ' pukul '
            . substr((string) $p->jam_mulai, 0, 5) . '–' . substr((string) $p->jam_selesai, 0, 5);
        $by = $p->diputusOleh
            ? ' oleh **' . $p->diputusOleh->nama_lengkap . '** (' . ($p->diputusOleh->role === 'dosen' ? 'Dosen' : 'Laboran') . ')'
            : '';
        $alasan = $p->alasan_keputusan ? "\n- Catatan: {$p->alasan_keputusan}" : '';

        return "Status pengajuan terakhir tim Anda:\n\n"
            . "- **{$ruang}** — {$jadwal}\n"
            . "- Status: {$label}{$by}.{$alasan}\n\n"
            . 'Detail lengkap ada di menu **Ruangan → Riwayat Pengajuan**.';
    }

    private static function rRoomAccess(User $user, string $first): string
    {
        $kartu = $user->rfid_uid
            ? "Nomor kartu akses Anda: **{$user->rfid_uid}**."
            : 'Anda belum punya nomor kartu akses — minta laboran menerbitkannya.';
        return "Tentang akses masuk ruangan:\n\n"
            . "- {$kartu}\n"
            . "- Ruangan hanya bisa dibuka (kartu/PIN/sidik jari) **selama Anda punya izin aktif** — pengajuan disetujui dan sedang berlangsung. Tanpa izin, mesin menolak tap apa pun.\n"
            . "- Semua tap tercatat otomatis, jadi selalu ada bukti siapa membuka ruangan kapan.\n"
            . '- Punya izin tapi mesin menolak? Laporkan lewat tombol laporan di chat ini agar dicek laboran.';
    }

    private static function rRoomList(string $first): string
    {
        $rooms = Ruangan::where('aktif', true)->orderBy('nama')->limit(8)->get(['nama', 'kapasitas']);
        if ($rooms->isEmpty()) {
            return "Daftar ruangan lab belum termuat, {$first} — coba buka menu **Ruangan** untuk melihatnya langsung.";
        }
        $list = $rooms->map(fn (Ruangan $r) => "- **{$r->nama}**" . ($r->kapasitas ? " — kapasitas {$r->kapasitas} orang" : ''))->implode("\n");
        return "Ruangan lab yang bisa diajukan:\n\n{$list}\n\nDetail jam operasional & aturannya ada di menu **Ruangan**.";
    }

    private static function rRoomLocation(string $q, string $first): string
    {
        $rooms = Ruangan::where('aktif', true)->orderBy('nama')->get(['nama', 'lokasi', 'fasilitas']);
        if ($rooms->isEmpty()) {
            return "Info lokasi ruangan belum termuat, {$first} — coba buka menu **Ruangan** langsung.";
        }
        // Ruangan spesifik disebut di pertanyaan? Jawab langsung.
        foreach ($rooms as $r) {
            $nama = mb_strtolower((string) $r->nama);
            if ($nama !== '' && str_contains($q, $nama)) {
                $lok = $r->lokasi ?: 'lokasi belum diisi';
                $fasArr = is_array($r->fasilitas) ? $r->fasilitas : (array) $r->fasilitas;
                $fasTxt = implode(', ', array_filter($fasArr));
                $fas = $fasTxt !== '' ? "\n- Fasilitas: {$fasTxt}" : '';
                return "**{$r->nama}** berada di **{$lok}**.{$fas}\n\nDenah & aturan ruangan ada di menu **Ruangan**.";
            }
        }
        $list = $rooms->take(8)->map(function (Ruangan $r) {
            $lok = $r->lokasi ? " — {$r->lokasi}" : '';
            return "- **{$r->nama}**{$lok}";
        })->implode("\n");
        return "Lokasi ruangan lab, {$first}:\n\n{$list}\n\nDetail denah & aturan tiap ruangan ada di menu **Ruangan**.";
    }

    private static function rCardLost(User $user, string $first): string
    {
        $kartu = $user->rfid_uid ? " (nomor saat ini: **{$user->rfid_uid}**)" : '';
        return "Kalau kartu akses Anda hilang atau rusak, {$first}:\n\n"
            . "1. **Segera laporkan** ke Admin Laboran — lewat tiket laporan di chat ini atau datang langsung — supaya kartu lama dinonaktifkan dan tidak dipakai orang lain.\n"
            . "2. Admin akan menerbitkan kartu pengganti dan memperbarui nomor akses akun Anda{$kartu}.\n"
            . "3. Sementara menunggu, akses ruangan tetap butuh izin aktif — tanyakan alternatif (PIN) ke laboran.\n\n"
            . 'Mau saya buatkan draft laporan kehilangan kartunya sekarang?';
    }

    private static function rAttendToday(User $user, string $q, array $sched, string $first): string
    {
        $isKemarin = str_contains($q, 'kemarin');
        $tanggal = Carbon::now('Asia/Jakarta')->subDays($isKemarin ? 1 : 0)->toDateString();
        $rec = AbsensiHarian::with(['masukLog', 'pulangLog'])
            ->where('user_id', $user->id)
            ->whereDate('tanggal', $tanggal)
            ->first();

        $hariLabel = $isKemarin ? 'kemarin' : 'hari ini';
        $statusHari = $sched['label_status_hari'] ?? 'jadwal lab';

        if (! $rec) {
            return "Absensi {$hariLabel} ({$tanggal}), {$first}:\n\n"
                . "- Belum ada catatan tap mesin untuk Anda.\n"
                . "- Status jadwal: **{$statusHari}**\n\n"
                . "Kalau Anda merasa sudah tap, kemungkinan tap-nya tidak terbaca — laporkan lewat tombol laporan di bawah agar dicek laboran.";
        }

        $masuk = $rec->masukLog?->waktu_tap?->timezone('Asia/Jakarta')->format('H:i');
        $pulang = $rec->pulangLog?->waktu_tap?->timezone('Asia/Jakarta')->format('H:i');
        $statusLabel = match ((string) $rec->status) {
            'lengkap' => 'Lengkap', 'hadir' => 'Hadir', 'alfa' => 'Alfa',
            'tidak_lengkap' => 'Tidak lengkap', default => $rec->status ?: 'Tercatat',
        };
        $telat = $rec->menit_terlambat_masuk ? " (terlambat {$rec->menit_terlambat_masuk} mnt)" : '';

        return "Absensi {$hariLabel} ({$tanggal}), {$first}:\n\n"
            . '- **Tap masuk:** ' . ($masuk ? "{$masuk} WIB{$telat}" : 'belum ada') . "\n"
            . '- **Tap pulang:** ' . ($pulang ? "{$pulang} WIB" : 'belum ada') . "\n"
            . "- **Status:** {$statusLabel}\n\n"
            . 'Rekap lengkap ada di menu **Absensi → Riwayat**.';
    }

    private static function rAttendRekap(User $user, array $sched, string $first): string
    {
        $start = Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString();
        $rows = AbsensiHarian::where('user_id', $user->id)
            ->whereDate('tanggal', '>=', $start)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        if ($rows->isEmpty()) {
            return "Belum ada catatan absensi bulan ini, {$first}. Rekap akan muncul setelah mesin mencatat tap Anda — lihat menu **Absensi → Riwayat**.";
        }
        $total = $rows->sum();
        $lengkap = (int) ($rows['lengkap'] ?? 0);
        $tidakLengkap = (int) ($rows['tidak_lengkap'] ?? 0);
        $alfa = (int) ($rows['alfa'] ?? 0);
        $lain = $total - $lengkap - $tidakLengkap - $alfa;

        return "Rekap absensi bulan ini, {$first}:\n\n"
            . "- **Total tercatat:** {$total} hari\n"
            . "- **Lengkap:** {$lengkap} · **Tidak lengkap:** {$tidakLengkap} · **Alfa:** {$alfa}" . ($lain ? " · **Lainnya:** {$lain}" : '') . "\n\n"
            . 'Rincian per tanggal ada di menu **Absensi → Riwayat** — dan kalau ada yang salah, saya bisa bantu buatkan laporan koreksi.';
    }

    private static function rSchedule(User $user, array $sched, string $first): string
    {
        $hariNama = $sched['hari_nama'] ?? 'Hari ini';
        $labelJadwal = $sched['label_jadwal'] ?? '07:50 - 17:00 WIB';
        $statusHari = $sched['label_status_hari'] ?? 'Jadwal Reguler';
        $isWajib = (bool) ($sched['is_wajib'] ?? false);
        $info = $isWajib
            ? "Hari ini (**{$hariNama}**) merupakan **hari wajib absensi lab** ({$labelJadwal}). Jangan lupa tap masuk dan tap pulang ya!"
            : "Hari ini (**{$hariNama}**) **bukan hari wajib absensi lab** ({$statusHari}). Tidak ada risiko alfa hari ini.";

        return "Informasi jadwal lab untuk **{$first}**:\n\n"
            . '- **Kebijakan saat ini:** ' . AbsensiKebijakan::labelMode() . ".\n"
            . "- **Status hari ini:** {$info}\n"
            . '- Jadwal lengkap dan hari libur bisa dicek di widget **Kalender** pada Beranda.';
    }

    private static function rCalendar(string $first): string
    {
        return "Soal kalender & agenda, {$first}:\n\n"
            . "- **Kalender Beranda** — klik nama bulannya untuk lompat ke bulan mana pun, tombol **Hari Ini** kembali ke tanggal sekarang.\n"
            . "- **Agenda Terdekat** — menampilkan agenda 1 bulan ke depan dari bulan yang sedang dibuka; titik prioritas berkedip sesuai tingkat kepentingan.\n"
            . '- Klik tanggal mana pun untuk melihat detail kegiatan hari itu.';
    }

    private static function rFingerprint(User $user, string $first): string
    {
        $status = $user->finger_id
            ? "ID sidik jari Anda tercatat aktif di server (**#{$user->finger_id}**)."
            : 'Data sidik jari Anda **belum tersimpan** di server.';
        return "Status biometrik & sensor lab:\n\n"
            . "- {$status}\n"
            . "- **Cara tap:** tempelkan jari terdaftar secara mantap pada sensor sampai indikator menyala hijau.\n"
            . "- **Belum/baru:** perekaman (enroll) dilakukan laboran di mesin lab — datang saat jam operasional.\n"
            . '- **Sering gagal terbaca:** minta laboran kalibrasi/rekam ulang, atau laporkan lewat tombol laporan di chat ini.';
    }

    private static function rAccountPw(User $user, string $first): string
    {
        if ($user->must_change_password) {
            return "Akun Anda masih memakai **password sementara** dari admin, {$first} — itu sebabnya ada banner dan titik kuning di aplikasi.\n\n"
                . "Tenang, Anda **tetap bebas memakai semua fitur** seperti biasa. Tapi sebaiknya segera amankan akun:\n"
                . "1. Klik avatar kanan atas → **Keamanan**, atau Profil → Ubah Password.\n"
                . "2. Buat password pribadi baru (min. 8 karakter, kombinasi huruf & angka).\n\n"
                . 'Setelah diganti, peringatan hilang. Kalau password sementaranya sudah tidak berlaku, minta laboran membuatkan kredensial baru — atau kirim laporan lewat tombol di bawah.';
        }
        return "Tentang password akun Anda (**{$user->nitk_nidn}**):\n\n"
            . "- **Ganti password:** avatar kanan atas → **Keamanan**, atau Profil → Ubah Password.\n"
            . "- **Lupa password:** pakai pemulihan OTP ke email terverifikasi di halaman Profil.\n"
            . '- Password yang baik: min. 8 karakter, kombinasi huruf besar, kecil, dan angka.';
    }

    private static function rAccountLogin(User $user, string $first): string
    {
        $lock = method_exists($user, 'isLoginTemporarilyLocked') && $user->isLoginTemporarilyLocked();
        $extra = $lock
            ? "\n\nStatus akun Anda **sedang terkunci sementara** karena percobaan login gagal — tunggu beberapa menit atau minta laboran membukanya lebih cepat."
            : '';
        return "Kendala login biasanya karena salah satu ini, {$first}:\n\n"
            . "- **NIM atau password salah** — cek ulang huruf besar/kecil.\n"
            . "- **Akun terkunci** setelah beberapa kali salah password — tunggu atau minta laboran buka kunci.\n"
            . "- **Password sementara kedaluwarsa** — minta laboran membuatkan kredensial baru.\n"
            . "- **Akun nonaktif** — hanya admin yang bisa mengaktifkan kembali.{$extra}\n\n"
            . 'Kalau tetap gagal, kirim laporan lewat tombol di bawah — laboran akan bantu cek.';
    }

    private static function rAccountData(User $user, string $first): string
    {
        return "Mengelola data akun Anda (**{$user->nitk_nidn}**), {$first}:\n\n"
            . "- **Email & no. HP/WA** — ubah di halaman **Profil Saya**; email dipakai untuk OTP pemulihan akun.\n"
            . "- **Foto profil** — unggah di Profil; tampil juga di kartu tim PBL Anda.\n"
            . "- **Verifikasi email** — ikuti tautan OTP yang dikirim ke email Anda.\n\n"
            . 'Semua perubahan langsung tersimpan — tidak perlu persetujuan admin.';
    }

    private static function rReportStatus(User $user, string $first): string
    {
        $items = Suggestion::where('user_id', $user->id)->latest('id')->limit(3)->get();
        if ($items->isEmpty()) {
            return "Anda belum punya laporan, {$first}. Kalau ada kendala, ceritakan di sini — saya rangkum jadi tiket resmi ke Admin Laboran.";
        }

        $statusLabel = fn (string $s) => match ($s) {
            'baru' => 'Menunggu', 'dibaca' => 'Diproses', default => 'Selesai',
        };

        // Jawab langsung status laporan terbaru dulu — paling relevan untuk "tadi".
        $latest = $items->first();
        $ref = '#' . str_pad((string) $latest->id, 4, '0', STR_PAD_LEFT);
        $replied = filled($latest->admin_reply);
        $replier = $latest->repliedBy?->nama_lengkap ?? 'Admin Laboran';
        $waktu = $latest->admin_replied_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i');

        $head = $replied
            ? "**Sudah dijawab**, {$first}! Laporan terbaru Anda ({$ref}) ditanggapi oleh **{$replier}**" . ($waktu ? " pada {$waktu} WIB" : '') . ":\n\n"
                . '"' . \Illuminate\Support\Str::limit(trim((string) $latest->admin_reply), 160) . '"' . "\n\n"
            : "**Belum dijawab**, {$first}. Laporan terbaru Anda ({$ref}) berstatus **{$statusLabel((string) $latest->status)}** — "
                . "Admin Laboran biasanya menanggapi pada jam kerja; Anda akan dapat notifikasi begitu dibalas.\n\n";

        $lainnya = $items->slice(1)->map(function (Suggestion $s) use ($statusLabel) {
            $r = '#' . str_pad((string) $s->id, 4, '0', STR_PAD_LEFT);
            $balas = $s->admin_reply ? ' ✓ dibalas' : '';
            return "- {$r} · {$statusLabel((string) $s->status)}{$balas}";
        })->implode("\n");

        return $head
            . ($lainnya !== '' ? "Laporan lainnya:\n{$lainnya}\n\n" : '')
            . 'Detail & balasan lengkap ada di **Laporan & Bantuan → Riwayat Laporan**.';
    }

    private static function rAnnouncement(User $user, string $first): string
    {
        $items = Announcement::active()
            ->forAudience($user)
            ->latest('id')->limit(3)->get(['judul', 'created_at']);
        if ($items->isEmpty()) {
            return "Belum ada pengumuman untuk Anda saat ini, {$first}. Pengumuman baru akan muncul di lonceng notifikasi kanan atas.";
        }
        $list = $items->map(fn (Announcement $a) => '- **' . $a->judul . '** — ' . $a->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y'))->implode("\n");
        return "Pengumuman terbaru:\n\n{$list}\n\nSemua pengumuman bisa dibaca lewat ikon lonceng di kanan atas.";
    }
}
