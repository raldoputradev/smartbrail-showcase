<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HelpdeskAiService
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{reply: string, source: string}
     */
    public function reply(string $message, array $history = []): array
    {
        $message = trim(Str::limit($message, 500, ''));
        if ($message === '') {
            return [
                'reply' => 'Halo! 👋 Ketik aja pertanyaanmu soal AIoT SIMALAS ya~',
                'source' => 'faq',
            ];
        }

        $apiKey = trim((string) config('services.gemini.api_key', ''));
        if ($apiKey !== '') {
            try {
                return [
                    'reply' => $this->replyWithGemini($message, $history, $apiKey),
                    'source' => 'gemini',
                ];
            } catch (\Throwable $e) {
                Log::warning('Helpdesk Gemini gagal', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'reply' => $this->replyFromKnowledge($message)
                        ."\n\n_(Psst, AI Gemini lagi offline bentar — jawaban di atas dari FAQ lokal ya 🙏)_",
                    'source' => 'faq',
                ];
            }
        }

        return [
            'reply' => $this->replyFromKnowledge($message),
            'source' => 'faq',
        ];
    }

    public function isGeminiConfigured(): bool
    {
        return trim((string) config('services.gemini.api_key', '')) !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    private function replyWithGemini(string $message, array $history, string $apiKey): string
    {
        $primary = (string) config('services.gemini.model', 'gemini-flash-latest');
        $fallbacks = config('services.gemini.fallback_models', []);
        if (! is_array($fallbacks)) {
            $fallbacks = [];
        }

        $models = array_values(array_unique(array_filter(array_merge([$primary], $fallbacks))));

        $contents = [];
        foreach (array_slice($history, -6) as $row) {
            $role = ($row['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $text = trim((string) ($row['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt()]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.75,
                'maxOutputTokens' => 800,
            ],
        ];

        $lastError = 'Gemini unavailable';

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

            $response = Http::timeout(30)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, $payload);

            if (! $response->successful()) {
                $lastError = 'Gemini HTTP '.$response->status().' ('.$model.'): '.Str::limit((string) $response->body(), 220);
                Log::info('Helpdesk Gemini model gagal, coba berikutnya', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);
                continue;
            }

            $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text'));
            if ($text === '') {
                $lastError = 'Gemini empty reply ('.$model.')';
                continue;
            }

            return Str::limit($text, 1500, '…');
        }

        throw new \RuntimeException($lastError);
    }

    private function replyFromKnowledge(string $message): string
    {
        $q = Str::lower($message);

        foreach ($this->faq() as $item) {
            foreach ($item['keys'] as $key) {
                if (Str::contains($q, $key)) {
                    return $item['a'];
                }
            }
        }

        return 'AIoT SIMALAS itu sistem lab pintar di BRAIL Polibatam 🔥 absensi sidik jari ESP32, loker IoT, plus app mobile mahasiswa. '
            .'Coba tanya: cara unduh APK, login mahasiswa, enroll sidik jari, atau lokasi lab ya ✨ '
            .'(Biar makin natural, admin bisa isi GEMINI_API_KEY di server.)';
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
Kamu adalah CS / helpdesk AI resmi AIoT SIMALAS (Smart Laboratory Management System) di Politeknik Negeri Batam, BRAIL.

GAYA NGOMONG (WAJIB):
- Santai, gaul, ramah kayak temen kampus — bukan robot kaku / formal bertele-tele.
- Pakai Bahasa Indonesia kasual (boleh “ya”, “sih”, “dong”, “nih”, “banget”, “oke”, “gampang”).
- SELALU sisipkan 1–4 emoji yang relevan di jawaban (jangan berlebihan spam).
- Jawaban pendek & enak dibaca: 2–5 kalimat, boleh pakai bullet singkat kalau perlu langkah.
- Jangan kaku kayak dokumen resmi. Tetap sopan, jangan kasar / toxic / SARA.

TOPIK:
- Hanya bahas SIMALAS: absensi biometrik, loker pintar, app mobile mahasiswa, portal dosen/laboran, unduh APK, OTP, enroll sidik jari, lokasi lab, siapa pengembang.
- Kalau ditanya “kamu siapa”: bilang kamu asisten AI helpdesk AIoT SIMALAS yang siap bantu.
- Kalau di luar topik: tolak santai + arahkan balik ke SIMALAS.

Kepanjangan BRAIL (WAJIB benar):
- BRAIL = Barelang Robotics and Artificial Intelligence Lab.
- JANGAN tulis “Brain and AI Lab” atau kepanjangan salah lain.
- Cukup sebut “BRAIL” atau “BRAIL Polibatam” (jangan “BRAIL Lab” — Lab sudah termasuk di kepanjangan).

Fakta pengembang:
- Dikerjakan oleh tim AIoT SIMALAS di bawah BRAIL, Politeknik Negeri Batam.
- Jangan sebut nama individu kecuali user minta eksplisit.
- Jangan mengarang daftar anggota.

Fakta penting:
- Mahasiswa pakai aplikasi Android (bukan login web publik).
- Dosen & laboran login di portal web staff.
- APK dari halaman Unduh resmi situs.
- BRAIL ada di Politeknik Negeri Batam, Batam, Kepri.
- Sidik jari di mesin ESP32 di lab setelah akun disetujui admin.
Jangan mengarang fitur yang tidak disebutkan.
TXT;
    }

    /**
     * @return list<array{keys: list<string>, a: string}>
     */
    private function faq(): array
    {
        return [
            [
                'keys' => ['siapa kamu', 'kamu siapa', 'who are you', 'asisten'],
                'a' => 'Haii! 👋 Aku asisten AI helpdesk AIoT SIMALAS. Tinggal tanya aja soal absensi sidik jari, loker IoT, unduh APK, atau cara pakai sistem di BRAIL Polibatam~ ✨',
            ],
            [
                'keys' => ['siapa yang bikin', 'siapa pembuat', 'siapa develop', 'developer', 'pengembang', 'dibuat siapa', 'yang kerjakan', 'dibuat oleh siapa', 'tim aiot'],
                'a' => 'Yang bikin AIoT SIMALAS adalah tim AIoT SIMALAS di bawah BRAIL (Barelang Robotics and Artificial Intelligence Lab), Politeknik Negeri Batam 🛠️ Mulai dari app mobile, portal web, API, sampe mesin IoT-nya!',
            ],
            [
                'keys' => ['brail', 'kepanjangan brail', 'arti brail', 'barelang'],
                'a' => 'BRAIL kepanjangannya Barelang Robotics and Artificial Intelligence Lab di Polibatam 🤖 Itu lab tempat AIoT SIMALAS dikembangkan. Bukan “Brain and AI Lab” ya 😉',
            ],
            [
                'keys' => ['apa itu', 'simalas', 'aiot', 'project', 'proyek', 'tentang'],
                'a' => 'AIoT SIMALAS = platform lab pintar di BRAIL Polibatam 🔥 Ada absensi sidik jari ESP32, loker IoT, sama app mobile mahasiswa. Dosen & laboran kelola lewat portal web staff.',
            ],
            [
                'keys' => ['unduh', 'download', 'apk', 'instal', 'install'],
                'a' => 'Gampang! 📲 Klik Unduh Sekarang / buka halaman Unduh di situs resmi, install APK-nya. Kalau Android nanya izin sumber tidak dikenal, bolehin aja. Panduan lengkap ada di halaman unduh ya~',
            ],
            [
                'keys' => ['mahasiswa', 'login mhs', 'aplikasi'],
                'a' => 'Mahasiswa login-nya di aplikasi mobile SIMALAS (Android), bukan di website publik ya 📱 Website cuma buat kenalan project + unduh APK.',
            ],
            [
                'keys' => ['dosen', 'laboran', 'admin', 'portal'],
                'a' => 'Dosen & laboran masuk lewat portal web staff (login khusus) 🖥️ Terpisah dari landing page publik.',
            ],
            [
                'keys' => ['sidik', 'finger', 'enroll', 'absen'],
                'a' => 'Setelah akun disetujuin admin, datang ke mesin ESP32 di lab terus ikut langkah enroll sidik jari 👆 Absennya di mesin fisik, riwayat & profilnya di app mobile.',
            ],
            [
                'keys' => ['otp', 'email', 'daftar', 'register'],
                'a' => 'Daftar di app mobile → isi data → verifikasi OTP email → nunggu approve laboran (biasanya 1–3 hari kerja) ✉️ OTP nggak masuk? Cek spam dulu, terus kirim ulang ya!',
            ],
            [
                'keys' => ['lokasi', 'alamat', 'maps', 'polibatam', 'di mana'],
                'a' => 'BRAIL ada di Politeknik Negeri Batam (Jl. Ahmad Yani, Teluk Tering, Batam Kota) 📍 Cek peta di bagian Kontak di beranda biar lebih gampang.',
            ],
            [
                'keys' => ['iphone', 'ios', 'apple'],
                'a' => 'Waduh, sekarang app-nya baru Android aja nih 😅 Versi iOS belum available.',
            ],
            [
                'keys' => ['password', 'lupa'],
                'a' => 'Di app: Lupa Password → masukin email → OTP → bikin password baru. Gampang kok 🔑',
            ],
            [
                'keys' => ['loker'],
                'a' => 'Loker pintar dibuka pakai sidik jari di mesin loker IoT (setelah akun & enrollment aktif) 🔐 Riwayat aksesnya bisa dilihat di app mobile.',
            ],
        ];
    }
}
