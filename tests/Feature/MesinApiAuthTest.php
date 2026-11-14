<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Endpoint mesin tidak boleh bisa dipanggil tanpa identitas perangkat yang sah.
 *
 * Uji ini sengaja tidak menyentuh database supaya bisa dijalankan di mana saja,
 * termasuk di CI tanpa MySQL: penolakan terjadi sebelum query apa pun dilakukan.
 */
class MesinApiAuthTest extends TestCase
{
    public function test_tap_absensi_ditolak_tanpa_api_key(): void
    {
        $this->postJson('/api/mesin/tap', ['id_mesin' => 'MESIN_ABSENSI_01'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_tap_absensi_ditolak_tanpa_id_mesin(): void
    {
        $this->withHeader('X-API-KEY', 'kunci-yang-tidak-pernah-terdaftar')
            ->postJson('/api/mesin/tap')
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_endpoint_loker_sudah_dilepas(): void
    {
        $this->postJson('/api/mesin/loker', ['id_mesin' => 'MESIN_LOKER_01'])
            ->assertStatus(404);
    }

    public function test_sync_sidik_jari_tidak_terbuka_untuk_publik(): void
    {
        $this->getJson('/api/mesin/fingerprint/sync?id_mesin=MESIN_ABSENSI_01')
            ->assertStatus(401);
    }
}
