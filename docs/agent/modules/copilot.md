# Modul SIMALAS Copilot

Kembali ke `../architecture.md`. Pemetaan dari kode 2026-10-24; tidak ada panggilan Gemini atau test pada pemetaan ini.

## 1. Alur end-to-end

1. **UI:** `resources/views/admin/ai_assistant.blade.php` memakai layout Copilot, CSS/JS statis, konfigurasi JSON, riwayat, dan indikator Gemini/mode lokal.
2. **Browser:** `public/js/copilot.js` mengirim POST ke `streamUrl` dengan CSRF dan `client_id`, membaca body stream, lalu menangani `meta`, `status`, `delta`, `block`, `replay`, `error`, `done`.
3. **Route:** `/admin/ai/chat/stream` dan `/dosen/ai/chat/stream` dilindungi session, role, approved, permission `ai_assistant.view`, dan throttle.
4. **Controller:** `Admin/AdminAiController::stream` memvalidasi pesan/client ID, mengunci duplikasi, membuat/mengambil percakapan milik user, menyimpan pesan, dan mengembalikan `StreamedResponse` SSE. Replay `client_id` mencegah provider dipanggil ulang untuk pesan yang sudah lengkap.
5. **Orchestrator:** `AdminAiOrchestrator::streamChat` menerapkan rate limit, memilih jalur lokal untuk pencarian identitas/konfigurasi kosong, atau menjalankan Gemini dengan riwayat terbatas, deklarasi tool, system prompt, dan fallback model.
6. **Provider:** `GeminiSseClient` memanggil `streamGenerateContent?alt=sse` melalui cURL dan meneruskan chunk. Endpoint non-stream lama selalu lokal agar tidak melewati filter privasi.
7. **Tool:** deklarasi berasal dari `AdminAiToolRegistry::geminiDeclarationsForUser`. Function call dijalankan oleh `AdminAiToolRunner`; maksimum enam ronde. Hasil tool dikirim kembali setelah redaction.
8. **Respons:** teks menjadi SSE delta; stats/table/chart/link menjadi blok UI; `AiConversation`/`AiMessage` menyimpan konten dan meta per user.

## 2. Sumber data

- `CopilotAbsensiData::ringkasanHariIni/daftarHariIni` memanggil `AbsensiManagementController::snapshot` dengan user yang sama.
- `laporanPeriode` memakai `PeriodicReportBuilder::build`, sama dengan menu Laporan.
- `cariMahasiswa` membaca `User` role mahasiswa; identitas hanya menjadi blok UI dan payload provider berisi agregat.
- `AdminAiToolRunner` juga membaca mesin, user, log, pengumuman, saran, PBL, dan support operasional sesuai tool.
- `AdminAiContextBuilder` menyediakan snapshot agregat untuk system prompt laboran; dosen mendapat konteks waktu saja pada jalur yang dibaca.

Copilot tidak menggunakan `DashboardDemoData`. Jawaban numerik diarahkan berasal dari tool, bukan dikarang model.

## 3. Batas akses

`AdminAiToolRegistry::READ_ONLY = true`. Tool write masih memiliki metadata/executor historis, tetapi tidak dideklarasikan ke Gemini dan ditolak runner/controller konfirmasi.

- Laboran memiliki daftar tool operasional yang lebih luas.
- Dosen hanya ringkasan/daftar/laporan/cari mahasiswa/chart dengan permission dosen.
- `PermissionRegistry` diperiksa lagi saat runner, bukan hanya saat deklarasi.
- Percakapan dibatasi `conversation.user_id === request.user.id`.
- Tautan ekspor dibentuk hanya jika permission sesuai.
- Role mahasiswa tidak memiliki route Copilot internal.

## 4. Privasi

`CopilotPrivacyFilter` menyamarkan email, NIM/ID, dan nama mahasiswa dengan placeholder konsisten per giliran. Nilai asli disimpan di memori server untuk restore argumen/tool dan output. `providerHistory` hanya mengambil jawaban selesai dan mengabaikan jawaban lokal.

`CopilotAbsensiData` memisahkan `provider` dan `blocks`: nama/NIM pada daftar tetap untuk browser, sedangkan provider menerima jumlah/agregat. Namun UI secara eksplisit memperingatkan bahwa tidak semua data pribadi pasti tersaring; jangan menjanjikan anonimisasi sempurna.

Helpdesk publik adalah jalur berbeda (`HelpdeskAiService`) dan tidak mewarisi semua mekanisme privasi/tool Copilot.

## 5. Strategi test yang ditemukan

`tests/Feature/CopilotStreamTest.php` mencakup halaman, role/permission, stream lokal/Gemini palsu, konsistensi rekap/laporan, privasi, replay, timeout, stop, dan baca-saja. Test memakai SQLite memori dan `FakeGeminiSse`; provider nyata tidak dipakai. Test ini tidak dijalankan dalam pemetaan.

## 6. Titik perhatian untuk pengembangan

- Perubahan tool memerlukan penyesuaian registry, permission, runner, payload provider/UI, prompt, dan test.
- Perubahan format SSE harus menjaga parser `public/js/copilot.js` dan penyimpanan/replay `AiMessage.meta`.
- Jangan mengaktifkan write tool hanya dengan mengubah deklarasi; ada beberapa lapisan dan keputusan pemilik diperlukan.
- Jangan menganggap konfigurasi API/Tier atau keberhasilan historis sebagai validasi jaringan saat ini.
