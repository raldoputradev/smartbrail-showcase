<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\AdminAi\AdminAiToolRegistry;
use App\Services\AdminAi\AdminAiToolRunner;
use App\Services\AdminAi\CopilotPrivacyFilter;
use App\Services\AdminAi\CopilotProviderError;
use App\Services\AdminAi\CopilotStreamAborted;
use App\Services\AdminAi\GeminiSseClient;
use App\Support\PermissionRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\CopilotSyntheticData;
use Tests\TestCase;

/**
 * Copilot: halaman layar penuh, stream bertahap, data laporan, izin. Provider selalu palsu.
 */
class CopilotStreamTest extends TestCase
{
    use RefreshDatabase;

    private array $ids;

    private FakeGeminiSse $fake;

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes Copilot dibatalkan: koneksi bukan sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-03 18:00:00', 'Asia/Jakarta'));
        Cache::flush();
        PermissionRegistry::clearCache();
        config([
            'services.gemini.api_key' => '',
            'services.gemini.base_url' => 'http://127.0.0.1:9/tidak-dipakai',
            'services.gemini.fallback_models' => [],
        ]);
        $this->fake = new FakeGeminiSse;
        $this->app->instance(GeminiSseClient::class, $this->fake);

        $this->ids = CopilotSyntheticData::seed(Carbon::parse('2026-06-03', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_halaman_copilot_layar_penuh_tanpa_topbar_dan_dashboard_lain_tidak_berubah(): void
    {
        $laboran = $this->user('laboran');

        $page = $this->actingAs($laboran)->get('/admin/ai');
        $page->assertOk()
            ->assertSee('Kembali ke SIMALAS')
            ->assertSee(route('admin.dashboard'), false)
            ->assertSee('id="cpConfig"', false)
            ->assertSee('css/copilot.css', false)
            ->assertDontSee('class="topbar"', false)
            ->assertDontSee('class="sidebar"', false)
            ->assertDontSee('topbarSearch', false)
            ->assertDontSee('notifToggle', false)
            ->assertDontSee('admin-ai.css', false);

        $html = $page->getContent();
        $this->assertDoesNotMatchRegularExpression('/loker|setujui|approve|Halo Admin/i', $html);

        $cfg = $this->pageConfig($html);
        $this->assertLessThanOrEqual(4, count($cfg['suggestions']));
        $this->assertSame(['Ringkas absensi', 'Cari mahasiswa', 'Cek tidak lengkap', 'Buat laporan'], array_column($cfg['suggestions'], 'label'));

        $this->actingAs($laboran)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('class="topbar"', false)
            ->assertSee('class="sidebar"', false);
    }

    public function test_saran_mengikuti_izin_role(): void
    {
        RolePermission::query()->updateOrCreate(
            ['role' => 'laboran', 'menu_key' => 'reports'],
            ['can_view' => false, 'can_create' => false, 'can_update' => false, 'can_delete' => false]
        );
        PermissionRegistry::clearCache();

        $cfg = $this->pageConfig($this->actingAs($this->user('laboran'))->get('/admin/ai')->assertOk()->getContent());
        $this->assertNotContains('Buat laporan', array_column($cfg['suggestions'], 'label'));
    }

    public function test_dosen_dan_mahasiswa_tidak_bisa_memakai_rute_copilot_admin(): void
    {
        foreach (['dosen', 'mahasiswa'] as $role) {
            $user = $this->user($role);
            $this->assertNotSame(200, $this->actingAs($user)->get('/admin/ai')->getStatusCode(), $role);
            $res = $this->actingAs($user)->postJson('/admin/ai/chat/stream', ['message' => 'Ringkas absensi hari ini', 'client_id' => $this->cid()]);
            $this->assertNotSame(200, $res->getStatusCode(), $role);
        }
        $this->assertSame(0, AiMessage::query()->count());
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_dosen_tidak_bisa_mengunduh_ekspor_admin(): void
    {
        $res = $this->actingAs($this->user('dosen'))->get('/admin/reports/export/excel?periode=bulan_ini');
        $this->assertContains($res->getStatusCode(), [302, 403]);
        $this->assertStringNotContainsString('spreadsheet', (string) $res->headers->get('Content-Type'));
    }

    public function test_stream_lokal_ringkasan_cocok_dengan_rekap_cek_absensi(): void
    {
        $laboran = $this->user('laboran');
        $cid = $this->cid();

        $events = $this->events($this->stream($laboran, 'Ringkas absensi hari ini', $cid));
        $names = array_column($events, 'event');
        $this->assertSame('meta', $names[0]);
        $this->assertSame('done', end($names));
        $this->assertContains('delta', $names);
        $this->assertSame('complete', $this->last($events, 'done')['status']);

        $stats = $this->firstBlock($events, 'stats');
        $this->assertNotNull($stats);
        $values = array_column($stats['items'], 'value', 'label');

        $snap = $this->actingAs($laboran)->getJson('/admin/attendance/snapshot?tab=all')->assertOk()->json();
        $text = $snap['summary_text'];
        $this->assertSame((string) $snap['summary']['total'], $values['Mahasiswa aktif']);
        $this->assertSame($text['hadir'], $values['Hadir tepat waktu']);
        $this->assertSame($text['lengkap'], $values['Lengkap']);
        $this->assertSame($text['terlambat'], $values['Terlambat']);
        $this->assertSame($text['alfa'], $values['Alfa']);
        $this->assertSame('13', $values['Mahasiswa aktif']);
        $this->assertSame('5', $values['Lengkap']);

        $links = $this->firstBlock($events, 'links');
        $this->assertNotNull($links);
        foreach ($links['items'] as $link) {
            $this->assertStringStartsWith(url('/admin/attendance'), $link['href']);
        }

        $reply = $this->assistantFor($cid);
        $this->assertSame('complete', $reply->meta['status']);
        $this->assertSame('local', $reply->meta['source']);
        $this->assertSame(1, AiMessage::query()->where('role', 'assistant')->count());
        $this->assertSame(implode('', array_column($this->all($events, 'delta'), 'text')), $reply->content);
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_stream_gemini_bertahap_tool_laporan_cocok_dengan_menu_laporan_tanpa_identitas_ke_provider(): void
    {
        $this->enableGemini();
        $this->fake->script = [
            [['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'laporan_absensi_periode', 'args' => ['periode' => 'bulan_ini', 'kelas' => 'TI-1A']]]]]]]]],
            [
                $this->textChunk('**Laporan bulan ini** untuk kelas TI-1A sudah siap.'),
                $this->textChunk("\n\n| Status | Jumlah |\n|---|---:|\n"),
                $this->textChunk("| Alfa | dari tabel |\n\nUnduh lewat tombol di bawah."),
            ],
        ];
        $laboran = $this->user('laboran');
        $cid = $this->cid();

        $events = $this->events($this->stream($laboran, 'Buat laporan absensi bulan ini kelas TI-1A', $cid));
        $names = array_column($events, 'event');
        $this->assertSame('meta', $names[0]);
        $this->assertContains('status', $names);
        $this->assertGreaterThanOrEqual(3, count($this->all($events, 'delta')));
        $this->assertLessThan(array_search('delta', $names, true), array_search('block', $names, true));
        $this->assertSame('complete', $this->last($events, 'done')['status']);
        $this->assertSame(2, $this->fake->calls);

        $stats = $this->firstBlock($events, 'stats');
        $values = array_column($stats['items'], 'value', 'label');
        $page = $this->actingAs($laboran)->get('/admin/reports?periode=bulan_ini&kelas=TI-1A')->assertOk();
        $summary = $page->viewData('summary');
        $this->assertSame(number_format((int) $summary['mahasiswa']), $values['Mahasiswa dalam cakupan']);
        $this->assertSame(number_format(count($page->viewData('studentRecapsActive'))), $values['Mahasiswa dalam laporan']);
        $this->assertSame(number_format((int) $summary['hadir']), $values['Hadir']);
        $this->assertSame(number_format((int) $summary['tidak_lengkap']), $values['Tidak lengkap']);
        $this->assertSame(number_format((int) $summary['alfa']), $values['Alfa']);
        $this->assertGreaterThan(0, (int) $summary['hadir']);

        $links = array_column($this->firstBlock($events, 'links')['items'], 'href', 'label');
        $this->assertArrayHasKey('Unduh Excel', $links);
        $excel = $this->actingAs($laboran)->get($links['Unduh Excel']);
        $excel->assertOk();

        $sent = json_encode($this->fake->payloads);
        $this->assertStringNotContainsString('Mahasiswa Contoh', $sent);
        $this->assertStringNotContainsString('99000', $sent);
        $this->assertStringNotContainsString('@example.test', $sent);
        $this->assertStringNotContainsString('sandi-uji', $sent);
        $this->assertStringContainsString('functionResponse', $sent);

        $reply = $this->assistantFor($cid);
        $this->assertSame('gemini', $reply->meta['source']);
        $this->assertSame('complete', $reply->meta['status']);
        $this->assertStringContainsString('| Alfa | dari tabel |', $reply->content);
        $this->assertSame(['stats', 'table', 'links'], array_column($reply->meta['blocks'], 'type'));
    }

    public function test_kirim_ulang_client_id_sama_memutar_ulang_tanpa_memanggil_provider(): void
    {
        $this->enableGemini();
        $this->fake->script = [[$this->textChunk('Jawaban pertama.')]];
        $laboran = $this->user('laboran');
        $cid = $this->cid();

        $first = $this->events($this->stream($laboran, 'Apa itu SIMALAS?', $cid));
        $conversationId = $this->last($first, 'meta')['conversation']['id'];
        $second = $this->events($this->stream($laboran, 'Apa itu SIMALAS?', $cid, $conversationId));

        $this->assertSame(1, $this->fake->calls);
        $this->assertSame('Jawaban pertama.', $this->last($second, 'replay')['message']['content']);
        $this->assertSame([], $this->all($second, 'delta'));
        $this->assertSame(1, AiMessage::query()->where('role', 'user')->count());
        $this->assertSame(1, AiMessage::query()->where('role', 'assistant')->count());
        $this->assertSame(1, AiConversation::query()->count());
    }

    public function test_timeout_provider_status_error_lalu_coba_lagi_memperbarui_baris_yang_sama(): void
    {
        $this->enableGemini();
        $this->fake->script = [
            new CopilotProviderError('timeout', 'Gemini timeout: uji'),
            [$this->textChunk('Sudah pulih.')],
        ];
        $laboran = $this->user('laboran');
        $cid = $this->cid();

        $first = $this->events($this->stream($laboran, 'Ringkas tren minggu ini', $cid));
        $this->assertSame('Layanan AI tidak merespons tepat waktu.', $this->last($first, 'error')['message']);
        $this->assertTrue($this->last($first, 'error')['retryable']);
        $this->assertSame('error', $this->last($first, 'done')['status']);
        $failed = $this->assistantFor($cid);
        $this->assertSame('error', $failed->meta['status']);

        $retry = $this->events($this->stream($laboran, 'Ringkas tren minggu ini', $cid, $this->last($first, 'meta')['conversation']['id']));
        $this->assertSame('complete', $this->last($retry, 'done')['status']);
        $this->assertSame($failed->id, $this->last($retry, 'done')['message_id']);
        $this->assertSame(1, AiMessage::query()->where('role', 'assistant')->count());
        $this->assertSame('Sudah pulih.', $failed->fresh()->content);
        $this->assertSame('complete', $failed->fresh()->meta['status']);
    }

    public function test_http_500_dan_balasan_kosong_jadi_pesan_singkat(): void
    {
        $this->enableGemini();
        $laboran = $this->user('laboran');

        $this->fake->script = [new CopilotProviderError('http', 'Gemini HTTP 500: uji')];
        $events = $this->events($this->stream($laboran, 'Jelaskan status hari ini', $this->cid()));
        $this->assertSame('Copilot gagal menghubungi layanan AI.', $this->last($events, 'error')['message']);

        $this->fake->script = [[['candidates' => [['content' => ['parts' => [['text' => '']]]]]]]];
        $events = $this->events($this->stream($laboran, 'Jelaskan status hari ini lagi', $this->cid()));
        $this->assertSame('error', $this->last($events, 'done')['status']);
        $this->assertArrayHasKey('message', $this->last($events, 'error'));
    }

    public function test_stop_menyimpan_jawaban_parsial_sebagai_dihentikan_dan_tidak_masuk_riwayat_provider(): void
    {
        $this->enableGemini();
        $this->fake->script = [
            [$this->textChunk('Bagian awal yang belum selesai'), new CopilotStreamAborted('klien berhenti')],
            [$this->textChunk('Jawaban berikutnya.')],
        ];
        $laboran = $this->user('laboran');
        $cid = $this->cid();

        $events = $this->events($this->stream($laboran, 'Analisis panjang absensi', $cid));
        $this->assertSame('stopped', $this->last($events, 'done')['status']);
        $reply = $this->assistantFor($cid);
        $this->assertSame('stopped', $reply->meta['status']);
        $this->assertSame('Bagian awal yang belum selesai', $reply->content);

        $next = $this->events($this->stream($laboran, 'Pertanyaan baru', $this->cid(), $this->last($events, 'meta')['conversation']['id']));
        $this->assertSame(2, $this->fake->calls, json_encode($next));
        $this->assertStringNotContainsString('Bagian awal yang belum selesai', json_encode($this->fake->payloads[1]['contents']));

        $show = $this->actingAs($laboran)->getJson('/admin/ai/conversations/'.$reply->conversation_id)->assertOk();
        $statuses = array_column($show->json('messages'), 'status');
        $this->assertSame(['complete', 'stopped', 'complete', 'complete'], $statuses);
    }

    public function test_pencarian_nama_dijawab_lokal_tanpa_provider(): void
    {
        $this->enableGemini();
        $laboran = $this->user('laboran');

        $events = $this->events($this->stream($laboran, 'cari mahasiswa Contoh 03', $this->cid()));
        $table = $this->firstBlock($events, 'table');
        $this->assertSame('Mahasiswa Contoh 03', $table['rows'][0]['nama']);
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_copilot_baca_saja_tool_tulis_tidak_diekspos_dan_ditolak(): void
    {
        $this->actingAs($this->user('laboran'));
        $declared = array_column(AdminAiToolRegistry::geminiDeclarationsForUser(), 'name');
        $this->assertNotEmpty($declared);
        foreach ($declared as $name) {
            $this->assertSame('read', AdminAiToolRegistry::get($name)['kind'], $name);
            $this->assertContains($name, AdminAiToolRegistry::COPILOT_TOOLS);
        }
        foreach (['approve_user', 'toggle_mesin', 'koreksi_absensi_harian', 'batalkan_alfa', 'fingerprint_sync_burst', 'create_announcement'] as $write) {
            $this->assertNotContains($write, $declared);
            $result = app(AdminAiToolRunner::class)->run($write, ['user_id' => $this->ids['students'][0]], $this->user('laboran'));
            $this->assertFalse($result['ok'] ?? true, $write);
        }

        $before = User::query()->where('id', $this->ids['students'][0])->first(['is_active', 'approval_status'])->toArray();
        $events = $this->events($this->stream($this->user('laboran'), 'nonaktifkan mahasiswa Contoh 01', $this->cid()));
        $this->assertStringContainsString('baca-saja', implode('', array_column($this->all($events, 'delta'), 'text')));
        $this->assertSame($before, User::query()->where('id', $this->ids['students'][0])->first(['is_active', 'approval_status'])->toArray());
    }

    public function test_laporan_tanpa_izin_reports_tidak_memberi_data_atau_tombol_unduh(): void
    {
        RolePermission::query()->updateOrCreate(
            ['role' => 'laboran', 'menu_key' => 'reports'],
            ['can_view' => false, 'can_create' => false, 'can_update' => false, 'can_delete' => false]
        );
        PermissionRegistry::clearCache();

        $events = $this->events($this->stream($this->user('laboran'), 'Buat laporan absensi bulan ini', $this->cid()));
        $this->assertNull($this->firstBlock($events, 'links'));
        $this->assertNull($this->firstBlock($events, 'stats'));
        $this->assertNotSame(200, $this->actingAs($this->user('laboran'))->get('/admin/reports/export/excel?periode=bulan_ini')->getStatusCode());
    }

    public function test_laporan_tanpa_periode_minta_satu_klarifikasi(): void
    {
        $events = $this->events($this->stream($this->user('laboran'), 'Buat laporan absensi', $this->cid()));
        $choices = $this->firstBlock($events, 'choices');
        $this->assertNotNull($choices);
        $this->assertCount(3, $choices['options']);
        $this->assertNull($this->firstBlock($events, 'stats'));
    }

    public function test_konfirmasi_aksi_lama_ditolak_saat_baca_saja(): void
    {
        $res = $this->actingAs($this->user('laboran'))->postJson('/admin/ai/actions/999999/confirm');
        $this->assertContains($res->getStatusCode(), [404, 422]);
    }

    public function test_pesan_kosong_dan_client_id_tidak_valid_ditolak(): void
    {
        $laboran = $this->user('laboran');
        $this->actingAs($laboran)->postJson('/admin/ai/chat/stream', ['message' => '   ', 'client_id' => $this->cid()])->assertStatus(422);
        $this->actingAs($laboran)->postJson('/admin/ai/chat/stream', ['message' => 'Halo', 'client_id' => '<x>'])->assertStatus(422);
        $this->assertSame(0, AiMessage::query()->count());
    }

    public function test_percakapan_orang_lain_tidak_bisa_dibaca_atau_dilanjutkan(): void
    {
        $other = User::query()->create([
            'nama_lengkap' => 'Laboran Lain Contoh',
            'nitk_nidn' => 'LAB-UJI-02',
            'email' => 'laboran.lain@example.test',
            'password' => 'bukan-sandi',
            'role' => 'laboran',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        $conv = AiConversation::query()->create(['user_id' => $other->id, 'title' => 'Rahasia', 'last_message_at' => now()]);

        $laboran = $this->user('laboran');
        $this->assertContains($this->actingAs($laboran)->getJson('/admin/ai/conversations/'.$conv->id)->getStatusCode(), [403, 404]);
        $res = $this->actingAs($laboran)->post('/admin/ai/chat/stream', ['message' => 'Halo', 'client_id' => $this->cid(), 'conversation_id' => $conv->id], ['Accept' => 'application/json']);
        $this->assertContains($res->getStatusCode(), [403, 404]);
        $this->assertSame(0, AiMessage::query()->count());
    }

    public function test_cakupan_vs_laporan_dan_satuan_konsisten_di_copilot_halaman_excel_word_cetak(): void
    {
        $laboran = $this->user('laboran');
        // Mei 2026: 12 mahasiswa contoh punya catatan. Tambahan yang sengaja tidak masuk laporan:
        // akun login (terdaftar Juni), satu nonaktif, satu aktif yang hanya berstatus libur.
        $nonaktif = $this->extraStudent('Mahasiswa Nonaktif Contoh', '99000901', false);
        $libur = $this->extraStudent('Mahasiswa Libur Contoh', '99000902', true);
        DB::table('absensi_harian')->insert([
            'user_id' => $libur, 'tanggal' => '2026-05-20', 'sesi' => 'pagi', 'status' => 'libur',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $query = 'periode=bulan_lalu';

        $tool = app(AdminAiToolRunner::class)->run('laporan_absensi_periode', ['periode' => 'bulan_lalu'], $laboran);
        $d = $tool['data'];
        $this->assertSame(15, $d['ringkasan']['mahasiswa_dalam_cakupan']);
        $this->assertSame(12, $d['ringkasan']['mahasiswa_dalam_laporan']);
        $this->assertSame(
            ['jumlah' => 3, 'terdaftar_setelah_periode' => 1, 'nonaktif' => 1, 'tanpa_catatan_terhitung' => 1],
            $d['selisih_cakupan']
        );
        $this->assertSame('orang', $d['satuan']['mahasiswa_dalam_laporan']);
        $this->assertSame('sesi-mahasiswa', $d['satuan']['alfa']);

        $items = array_column($tool['blocks'][0]['items'], null, 'label');
        $this->assertStringStartsWith('orang', $items['Mahasiswa dalam cakupan']['meta']);
        $this->assertStringStartsWith('orang', $items['Mahasiswa dalam laporan']['meta']);
        foreach (['Hadir', 'Tidak lengkap', 'Alfa'] as $label) {
            $this->assertStringStartsWith('sesi-mahasiswa', $items[$label]['meta'], $label);
        }
        $this->assertStringContainsString('3 orang dalam cakupan tidak masuk laporan', $tool['blocks'][0]['note']);
        $this->assertStringContainsString('1 terdaftar setelah periode berakhir, 1 akun nonaktif, 1 aktif tetapi hanya berstatus belum/libur/sakit/izin', $tool['blocks'][0]['note']);

        $page = $this->actingAs($laboran)->get('/admin/reports?'.$query)->assertOk();
        $summary = $page->viewData('summary');
        $active = $page->viewData('studentRecapsActive');
        $this->assertSame(15, (int) $summary['mahasiswa']);
        $this->assertCount(12, $active);
        // Agregat sesi-mahasiswa = jumlah sesi per orang di baris laporan (tidak ada yang hilang di ekspor).
        foreach (['hadir', 'tidak_lengkap', 'alfa'] as $k) {
            $this->assertSame((int) $summary[$k], (int) collect($active)->sum($k), $k);
            $this->assertSame(number_format((int) $summary[$k]), $items[match ($k) { 'hadir' => 'Hadir', 'tidak_lengkap' => 'Tidak lengkap', default => 'Alfa' }]['value']);
        }
        $page->assertSee('Mahasiswa dalam cakupan')->assertSee('12 dalam laporan')->assertSee('sesi-mahasiswa');

        $excel = $this->actingAs($laboran)->get('/admin/reports/export/excel?'.$query);
        $excel->assertOk();
        $xls = $excel->streamedContent();
        $this->assertMatchesRegularExpression('/Mahasiswa dalam cakupan \(orang\)<\/td><td class="right">15</', $xls);
        $this->assertMatchesRegularExpression('/Mahasiswa dalam laporan \(orang\)<\/td><td class="right">12</', $xls);
        $this->assertStringContainsString('Alfa (sesi-mahasiswa)', $xls);
        $this->assertSame(12, substr_count($xls, '<td class="nim">'));

        $word = $this->actingAs($laboran)->get('/admin/reports/export/word?'.$query);
        $word->assertOk();
        $doc = $word->streamedContent();
        $this->assertStringContainsString('Mahasiswa dalam cakupan</td><td>15 orang', $doc);
        $this->assertStringContainsString('Mahasiswa dalam laporan</td><td>12 orang', $doc);
        $this->assertStringContainsString(number_format((int) $summary['alfa']).' sesi-mahasiswa', $doc);

        $print = $this->actingAs($laboran)->get('/admin/reports/print?'.$query)->assertOk();
        $print->assertSee('Mahasiswa dalam cakupan')->assertSee('Mahasiswa dalam laporan')->assertSee('sesi-mahasiswa')->assertDontSee('Diekspor');

        $events = $this->events($this->stream($laboran, 'Buat laporan absensi bulan lalu', $this->cid()));
        $reply = implode('', array_column($this->all($events, 'delta'), 'text'));
        // Narasi: hanya periode/filter; angka dan alasan selisih sudah ada di kartu.
        $this->assertSame('Laporan bulan lalu (2026-05-01 s/d 2026-05-31), sumber menu Laporan.', $reply);
        $this->assertDoesNotMatchRegularExpression('/tampil di layar|di bawah/i', $reply);
        $this->assertSame(0, $this->fake->calls);
        $this->assertNotNull($nonaktif);
    }

    public function test_privasi_teks_bebas_dan_history_disamarkan_sebelum_provider(): void
    {
        $this->enableGemini();
        $this->fake->script = [
            [['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'cari_mahasiswa', 'args' => ['q' => '[NAMA_1]']]]]]]]]],
            [$this->textChunk('Data [NAMA_1] sudah dicek; [NI'), $this->textChunk('M_1] cocok.')],
            [$this->textChunk('Minggu lalu tidak ada catatan baru.')],
        ];
        $laboran = $this->user('laboran');
        $cid = $this->cid();
        $pesan = 'Tolong cek absensi Mahasiswa Contoh 05 (NIM 99000005, contoh05@example.test) minggu ini';

        $first = $this->events($this->stream($laboran, $pesan, $cid));
        $this->assertSame('complete', $this->last($first, 'done')['status']);
        $table = $this->firstBlock($first, 'table');
        $this->assertSame('Mahasiswa Contoh 05', $table['rows'][0]['nama']);
        $shown = implode('', array_column($this->all($first, 'delta'), 'text'));
        $this->assertSame('Data Mahasiswa Contoh 05 sudah dicek; 99000005 cocok.', $shown);
        $this->assertSame($shown, $this->assistantFor($cid)->content);

        $userTurn = json_encode(end($this->fake->payloads[0]['contents'])['parts'][0]['text'], JSON_UNESCAPED_UNICODE);
        $this->assertSame('"Tolong cek absensi [NAMA_1] (NIM [NIM_1], [EMAIL_1]) minggu ini"', $userTurn);

        $convId = $this->last($first, 'meta')['conversation']['id'];
        $this->events($this->stream($laboran, 'Bandingkan dengan minggu lalu', $this->cid(), $convId));
        $this->assertSame(3, $this->fake->calls);
        $history = json_encode($this->fake->payloads[2]['contents'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('[NAMA_1]', $history);
        $this->assertStringContainsString('[NIM_1]', $history);

        $sent = json_encode($this->fake->payloads, JSON_UNESCAPED_UNICODE);
        foreach (['Mahasiswa Contoh', 'Contoh 05', '99000005', 'contoh05@', '@example.test', 'sandi-uji'] as $secret) {
            $this->assertStringNotContainsString($secret, $sent, $secret);
        }
    }

    public function test_filter_privasi_sintetis_sebelum_sesudah(): void
    {
        $f = new CopilotPrivacyFilter(
            ['Rangga Pratama Wijaya', 'Siti Nurhaliza Putri', 'Dimas Anggara'],
            ['4342301001', 'rangga.uji@example.test'],
            ['ti', '1a', 'alfa', 'beta'],
        );
        $cases = [
            'Bagaimana absensi rangga minggu ini? NIM 4342301001, email rangga.uji@example.test, kelas TI-1A tim Alfa, alfa tanggal 2026-06-01.'
                => 'Bagaimana absensi [NAMA_1] minggu ini? NIM [NIM_1], email [EMAIL_1], kelas TI-1A tim Alfa, alfa tanggal 2026-06-01.',
            'Dimas Anggara dan Siti Putri sering alfa' => '[NAMA_2] dan [NAMA_3] sering alfa',
            'mahasiswa bernama Yoga Saputra belum tap' => 'mahasiswa bernama [NAMA_4] belum tap',
            'cek mahasiswa Kevin Halim kemarin, nomor 0812345678' => 'cek mahasiswa [NAMA_5] kemarin, nomor [NIM_2]',
            'Lalu Rangga bulan lalu?' => 'Lalu [NAMA_1] bulan lalu?',
            'Ringkas absensi hari ini kelas TI-1A' => 'Ringkas absensi hari ini kelas TI-1A',
        ];
        foreach ($cases as $before => $after) {
            $this->assertSame($after, $f->redact($before), $before);
        }

        $tool = $f->redactValue(['jumlah' => 123456, 'error' => 'Tidak ditemukan: Dimas Anggara', 'filter' => 'kelas TI-1A']);
        $this->assertSame(['jumlah' => 123456, 'error' => 'Tidak ditemukan: [NAMA_2]', 'filter' => 'kelas TI-1A'], $tool);

        $this->assertSame(['q' => 'Dimas Anggara'], $f->restoreValue(['q' => '[NAMA_2]']));
        $this->assertSame('Data ', $f->restoreStream('Data [NA'));
        $this->assertSame('Dimas Anggara dan ', $f->restoreStream('MA_2] dan '));
        $this->assertSame('[TIDAK_ADA', $f->restoreStream('[TIDAK_ADA').$f->flushStream());
        $this->assertEquals(['NAMA' => 5, 'NIM' => 2, 'EMAIL' => 1], $f->stats());
    }

    public function test_pindah_percakapan_tidak_menulis_ke_percakapan_lain_atau_menggandakan(): void
    {
        $this->enableGemini();
        $this->fake->script = [
            [$this->textChunk('Analisis bagian awal'), new CopilotStreamAborted('klien pindah percakapan')],
            [$this->textChunk('Jawaban percakapan B.')],
            [$this->textChunk('Analisis lengkap setelah dibuat ulang.')],
        ];
        $laboran = $this->user('laboran');
        $cidA = $this->cid();

        $a = $this->events($this->stream($laboran, 'Analisis tren absensi', $cidA));
        $this->assertSame('stopped', $this->last($a, 'done')['status']);
        $convA = $this->last($a, 'meta')['conversation']['id'];

        $b = $this->events($this->stream($laboran, 'Pertanyaan di percakapan lain', $this->cid()));
        $convB = $this->last($b, 'meta')['conversation']['id'];
        $this->assertNotSame($convA, $convB);

        $retry = $this->events($this->stream($laboran, 'Analisis tren absensi', $cidA, $convA));
        $this->assertSame('complete', $this->last($retry, 'done')['status']);

        $rows = fn (int $id) => AiMessage::query()->where('conversation_id', $id)->orderBy('id')->get();
        $this->assertSame(['user', 'assistant'], $rows($convA)->pluck('role')->all());
        $this->assertSame(['user', 'assistant'], $rows($convB)->pluck('role')->all());
        $this->assertSame('Analisis lengkap setelah dibuat ulang.', $rows($convA)[1]->content);
        $this->assertSame('Jawaban percakapan B.', $rows($convB)[1]->content);
        $this->assertSame($rows($convA)[0]->id, $rows($convA)[1]->meta['reply_to']);
        $this->assertSame(4, AiMessage::query()->count());
    }

    public function test_endpoint_lama_non_stream_tidak_pernah_memanggil_provider(): void
    {
        $this->enableGemini();
        $res = $this->actingAs($this->user('laboran'))->postJson('/admin/ai/chat', ['message' => 'Bagaimana absensi Mahasiswa Contoh 05 hari ini?']);
        $res->assertOk();
        $this->assertSame('local', $res->json('source'));
        $this->assertSame(0, $this->fake->calls);
    }

    // ───────────── Tahap A: dosen/KPS, mulai baru, dua sesi ─────────────

    public function test_dosen_membuka_copilot_layar_penuh_dengan_url_dan_saran_dosen(): void
    {
        $dosen = $this->user('dosen');

        $page = $this->actingAs($dosen)->get('/dosen/ai')->assertOk();
        $page->assertSee(route('dosen.dashboard'), false)->assertDontSee('class="sidebar"', false);
        $cfg = $this->pageConfig($page->getContent());
        $this->assertSame(route('dosen.ai.chat.stream'), $cfg['streamUrl']);
        $this->assertSame(route('dosen.ai.conversations'), $cfg['conversationsUrl']);
        $this->assertSame(route('dosen.ai'), $cfg['pageUrl']);
        $this->assertNull($cfg['initialConversation']);
        $this->assertSame(['Ringkas absensi', 'Cari mahasiswa', 'Cek tidak lengkap', 'Buat laporan'], array_column($cfg['suggestions'], 'label'));
        $this->assertDoesNotMatchRegularExpression('/approval|setujui|mesin|fingerprint|pengumuman/i', $page->getContent());

        $this->actingAs($dosen)->get('/dosen/dashboard')->assertOk()->assertSee(route('dosen.ai'), false);

        // Tanpa izin data mahasiswa, saran cari tidak dijanjikan.
        $this->setPerm('dosen', 'dosen_mahasiswa', false);
        $cfg = $this->pageConfig($this->actingAs($dosen)->get('/dosen/ai')->assertOk()->getContent());
        $this->assertNotContains('Cari mahasiswa', array_column($cfg['suggestions'], 'label'));

        // Admin bisa mematikan Copilot untuk dosen dari Kelola Akses Role.
        $this->setPerm('dosen', 'ai_assistant', false);
        $this->assertNotSame(200, $this->actingAs($dosen)->get('/dosen/ai')->getStatusCode());
        $this->assertNotSame(200, $this->actingAs($dosen)->post('/dosen/ai/chat/stream', ['message' => 'Halo', 'client_id' => $this->cid()])->getStatusCode());
        $this->actingAs($dosen)->get('/dosen/dashboard')->assertOk()->assertDontSee(route('dosen.ai'), false);
        $this->assertSame(0, AiMessage::query()->count());
    }

    public function test_mahasiswa_dan_laboran_tidak_bisa_memakai_rute_copilot_dosen(): void
    {
        foreach (['mahasiswa', 'laboran'] as $role) {
            $user = $this->user($role);
            $this->assertNotSame(200, $this->actingAs($user)->get('/dosen/ai')->getStatusCode(), $role);
            $res = $this->actingAs($user)->postJson('/dosen/ai/chat/stream', ['message' => 'Ringkas absensi hari ini', 'client_id' => $this->cid()]);
            $this->assertNotSame(200, $res->getStatusCode(), $role);
        }
        $this->assertNotSame(200, $this->actingAs($this->user('mahasiswa'))->get('/admin/ai')->getStatusCode());
        $this->assertSame(0, AiMessage::query()->count());
    }

    public function test_daftar_tool_dan_izin_berbeda_per_role(): void
    {
        $laboran = $this->user('laboran');
        $dosen = $this->user('dosen');

        $dosenTools = array_column(AdminAiToolRegistry::geminiDeclarationsForUser($dosen), 'name');
        sort($dosenTools);
        $expected = AdminAiToolRegistry::COPILOT_TOOLS_DOSEN;
        sort($expected);
        $this->assertSame($expected, $dosenTools);
        $labTools = array_column(AdminAiToolRegistry::geminiDeclarationsForUser($laboran), 'name');
        $this->assertContains('hitung_pengguna', $labTools);
        $this->assertContains('mesin_status', $labTools);
        $this->assertSame([], AdminAiToolRegistry::geminiDeclarationsForUser($this->user('mahasiswa')));

        $runner = app(AdminAiToolRunner::class);
        foreach (['hitung_pengguna', 'attention_today', 'mesin_status', 'ops_summary', 'list_pending_approvals', 'list_announcements',
            'cari_pengguna', 'fingerprint_sync_report', 'enroll_pending', 'approve_user', 'toggle_mesin', 'koreksi_absensi_harian'] as $tool) {
            $r = $runner->run($tool, ['user_id' => $this->ids['students'][0], 'role' => 'semua'], $dosen);
            $this->assertFalse($r['ok'] ?? true, $tool);
            $this->assertArrayNotHasKey('data', $r, $tool);
        }
        $this->assertTrue($runner->run('hitung_pengguna', ['role' => 'semua'], $laboran)['ok']);

        // Dosen: cari mahasiswa tanpa kolom sidik jari; laboran tetap punya kolom itu.
        $dRes = $runner->run('cari_mahasiswa', ['q' => 'Contoh 03'], $dosen);
        $this->assertTrue($dRes['ok']);
        $this->assertNotContains('sidik', array_column($dRes['blocks'][0]['columns'], 'key'));
        $this->assertArrayNotHasKey('sidik', $dRes['blocks'][0]['rows'][0]);
        $lRes = $runner->run('cari_mahasiswa', ['q' => 'Contoh 03'], $laboran);
        $this->assertContains('sidik', array_column($lRes['blocks'][0]['columns'], 'key'));

        // Izin data dosen yang sudah ada menentukan tool.
        $this->setPerm('dosen', 'dosen_mahasiswa', false);
        $this->assertFalse($runner->run('cari_mahasiswa', ['q' => 'Contoh 03'], $dosen)['ok']);
        $this->assertNotContains('cari_mahasiswa', array_column(AdminAiToolRegistry::geminiDeclarationsForUser($dosen), 'name'));
        $this->setPerm('dosen', 'dosen_absensi', false);
        $this->assertFalse($runner->run('laporan_absensi_periode', ['periode' => 'bulan_ini'], $dosen)['ok']);
        $this->assertFalse($runner->run('ringkasan_absensi_hari_ini', [], $dosen)['ok']);
        $this->assertSame([], AdminAiToolRegistry::geminiDeclarationsForUser($dosen));
    }

    public function test_dosen_tidak_bisa_memanggil_tool_admin_lewat_model_dan_konteks_tanpa_data_operasional(): void
    {
        $this->enableGemini();
        $this->fake->script = [
            [['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'hitung_pengguna', 'args' => ['role' => 'semua']]]]]]]]],
            [['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'list_pending_approvals', 'args' => []]]]]]]]],
            [$this->textChunk('Data itu tidak tersedia untuk akun ini.')],
        ];
        $dosen = $this->user('dosen');
        $events = $this->events($this->stream($dosen, 'Jelaskan semua yang kamu tahu tentang akun dan mesin', $this->cid()));
        $this->assertSame('complete', $this->last($events, 'done')['status']);
        $this->assertSame(3, $this->fake->calls);

        $declared = array_column($this->fake->payloads[0]['tools'][0]['functionDeclarations'] ?? [], 'name');
        sort($declared);
        $expected = AdminAiToolRegistry::COPILOT_TOOLS_DOSEN;
        sort($expected);
        $this->assertSame($expected, $declared);

        $system = (string) $this->fake->payloads[0]['system_instruction']['parts'][0]['text'];
        $this->assertStringContainsString('"generated_at"', $system);
        $this->assertStringContainsString('dosen/KPS', $system);
        foreach (['pending_approval', 'enroll_belum', 'mesin', 'hitung_pengguna', 'attention_today'] as $opsKey) {
            $this->assertStringNotContainsString('"'.$opsKey.'"', $system, $opsKey);
        }
        $this->assertStringNotContainsString('hitung_pengguna', $system);

        $responses = json_encode([$this->fake->payloads[1]['contents'], $this->fake->payloads[2]['contents']], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Tidak punya izin', $responses);
        $this->assertStringNotContainsString('"total"', $responses);
        $this->assertNull($this->firstBlock($events, 'table'));
        $this->assertNull($this->firstBlock($events, 'stats'));
    }

    public function test_dosen_mode_lokal_hanya_intent_absensi_tanpa_data_admin(): void
    {
        $dosen = $this->user('dosen');
        foreach (['berapa jumlah akun dosen', 'status mesin hari ini', 'siapa yang perlu perhatian', 'daftar pengumuman', 'antrian approval'] as $q) {
            $events = $this->events($this->stream($dosen, $q, $this->cid()));
            $text = implode('', array_column($this->all($events, 'delta'), 'text'));
            $this->assertStringContainsString('Belum bisa menjawab', $text, $q);
            $this->assertSame([], $this->all($events, 'block'), $q);
        }
        $events = $this->events($this->stream($dosen, 'hapus akun mahasiswa Contoh 01', $this->cid()));
        $text = implode('', array_column($this->all($events, 'delta'), 'text'));
        $this->assertStringContainsString('baca-saja', $text);
        $this->assertStringNotContainsString('Mesin IoT', $text);
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_ringkasan_dan_laporan_dosen_cocok_dengan_halaman_dosen_dan_unduhan_tetap_berizin(): void
    {
        $dosen = $this->user('dosen');

        $events = $this->events($this->stream($dosen, 'Ringkas absensi hari ini', $this->cid()));
        $values = array_column($this->firstBlock($events, 'stats')['items'], 'value', 'label');
        $snap = $this->actingAs($dosen)->getJson('/dosen/absensi/snapshot?tab=all')->assertOk()->json();
        $this->assertSame((string) $snap['summary']['total'], $values['Mahasiswa aktif']);
        $this->assertSame($snap['summary_text']['alfa'], $values['Alfa']);
        $this->assertSame($snap['summary_text']['tidak_lengkap'], $values['Tidak lengkap']);
        $rekapLinks = array_column($this->firstBlock($events, 'links')['items'], 'href', 'label');
        $this->assertArrayHasKey('Buka di Rekap Harian', $rekapLinks);
        foreach ($rekapLinks as $href) {
            $this->assertStringStartsWith(url('/dosen/absensi'), $href);
        }
        $reply = implode('', array_column($this->all($events, 'delta'), 'text'));
        $this->assertStringContainsString('Rekap Harian', $reply);
        $this->assertStringNotContainsString('Cek Absensi', $reply);

        $events = $this->events($this->stream($dosen, 'Siapa yang alfa hari ini?', $this->cid()));
        $table = $this->firstBlock($events, 'table');
        $this->assertSame((int) $snap['summary']['alfa'], (int) $table['total']);

        $tool = app(AdminAiToolRunner::class)->run('laporan_absensi_periode', ['periode' => 'bulan_lalu', 'kelas' => 'TI-1A'], $dosen);
        $items = array_column($tool['blocks'][0]['items'], 'value', 'label');
        $page = $this->actingAs($dosen)->get('/dosen/reports?periode=bulan_lalu&kelas=TI-1A')->assertOk();
        $summary = $page->viewData('summary');
        $this->assertSame(number_format((int) $summary['mahasiswa']), $items['Mahasiswa dalam cakupan']);
        $this->assertSame(number_format(count($page->viewData('studentRecapsActive'))), $items['Mahasiswa dalam laporan']);
        foreach (['hadir' => 'Hadir', 'tidak_lengkap' => 'Tidak lengkap', 'alfa' => 'Alfa'] as $k => $label) {
            $this->assertSame(number_format((int) $summary[$k]), $items[$label], $label);
        }

        $links = array_column(collect($tool['blocks'])->firstWhere('type', 'links')['items'], 'href', 'label');
        $this->assertArrayHasKey('Buka di menu Laporan Periodik', $links);
        foreach ($links as $href) {
            $this->assertStringStartsWith(url('/dosen/reports'), $href);
        }
        $this->actingAs($dosen)->get($links['Unduh Excel'])->assertOk();
        $this->actingAs($dosen)->get($links['Unduh Word'])->assertOk();
        $this->actingAs($dosen)->get($links['Cetak'])->assertOk();
        foreach (['mahasiswa', 'laboran'] as $role) {
            $this->assertNotSame(200, $this->actingAs($this->user($role))->get($links['Unduh Excel'])->getStatusCode(), $role);
        }
        $this->setPerm('dosen', 'dosen_absensi', false);
        $this->assertNotSame(200, $this->actingAs($dosen)->get($links['Unduh Excel'])->getStatusCode());
    }

    public function test_riwayat_dosen_dan_laboran_terpisah(): void
    {
        $dosen = $this->user('dosen');
        $laboran = $this->user('laboran');
        $events = $this->events($this->stream($laboran, 'Ringkas absensi hari ini', $this->cid()));
        $labConv = $this->last($events, 'meta')['conversation']['id'];
        $events = $this->events($this->stream($dosen, 'Ringkas absensi hari ini', $this->cid()));
        $dosenConv = $this->last($events, 'meta')['conversation']['id'];

        $this->assertSame([$dosenConv], array_column($this->actingAs($dosen)->getJson('/dosen/ai/conversations')->assertOk()->json('conversations'), 'id'));
        $this->assertSame(403, $this->actingAs($dosen)->getJson('/dosen/ai/conversations/'.$labConv)->getStatusCode());
        $this->assertSame(403, $this->actingAs($dosen)->deleteJson('/dosen/ai/conversations/'.$labConv)->getStatusCode());
        $res = $this->actingAs($dosen)->post('/dosen/ai/chat/stream', ['message' => 'Halo', 'client_id' => $this->cid(), 'conversation_id' => $labConv], ['Accept' => 'application/json']);
        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame(403, $this->actingAs($laboran)->getJson('/admin/ai/conversations/'.$dosenConv)->getStatusCode());
        $this->assertSame(2, AiMessage::query()->where('conversation_id', $labConv)->count());

        // ?c= milik orang lain tidak dibuka.
        $cfg = $this->pageConfig($this->actingAs($dosen)->get('/dosen/ai?c='.$labConv)->assertOk()->getContent());
        $this->assertNull($cfg['initialConversation']);
        $this->actingAs($dosen)->get('/dosen/ai')->assertOk()->assertDontSee('data-id="'.$labConv.'"', false);
    }

    public function test_membuka_halaman_selalu_percakapan_baru_tanpa_membuat_baris_dan_c_hanya_untuk_milik_sendiri(): void
    {
        $laboran = $this->user('laboran');
        $events = $this->events($this->stream($laboran, 'Ringkas absensi hari ini', $this->cid()));
        $conv = $this->last($events, 'meta')['conversation']['id'];
        $convCount = AiConversation::query()->count();
        $msgCount = AiMessage::query()->count();

        for ($i = 0; $i < 3; $i++) {
            $cfg = $this->pageConfig($this->actingAs($laboran)->get('/admin/ai')->assertOk()->getContent());
            $this->assertNull($cfg['initialConversation']);
        }
        $this->actingAs($this->user('dosen'))->get('/dosen/ai')->assertOk();
        $this->assertSame($convCount, AiConversation::query()->count());
        $this->assertSame($msgCount, AiMessage::query()->count());

        $cfg = $this->pageConfig($this->actingAs($laboran)->get('/admin/ai?c='.$conv)->assertOk()->getContent());
        $this->assertSame($conv, $cfg['initialConversation']);
        foreach (['abc', '0', '999999', $conv.'x'] as $bad) {
            $cfg = $this->pageConfig($this->actingAs($laboran)->get('/admin/ai?c='.$bad)->assertOk()->getContent());
            $this->assertNull($cfg['initialConversation'], $bad);
        }
        $this->assertSame($convCount, AiConversation::query()->count());

        $js = (string) file_get_contents(public_path('js/copilot.js'));
        $this->assertStringNotContainsString("var last = store('local', 'cp_last_conv')", $js);
        $this->assertStringContainsString('cfg.initialConversation', $js);
    }

    public function test_dua_sesi_di_tanggal_sama_dihitung_dua_sesi_mahasiswa_konsisten_di_semua_keluaran(): void
    {
        $laboran = $this->user('laboran');
        $query = 'periode=bulan_lalu';
        $before = app(AdminAiToolRunner::class)->run('laporan_absensi_periode', ['periode' => 'bulan_lalu'], $laboran)['data']['ringkasan'];

        $uid = $this->ids['students'][0];
        $pagi = DB::table('absensi_harian')->where('user_id', $uid)->where('tanggal', '2026-05-29')->where('sesi', 'pagi')->first();
        $this->assertNotNull($pagi);
        DB::table('absensi_harian')->insert([
            'user_id' => $uid, 'tanggal' => '2026-05-29', 'sesi' => 'malam', 'status' => 'lengkap',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $tool = app(AdminAiToolRunner::class)->run('laporan_absensi_periode', ['periode' => 'bulan_lalu'], $laboran);
        $after = $tool['data']['ringkasan'];
        $this->assertSame($before['mahasiswa_dalam_laporan'], $after['mahasiswa_dalam_laporan']);
        $this->assertSame($before['hadir_lengkap'] + 1, $after['hadir_lengkap']);
        $this->assertSame('sesi-mahasiswa', $tool['data']['satuan']['hadir_lengkap']);
        $items = array_column($tool['blocks'][0]['items'], null, 'label');
        $this->assertStringStartsWith('sesi-mahasiswa', $items['Hadir']['meta']);
        $this->assertStringContainsString('dua sesi di tanggal yang sama dihitung dua', $tool['blocks'][0]['note']);
        $rank = collect($tool['blocks'])->firstWhere('type', 'table');
        $this->assertSame('Mahasiswa Contoh 01', $rank['rows'][0]['nama']);
        $this->assertStringEndsWith(' sesi', $rank['rows'][0]['nilai']);

        $page = $this->actingAs($laboran)->get('/admin/reports?'.$query)->assertOk();
        $summary = $page->viewData('summary');
        $this->assertSame($after['hadir_lengkap'], (int) $summary['hadir']);
        $row = collect($page->viewData('studentRecapsActive'))->firstWhere('nama', 'Mahasiswa Contoh 01');
        $this->assertNotNull($row);
        $page->assertSee('sesi-mahasiswa')->assertDontSee('hari-mahasiswa');

        $xls = $this->actingAs($laboran)->get('/admin/reports/export/excel?'.$query)->streamedContent();
        $this->assertStringContainsString('Hadir (sesi-mahasiswa)</td><td class="right">'.number_format((int) $summary['hadir']).'<', $xls);
        $this->assertStringContainsString('Hadir (sesi)', $xls);
        $this->assertStringNotContainsString('hari-mahasiswa', $xls);
        $doc = $this->actingAs($laboran)->get('/admin/reports/export/word?'.$query)->streamedContent();
        $this->assertStringContainsString(number_format((int) $summary['hadir']).' sesi-mahasiswa', $doc);
        $this->assertStringNotContainsString('hari-mahasiswa', $doc);
        $this->actingAs($laboran)->get('/admin/reports/print?'.$query)->assertOk()
            ->assertSee('sesi-mahasiswa')->assertSee('Hadir (sesi)')->assertDontSee('hari-mahasiswa');
    }

    public function test_pemberitahuan_gemini_jujur_dan_pemetaan_placeholder_per_giliran(): void
    {
        $this->actingAs($this->user('laboran'))->get('/admin/ai')->assertOk()->assertDontSee('diproses Gemini');
        $this->enableGemini();
        $this->actingAs($this->user('dosen'))->get('/dosen/ai')->assertOk()
            ->assertSee('diproses Gemini')
            ->assertSee('tidak semua data pribadi pasti tersaring');

        $this->assertNotSame(app(CopilotPrivacyFilter::class), app(CopilotPrivacyFilter::class));

        $this->fake->script = [
            [$this->textChunk('Dicatat untuk [NAMA_1].')],
            [$this->textChunk('Dicatat untuk [NAMA_1].')],
        ];
        $laboran = $this->user('laboran');
        $dosen = $this->user('dosen');
        $a = $this->events($this->stream($laboran, 'Bagaimana Mahasiswa Contoh 05 minggu ini?', $this->cid()));
        $b = $this->events($this->stream($dosen, 'Bagaimana Mahasiswa Contoh 07 minggu ini?', $this->cid()));
        $this->assertSame('Dicatat untuk Mahasiswa Contoh 05.', implode('', array_column($this->all($a, 'delta'), 'text')));
        $this->assertSame('Dicatat untuk Mahasiswa Contoh 07.', implode('', array_column($this->all($b, 'delta'), 'text')));
        $this->assertStringNotContainsString('Contoh 0', json_encode($this->fake->payloads, JSON_UNESCAPED_UNICODE));
    }

    // ───────────── helper ─────────────

    private function setPerm(string $role, string $menu, bool $view): void
    {
        RolePermission::query()->updateOrCreate(
            ['role' => $role, 'menu_key' => $menu],
            ['can_view' => $view, 'can_create' => false, 'can_update' => false, 'can_delete' => false]
        );
        PermissionRegistry::clearCache();
    }

    private function extraStudent(string $nama, string $nim, bool $aktif): int
    {
        return (int) DB::table('users')->insertGetId([
            'nama_lengkap' => $nama,
            'nitk_nidn' => $nim,
            'email' => $nim.'@example.test',
            'password' => 'bukan-sandi',
            'role' => 'mahasiswa',
            'is_active' => $aktif ? 1 : 0,
            'approval_status' => 'approved',
            'kelas' => 'TI-1A',
            'tim' => 'Tim Alfa',
            'created_at' => '2026-04-01 08:00:00',
            'updated_at' => '2026-04-01 08:00:00',
        ]);
    }

    private function user(string $role): User
    {
        return User::query()->findOrFail($this->ids[$role]);
    }

    private function cid(): string
    {
        return (string) Str::uuid();
    }

    private function enableGemini(): void
    {
        config(['services.gemini.api_key' => 'kunci-palsu-uji']);
        RateLimiter::clear('admin-ai-chat:'.$this->ids['laboran']);
        RateLimiter::clear('admin-ai-chat:'.$this->ids['dosen']);
    }

    private function textChunk(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]]];
    }

    private function stream(User $user, string $message, string $cid, ?int $conversationId = null): TestResponse
    {
        $body = ['message' => $message, 'client_id' => $cid];
        if ($conversationId) {
            $body['conversation_id'] = $conversationId;
        }
        $res = $this->actingAs($user)->post($user->isDosen() ? '/dosen/ai/chat/stream' : '/admin/ai/chat/stream', $body, ['Accept' => 'text/event-stream']);
        $res->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $res->headers->get('Content-Type'));

        return $res;
    }

    /** @return list<array{event: string, data: array}> */
    private function events(TestResponse $res): array
    {
        $out = [];
        foreach (preg_split("/\n\n/", $res->streamedContent()) as $chunk) {
            $event = null;
            $data = null;
            foreach (explode("\n", $chunk) as $line) {
                if (str_starts_with($line, 'event: ')) {
                    $event = substr($line, 7);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);
                }
            }
            if ($event !== null) {
                $out[] = ['event' => $event, 'data' => $data];
            }
        }

        return $out;
    }

    private function all(array $events, string $name): array
    {
        return array_values(array_map(fn ($e) => $e['data'], array_filter($events, fn ($e) => $e['event'] === $name)));
    }

    private function last(array $events, string $name): ?array
    {
        $all = $this->all($events, $name);

        return $all === [] ? null : end($all);
    }

    private function firstBlock(array $events, string $type): ?array
    {
        foreach ($this->all($events, 'block') as $d) {
            if (($d['block']['type'] ?? null) === $type) {
                return $d['block'];
            }
        }

        return null;
    }

    private function assistantFor(string $cid): AiMessage
    {
        $user = AiMessage::query()->where('role', 'user')->where('meta->client_id', $cid)->firstOrFail();

        return AiMessage::query()->where('role', 'assistant')->where('meta->reply_to', $user->id)->firstOrFail();
    }

    private function pageConfig(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="cpConfig">(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }
}

/**
 * Pengganti GeminiSseClient: memutar skrip potongan SSE, mencatat payload. Tanpa jaringan.
 */
class FakeGeminiSse extends GeminiSseClient
{
    /** @var list<list<array|\Throwable>|\Throwable> */
    public array $script = [];

    public array $payloads = [];

    public int $calls = 0;

    public function stream(string $url, string $apiKey, array $payload, callable $onChunk, callable $alive): void
    {
        $this->calls++;
        $this->payloads[] = $payload;
        if (! str_starts_with($url, 'http://127.0.0.1:9/')) {
            throw new RuntimeException('URL provider tidak diharapkan dalam tes.');
        }
        $step = array_shift($this->script);
        if ($step === null) {
            throw new RuntimeException('Skrip provider palsu habis.');
        }
        if ($step instanceof \Throwable) {
            throw $step;
        }
        foreach ($step as $chunk) {
            if ($chunk instanceof \Throwable) {
                throw $chunk;
            }
            $onChunk($chunk);
        }
    }
}
