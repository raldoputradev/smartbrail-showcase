<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HelpdeskAiService
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  array{mime: string, data: string}|null  $image  Gambar base64 (vision)
     * @return array{reply: string, source: string}
     */
    public function reply(string $message, array $history = [], ?array $image = null): array
    {
        $message = trim(Str::limit($message, 2500, ''));
        if ($message === '' && $image === null) {
            return [
                'reply' => 'Halo! 👋 Ketik aja pertanyaanmu soal SmartBrail ya~',
                'source' => 'faq',
            ];
        }

        $apiKey = trim((string) config('services.gemini.api_key', ''));
        // Circuit breaker: setelah kegagalan, jangan hubungi Gemini dulu selama
        // jeda singkat — request pengguna tidak boleh menunggu timeout berulang.
        $geminiDown = $apiKey !== '' && Cache::get('helpdesk_gemini_down') === true;
        $geminiFailed = false;
        if ($apiKey !== '' && ! $geminiDown) {
            try {
                $reply = $this->replyWithGemini($message, $history, $apiKey, $image);
                Cache::forget('helpdesk_gemini_down');

                return ['reply' => $reply, 'source' => 'gemini'];
            } catch (\Throwable $e) {
                $geminiFailed = true;
                Cache::put('helpdesk_gemini_down', true, now()->addMinutes(10));
                // Pesan error cURL menyertakan URL lengkap beserta ?key= — jangan log API key.
                $safeError = str_replace($apiKey, '***', (string) $e->getMessage());
                Log::warning('Helpdesk Gemini gagal (sirkuit dibuka 10 menit)', ['error' => $safeError]);
            }
        }

        // Fallback gratis saat Gemini tidak tersedia:
        // - request bergambar → endpoint vision OpenAI-compatible (Ollama lokal / OpenRouter)
        // - request teks → Groq (model open-source gratis)
        if ($image !== null) {
            $vf = (array) config('services.vision_fallback', []);
            if (($vf['enabled'] ?? false) && trim((string) ($vf['base_url'] ?? '')) !== '') {
                try {
                    return ['reply' => $this->replyWithVisionFallback($message, $history, $image), 'source' => 'vision_fallback'];
                } catch (\Throwable $e) {
                    $safeError = str_replace((string) ($vf['api_key'] ?? ''), '***', (string) $e->getMessage());
                    Log::warning('Helpdesk vision fallback gagal', ['error' => $safeError]);
                }
            }
        } else {
            $groqKey = trim((string) config('services.groq.api_key', ''));
            if ($groqKey !== '') {
                try {
                    return ['reply' => $this->replyWithGroq($message, $history, $groqKey), 'source' => 'groq'];
                } catch (\Throwable $e) {
                    $safeError = str_replace($groqKey, '***', (string) $e->getMessage());
                    Log::warning('Helpdesk Groq gagal', ['error' => $safeError]);
                }
            }
        }

        return [
            'reply' => $this->replyFromKnowledge($message)
                .($geminiFailed ? "\n\n_(Psst, AI Gemini lagi offline bentar — jawaban di atas dari FAQ lokal ya 🙏)_" : ''),
            'source' => 'faq',
        ];
    }

    public function isGeminiConfigured(): bool
    {
        return trim((string) config('services.gemini.api_key', '')) !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  array{mime: string, data: string}|null  $image
     */
    private function replyWithGemini(string $message, array $history, string $apiKey, ?array $image = null): string
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
        $userParts = [];
        if ($image !== null && ($image['data'] ?? '') !== '') {
            $userParts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['data']]];
        }
        $userParts[] = ['text' => $message !== '' ? $message : 'Apa isi gambar ini?'];
        $contents[] = [
            'role' => 'user',
            'parts' => $userParts,
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
        // Payload gambar base64 lebih berat — beri ruang timeout lebih saat vision.
        $timeout = $image !== null ? 15 : 5;

        foreach (array_slice($models, 0, 2) as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

            $response = Http::timeout($timeout)
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

    /**
     * Fallback teks gratis via Groq (OpenAI-compatible) — dipakai saat Gemini
     * tidak tersedia/kuota habis. Model open-source seperti gpt-oss / qwen.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    private function replyWithGroq(string $message, array $history, string $apiKey): string
    {
        $model = (string) config('services.groq.model', 'openai/gpt-oss-120b');
        $response = Http::timeout(15)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0.6,
                'max_tokens' => 700,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Groq HTTP '.$response->status().': '.Str::limit((string) $response->body(), 200));
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content'));
        if ($text === '') {
            throw new \RuntimeException('Groq empty reply');
        }

        return Str::limit($text, 1500, '…');
    }

    /**
     * Analisis gambar via endpoint OpenAI-compatible apa pun (Ollama lokal,
     * OpenRouter, dll) — gratis/tanpa key untuk Ollama.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  array{mime: string, data: string}  $image
     */
    private function replyWithVisionFallback(string $message, array $history, array $image): string
    {
        $vf = (array) config('services.vision_fallback', []);
        $base = rtrim((string) ($vf['base_url'] ?? ''), '/');
        $model = (string) ($vf['model'] ?? 'qwen2.5vl:3b');

        $messages = [];
        foreach (array_slice($history, -4) as $row) {
            $role = ($row['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($row['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $text];
        }
        $messages[] = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $message !== '' ? $message : 'Apa isi gambar ini?'],
                ['type' => 'image_url', 'image_url' => ['url' => "data:{$image['mime']};base64,{$image['data']}"]],
            ],
        ];

        $req = Http::connectTimeout((int) ($vf['connect_timeout'] ?? 2))
            ->timeout((int) ($vf['timeout'] ?? 45))
            ->acceptJson();
        $apiKey = trim((string) ($vf['api_key'] ?? ''));
        if ($apiKey !== '') {
            $req = $req->withToken($apiKey);
        }

        $response = $req->post("{$base}/chat/completions", [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.6,
            'max_tokens' => 700,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Vision fallback HTTP '.$response->status().': '.Str::limit((string) $response->body(), 200));
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content'));
        if ($text === '') {
            throw new \RuntimeException('Vision fallback empty reply');
        }

        return Str::limit($text, 1500, '…');
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

        return 'SmartBrail itu sistem lab pintar di BRAIL Polibatam 🔥 absensi sidik jari ESP32, akses ruangan IoT, plus app mobile mahasiswa. '
            .'Coba tanya: cara unduh APK, login mahasiswa, enroll sidik jari, atau lokasi lab ya ✨ '
            .'(Biar makin natural, admin bisa isi GEMINI_API_KEY di server.)';
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
Kamu adalah CS / helpdesk AI resmi SmartBrail (Smart Laboratory Management System) di Politeknik Negeri Batam, BRAIL.

GAYA NGOMONG (WAJIB):
- Santai, gaul, ramah kayak temen kampus — bukan robot kaku / formal bertele-tele.
- Pakai Bahasa Indonesia kasual (boleh “ya”, “sih”, “dong”, “nih”, “banget”, “oke”, “gampang”).
- SELALU sisipkan 1–4 emoji yang relevan di jawaban (jangan berlebihan spam).
- Jawaban pendek & enak dibaca: 2–5 kalimat, boleh pakai bullet singkat kalau perlu langkah.
- Jangan kaku kayak dokumen resmi. Tetap sopan, jangan kasar / toxic / SARA.

TOPIK:
- Hanya bahas SmartBrail: absensi biometrik, akses ruangan pintar, app mobile mahasiswa, portal dosen/laboran, unduh APK, OTP, enroll sidik jari, lokasi lab, siapa pengembang.
- Kalau ditanya “kamu siapa”: bilang kamu asisten AI helpdesk SmartBrail yang siap bantu.
- Kalau di luar topik: tolak santai + arahkan balik ke SmartBrail.

Kepanjangan BRAIL (WAJIB benar):
- BRAIL = Barelang Robotics and Artificial Intelligence Lab.
- JANGAN tulis “Brain and AI Lab” atau kepanjangan salah lain.
- Cukup sebut “BRAIL” atau “BRAIL Polibatam” (jangan “BRAIL Lab” — Lab sudah termasuk di kepanjangan).

Fakta pengembang:
- Dikerjakan oleh tim SmartBrail di bawah BRAIL, Politeknik Negeri Batam.
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
                'a' => 'Haii! 👋 Aku asisten AI helpdesk SmartBrail. Tinggal tanya aja soal absensi sidik jari, akses ruangan, unduh APK, atau cara pakai sistem di BRAIL Polibatam~ ✨',
            ],
            [
                'keys' => ['siapa yang bikin', 'siapa pembuat', 'siapa develop', 'developer', 'pengembang', 'dibuat siapa', 'yang kerjakan', 'dibuat oleh siapa', 'tim aiot'],
                'a' => 'Yang bikin SmartBrail adalah tim SmartBrail di bawah BRAIL (Barelang Robotics and Artificial Intelligence Lab), Politeknik Negeri Batam 🛠️ Mulai dari app mobile, portal web, API, sampe mesin IoT-nya!',
            ],
            [
                'keys' => ['brail', 'kepanjangan brail', 'arti brail', 'barelang'],
                'a' => 'BRAIL kepanjangannya Barelang Robotics and Artificial Intelligence Lab di Polibatam 🤖 Itu lab tempat SmartBrail dikembangkan. Bukan “Brain and AI Lab” ya 😉',
            ],
            [
                'keys' => ['apa itu', 'simalas', 'aiot', 'project', 'proyek', 'tentang'],
                'a' => 'SmartBrail = platform lab pintar di BRAIL Polibatam 🔥 Ada absensi sidik jari ESP32, akses ruangan IoT, sama app mobile mahasiswa. Dosen & laboran kelola lewat portal web staff.',
            ],
            [
                'keys' => ['unduh', 'download', 'apk', 'instal', 'install'],
                'a' => 'Gampang! 📲 Klik Unduh Sekarang / buka halaman Unduh di situs resmi, install APK-nya. Kalau Android nanya izin sumber tidak dikenal, bolehin aja. Panduan lengkap ada di halaman unduh ya~',
            ],
            [
                'keys' => ['mahasiswa', 'login mhs', 'aplikasi'],
                'a' => 'Mahasiswa bisa login di portal web SmartBrail atau aplikasi mobile (Android) 📱 Website publik ini buat kenalan project + unduh APK.',
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
        ];
    }
}
