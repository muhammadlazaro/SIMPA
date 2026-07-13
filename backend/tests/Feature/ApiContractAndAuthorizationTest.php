<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use App\Models\Rfc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiContractAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): string
    {
        return 'Bearer '.$user->createToken('test')->plainTextToken;
    }

    public function test_aplikasi_index_uses_standard_paginated_contract(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        Aplikasi::factory()->count(2)->create();

        $response = $this->withHeader('Authorization', $this->bearer($pengelola))
            ->getJson('/api/aplikasi?per_page=1&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_rfc_endpoints_follow_standard_contract(): void
    {
        Storage::fake('public');

        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->create();

        $create = $this->withHeader('Authorization', $this->bearer($pengelola))
            ->post('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => 'Perubahan kecil',
                'pelaksana' => 'Internal Pusdatik',
                'status_tindaklanjut' => 'Analisa Desain',
                'formulir_rfc' => UploadedFile::fake()->create('formulir-rfc.pdf', 128, 'application/pdf'),
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);

        $rfcId = (int) $create->json('data.id');

        $this->withHeader('Authorization', $this->bearer($pengelola))
            ->patchJson("/api/rfc/{$rfcId}", [
                'deskripsi' => 'Perubahan kecil - revisi',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deskripsi', 'Perubahan kecil - revisi');

        $this->withHeader('Authorization', $this->bearer($pengelola))
            ->deleteJson("/api/rfc/{$rfcId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('rfcs', [
            'id' => $rfcId,
        ]);
    }

    public function test_cannot_create_second_open_rfc_for_same_application(): void
    {
        Storage::fake('public');

        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->production()->create();

        Rfc::create([
            'aplikasi_id' => $aplikasi->id,
            'tipe_rfc' => 'Standar',
            'deskripsi' => 'RFC yang masih berjalan.',
            'pelaksana' => 'Internal Pusdatik',
            'status_tindaklanjut' => Rfc::STATUS_ANALISA_DESAIN,
        ]);

        $response = $this->withHeader('Authorization', $this->bearer($pengelola))
            ->post('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => 'RFC kedua untuk aplikasi yang sama.',
                'pelaksana' => 'Internal Pusdatik',
                'status_tindaklanjut' => Rfc::STATUS_DIAJUKAN,
                'formulir_rfc' => UploadedFile::fake()->create('formulir-rfc-2.pdf', 128, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Aplikasi ini masih memiliki RFC yang belum selesai. Selesaikan RFC tersebut sebelum membuat RFC baru.');

        $this->assertDatabaseCount('rfcs', 1);
    }

    public function test_non_pengelola_cannot_create_rfc(): void
    {
        $developer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $aplikasi = Aplikasi::factory()->create();

        $response = $this->withHeader('Authorization', $this->bearer($developer))
            ->postJson('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => 'Tidak boleh',
                'pelaksana' => 'Internal Pusdatik',
                'status_tindaklanjut' => 'Analisa Desain',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('rfcs', 0);
    }

    public function test_unit_kerja_can_submit_rfc_for_own_production_application(): void
    {
        Storage::fake('public');

        $unitKerja = User::factory()->create(['role' => 'unit_kerja']);
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->production()->create(['created_by' => $unitKerja->id]);

        $response = $this->withHeader('Authorization', $this->bearer($unitKerja))
            ->post('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => 'Mohon perubahan fitur laporan.',
                'formulir_rfc' => UploadedFile::fake()->create('formulir-rfc.pdf', 128, 'application/pdf'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_tindaklanjut', 'Diajukan')
            ->assertJsonPath('data.pelaksana', null);

        $this->assertDatabaseHas('rfcs', [
            'aplikasi_id' => $aplikasi->id,
            'created_by' => $unitKerja->id,
            'status_tindaklanjut' => 'Diajukan',
            'pelaksana' => null,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $pengelola->id,
            'aplikasi_id' => $aplikasi->id,
            'title' => 'Pengajuan RFC Baru',
            'type' => 'action_required',
            'is_read' => false,
        ]);
    }

    public function test_unit_kerja_cannot_submit_rfc_for_other_unit_application(): void
    {
        Storage::fake('public');

        $unitKerja = User::factory()->create(['role' => 'unit_kerja']);
        $otherUnit = User::factory()->create(['role' => 'unit_kerja']);
        $aplikasi = Aplikasi::factory()->production()->create(['created_by' => $otherUnit->id]);

        $response = $this->withHeader('Authorization', $this->bearer($unitKerja))
            ->post('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => 'Tidak boleh mengubah aplikasi unit lain.',
                'formulir_rfc' => UploadedFile::fake()->create('formulir-rfc.pdf', 128, 'application/pdf'),
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('rfcs', 0);
    }

    public function test_unit_kerja_cannot_submit_rfc_for_non_production_application(): void
    {
        Storage::fake('public');

        $unitKerja = User::factory()->create(['role' => 'unit_kerja']);
        $aplikasi = Aplikasi::factory()->create([
            'created_by' => $unitKerja->id,
            'status' => 'pengembangan',
        ]);

        $response = $this->withHeader('Authorization', $this->bearer($unitKerja))
            ->post('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => 'Belum boleh diajukan sebagai RFC.',
                'formulir_rfc' => UploadedFile::fake()->create('formulir-rfc.pdf', 128, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('rfcs', 0);
    }

    public function test_unit_kerja_only_sees_own_rfcs(): void
    {
        $unitKerja = User::factory()->create(['role' => 'unit_kerja']);
        $otherUnit = User::factory()->create(['role' => 'unit_kerja']);
        $ownApp = Aplikasi::factory()->production()->create(['created_by' => $unitKerja->id]);
        $otherApp = Aplikasi::factory()->production()->create(['created_by' => $otherUnit->id]);

        $ownRfc = Rfc::create([
            'aplikasi_id' => $ownApp->id,
            'tipe_rfc' => 'Minor',
            'deskripsi' => 'RFC sendiri',
            'pelaksana' => null,
            'status_tindaklanjut' => 'Diajukan',
        ]);

        Rfc::create([
            'aplikasi_id' => $otherApp->id,
            'tipe_rfc' => 'Major',
            'deskripsi' => 'RFC unit lain',
            'pelaksana' => null,
            'status_tindaklanjut' => 'Diajukan',
        ]);

        $response = $this->withHeader('Authorization', $this->bearer($unitKerja))
            ->getJson('/api/rfc');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRfc->id);

        $this->withHeader('Authorization', $this->bearer($unitKerja))
            ->getJson('/api/rfc/stats')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.diajukan', 1);
    }

    public function test_rfc_description_has_max_length_constraint(): void
    {
        Storage::fake('public');

        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->create();

        $response = $this->withHeader('Authorization', $this->bearer($pengelola))
            ->post('/api/rfc', [
                'aplikasi_id' => $aplikasi->id,
                'tipe_rfc' => 'Minor',
                'deskripsi' => str_repeat('A', 5001),
                'pelaksana' => 'Internal Pusdatik',
                'status_tindaklanjut' => 'Analisa Desain',
                'formulir_rfc' => UploadedFile::fake()->create('formulir-rfc.pdf', 128, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deskripsi']);
    }

    public function test_smoke_authenticated_flow_aplikasi_list_and_detail(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->create();

        $list = $this->withHeader('Authorization', $this->bearer($pengelola))
            ->getJson('/api/aplikasi');

        $list->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeader('Authorization', $this->bearer($pengelola))
            ->getJson('/api/aplikasi/'.$aplikasi->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $aplikasi->id);
    }
}
