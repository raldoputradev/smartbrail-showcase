<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Smoke test schema: kolom yang dirujuk query modul BMS harus benar-benar ada.
 * Menangkap bug jenis "kolom users.name/nim" yang lolos di SQLite
 * karena fallback string-literal (DQS) tetapi error 1054 di MySQL.
 */
class SchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('SchemaGuardTest hanya untuk sqlite :memory:.');
        }
    }

    public function test_kolom_users_yang_dirujuk_query_bms_ada(): void
    {
        $kolom = Schema::getColumnListing('users');
        foreach (['id', 'nama_lengkap', 'nitk_nidn', 'role', 'tim', 'kelas', 'finger_id', 'rfid_uid', 'akses_pin', 'is_admin'] as $k) {
            $this->assertContains($k, $kolom, "Kolom users.{$k} tidak ada — query BMS akan error di MySQL");
        }
        // Regresi spesifik: kolom ini TIDAK ada — jangan pernah dirujuk lagi.
        $this->assertNotContains('name', $kolom);
        $this->assertNotContains('nim', $kolom);
    }

    public function test_anggota_tim_mengembalikan_kolom_nyata(): void
    {
        $tim = \App\Models\PblProject::create(['kode' => 'T', 'nama' => 'Tim X', 'aktif' => true]);
        \App\Models\User::query()->create([
            'nama_lengkap' => 'Mhs Uji', 'nitk_nidn' => 'M1', 'email' => 'm@t.t',
            'password' => 'x', 'role' => 'mahasiswa', 'is_active' => 1,
            'approval_status' => 'approved', 'tim' => 'Tim X',
        ]);

        $anggota = \App\Support\RuanganBookingService::anggotaTim($tim);
        $this->assertCount(1, $anggota);
        $this->assertSame('Mhs Uji', $anggota->first()->nama_lengkap);
    }
}
