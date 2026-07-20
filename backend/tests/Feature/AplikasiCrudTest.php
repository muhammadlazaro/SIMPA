<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Aplikasi;
use App\Models\AplikasiDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AplikasiCrudTest extends TestCase
{
    use RefreshDatabase;

    protected $pengelolaUser;
    protected $readerUser;
    protected $unitKerjaUser;
    protected $pengelolaToken;
    protected $readerToken;
    protected $unitKerjaToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create pengelola aplikasi user
        $this->pengelolaUser = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $this->pengelolaToken = $this->pengelolaUser->createToken('test')->plainTextToken;

        // Create non-pengelola role
        $this->readerUser = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $this->readerToken = $this->readerUser->createToken('test')->plainTextToken;

        // Create unit kerja role
        $this->unitKerjaUser = User::factory()->create(['role' => 'unit_kerja']);
        $this->unitKerjaToken = $this->unitKerjaUser->createToken('test')->plainTextToken;
    }

    /**
     * Test pengelola can create aplikasi
     */
    public function test_pengelola_can_create_aplikasi(): void
    {
        Storage::fake('public');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->post('/api/aplikasi', [
                'nama_layanan' => 'Test Layanan',
                'nama_singkat' => 'TL',
                'nama_aplikasi' => 'Test App',
                'jenis_layanan_aplikasi' => 'publik',
                'kode_unitOrganisasi' => 'TEST001',
                'tipe_akuisisi' => 'Custom-Made',
                'surat_pengajuan' => UploadedFile::fake()->create('surat.pdf', 100),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['aplikasi'],
            ]);

        $this->assertDatabaseHas('aplikasis', [
            'nama_aplikasi' => 'Test App',
            'nama_layanan' => 'Test Layanan',
            'status' => 'diajukan',
        ]);
    }

    /**
     * Test user cannot create aplikasi (only read access)
     */
    public function test_user_cannot_create_aplikasi(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->readerToken)
            ->postJson('/api/aplikasi', [
                'nama_layanan' => 'Test Layanan',
                'nama_singkat' => 'TL',
                'nama_aplikasi' => 'Test App',
                'jenis_layanan_aplikasi' => 'publik',
                'kode_unitOrganisasi' => 'TEST001',
                'tipe_akuisisi' => 'Custom-Made',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test unit kerja can create aplikasi.
     */
    public function test_unit_kerja_can_create_aplikasi(): void
    {
        Storage::fake('public');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->post('/api/aplikasi', [
                'nama_layanan' => 'Layanan Unit',
                'nama_singkat' => 'LU',
                'nama_aplikasi' => 'Aplikasi Unit',
                'jenis_layanan_aplikasi' => 'pendukung',
                'kode_unitOrganisasi' => 'UNIT001',
                'tipe_akuisisi' => 'Custom-Made',
                'surat_pengajuan' => UploadedFile::fake()->create('surat.pdf', 100),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('aplikasis', [
            'nama_aplikasi' => 'Aplikasi Unit',
            'jenis_layanan_aplikasi' => 'pendukung',
            'created_by' => $this->unitKerjaUser->id,
        ]);
    }

    /**
     * Test pengelola can update aplikasi
     */
    public function test_pengelola_can_update_aplikasi(): void
    {
        $aplikasi = Aplikasi::factory()->create([
            'status' => 'pengembangan'
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->putJson("/api/aplikasi/{$aplikasi->id}", [
                'nama_layanan' => 'Test Layanan Updated',
                'nama_singkat' => 'TLU',
                'nama_aplikasi' => 'Test App Updated',
                'kode_unitOrganisasi' => 'UPDATED001',
                'jenis_layanan_aplikasi' => 'pendukung',
                'tipe_akuisisi' => 'Off-The-Shelf',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('aplikasis', [
            'id' => $aplikasi->id,
            'status' => 'pengembangan',
            'nama_layanan' => 'Test Layanan Updated',
            'nama_singkat' => 'TLU',
            'nama_aplikasi' => 'Test App Updated',
            'kode_unitOrganisasi' => 'UPDATED001',
            'jenis_layanan_aplikasi' => 'pendukung',
        ]);
    }

    /**
     * Test pengelola cannot update status directly.
     */
    public function test_pengelola_cannot_update_status_directly(): void
    {
        $aplikasi = Aplikasi::factory()->create([
            'status' => 'pengembangan',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->putJson("/api/aplikasi/{$aplikasi->id}", [
                'status' => 'diajukan',
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('aplikasis', [
            'id' => $aplikasi->id,
            'status' => 'pengembangan',
        ]);
    }

    /**
     * Test pengelola can permanently delete aplikasi and related data
     */
    public function test_pengelola_can_delete_aplikasi(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('legacy/pengajuan.pdf', 'legacy');
        Storage::disk('public')->put('documents/formulir.pdf', 'document');
        Storage::disk('public')->put('rfc_documents/formulir-rfc.pdf', 'rfc');

        $aplikasi = Aplikasi::factory()->create([
            'doc_pengajuan_path' => 'legacy/pengajuan.pdf',
        ]);

        $document = $aplikasi->documents()->create([
            'document_type' => 'formulir_pengajuan',
            'storage_path' => 'documents/formulir.pdf',
            'original_filename' => 'formulir.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 16,
            'version' => 1,
            'status' => 'active',
            'uploaded_by' => $this->pengelolaUser->id,
        ]);

        $checklist = $aplikasi->checklists()->create([
            'category' => 'studi_kelayakan',
            'title' => 'Dokumen lengkap',
            'item_status' => 'done',
            'created_by' => $this->pengelolaUser->id,
        ]);

        $note = $aplikasi->notes()->create([
            'note_type' => 'info',
            'body' => 'Catatan aplikasi',
            'created_by' => $this->pengelolaUser->id,
        ]);

        $rfc = $aplikasi->rfcs()->create([
            'tipe_rfc' => 'Minor',
            'deskripsi' => 'Perubahan kecil',
            'formulir_path' => 'rfc_documents/formulir-rfc.pdf',
            'formulir_original_filename' => 'formulir-rfc.pdf',
            'formulir_mime_type' => 'application/pdf',
            'formulir_file_size' => 32,
            'pelaksana' => 'Internal Pusdatik',
            'status_tindaklanjut' => 'Analisa Desain',
            'created_by' => $this->pengelolaUser->id,
        ]);

        $notification = $aplikasi->notifications()->create([
            'user_id' => $this->pengelolaUser->id,
            'type' => 'info',
            'title' => 'Notifikasi',
            'body' => 'Notifikasi aplikasi',
            'is_read' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
                         ->deleteJson("/api/aplikasi/{$aplikasi->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('aplikasis', ['id' => $aplikasi->id]);
        $this->assertDatabaseMissing('aplikasi_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('aplikasi_checklists', ['id' => $checklist->id]);
        $this->assertDatabaseMissing('aplikasi_notes', ['id' => $note->id]);
        $this->assertDatabaseMissing('rfcs', ['id' => $rfc->id]);
        $this->assertDatabaseMissing('app_notifications', ['id' => $notification->id]);

        Storage::disk('public')->assertMissing('legacy/pengajuan.pdf');
        Storage::disk('public')->assertMissing('documents/formulir.pdf');
        Storage::disk('public')->assertMissing('rfc_documents/formulir-rfc.pdf');
    }

    public function test_pengelola_can_mark_aplikasi_as_nonaktif_without_deleting_it(): void
    {
        $aplikasi = Aplikasi::factory()->production()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/nonaktifkan");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.aplikasi.status', Aplikasi::STATUS_NONAKTIF);

        $this->assertDatabaseHas('aplikasis', [
            'id' => $aplikasi->id,
            'status' => Aplikasi::STATUS_NONAKTIF,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('aplikasi_status_histories', [
            'aplikasi_id' => $aplikasi->id,
            'status_baru' => Aplikasi::STATUS_NONAKTIF,
            'aksi' => 'Nonaktifkan Aplikasi',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->getJson('/api/aplikasi/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.inactive', 1);
    }

    public function test_pengelola_cannot_mark_development_aplikasi_as_nonaktif(): void
    {
        $aplikasi = Aplikasi::factory()->create([
            'status' => Aplikasi::STATUS_PENGEMBANGAN,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/nonaktifkan");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Aplikasi hanya dapat dinonaktifkan setelah berstatus deployed production.');

        $this->assertDatabaseHas('aplikasis', [
            'id' => $aplikasi->id,
            'status' => Aplikasi::STATUS_PENGEMBANGAN,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseMissing('aplikasi_status_histories', [
            'aplikasi_id' => $aplikasi->id,
            'status_baru' => Aplikasi::STATUS_NONAKTIF,
        ]);
    }

    public function test_aplikasi_stats_separates_nonaktif_from_stopped_statuses(): void
    {
        Aplikasi::factory()->create(['status' => Aplikasi::STATUS_NONAKTIF]);
        Aplikasi::factory()->create(['status' => Aplikasi::STATUS_DITOLAK]);
        Aplikasi::factory()->create(['status' => Aplikasi::STATUS_TIDAK_LAYAK]);

        Cache::forget('aplikasi:stats:v1');

        $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->getJson('/api/aplikasi/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.development', 0)
            ->assertJsonPath('data.operational', 0)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.stopped', 2);
    }

    /**
     * Test semua role yang berwenang can list aplikasi
     */
    public function test_users_can_list_aplikasi(): void
    {
        Aplikasi::factory()->count(3)->create();

        // Pengelola can list
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
                         ->getJson('/api/aplikasi');
        $response->assertStatus(200);

        // User can list (read-only)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->readerToken)
                         ->getJson('/api/aplikasi');
        $response->assertStatus(200);
    }

    /**
     * Test unit kerja only sees its own submitted aplikasi.
     */
    public function test_unit_kerja_only_sees_own_aplikasi_in_list(): void
    {
        $ownApp = Aplikasi::factory()->create(['created_by' => $this->unitKerjaUser->id]);
        Aplikasi::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson('/api/aplikasi');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $ownApp->id]);
        $response->assertJsonCount(1, 'data');
    }

    public function test_unit_kerja_stats_only_count_visible_applications(): void
    {
        Aplikasi::factory()->create([
            'created_by' => $this->unitKerjaUser->id,
            'status' => Aplikasi::STATUS_DIAJUKAN,
        ]);
        Aplikasi::factory()->create([
            'created_by' => $this->unitKerjaUser->id,
            'status' => Aplikasi::STATUS_DEPLOYED_PRODUCTION,
        ]);
        Aplikasi::factory()->create([
            'created_by' => $this->unitKerjaUser->id,
            'status' => Aplikasi::STATUS_NONAKTIF,
        ]);

        $otherUnit = User::factory()->create(['role' => 'unit_kerja']);
        Aplikasi::factory()->create([
            'created_by' => $otherUnit->id,
            'status' => Aplikasi::STATUS_DIAJUKAN,
        ]);
        Aplikasi::factory()->create([
            'created_by' => $this->pengelolaUser->id,
            'status' => Aplikasi::STATUS_DEPLOYED_PRODUCTION,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->unitKerjaToken)
            ->getJson('/api/aplikasi/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.development', 1)
            ->assertJsonPath('data.operational', 1)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.stopped', 0);
    }

    public function test_new_unit_kerja_has_empty_list_and_stats(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->unitKerjaToken)
            ->getJson('/api/aplikasi')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->withHeader('Authorization', 'Bearer '.$this->unitKerjaToken)
            ->getJson('/api/aplikasi/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.development', 0)
            ->assertJsonPath('data.operational', 0)
            ->assertJsonPath('data.inactive', 0)
            ->assertJsonPath('data.stopped', 0);
    }

    public function test_unit_kerja_cannot_see_pengelola_created_uat_application_for_review(): void
    {
        $uatApp = Aplikasi::factory()->create([
            'created_by' => $this->pengelolaUser->id,
            'status' => Aplikasi::STATUS_UAT,
        ]);
        $hiddenDevelopmentApp = Aplikasi::factory()->create([
            'created_by' => $this->pengelolaUser->id,
            'status' => Aplikasi::STATUS_PENGEMBANGAN,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson('/api/aplikasi');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($uatApp->id, $ids);
        $this->assertNotContains($hiddenDevelopmentApp->id, $ids);

        $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson("/api/aplikasi/{$uatApp->id}")
            ->assertStatus(404);
    }

    public function test_aplikasi_index_marks_active_uat_document(): void
    {
        $uatApp = Aplikasi::factory()->create([
            'created_by' => $this->unitKerjaUser->id,
            'status' => Aplikasi::STATUS_UAT,
        ]);

        AplikasiDocument::query()->create([
            'aplikasi_id' => $uatApp->id,
            'document_type' => 'uat',
            'storage_path' => 'aplikasi_documents/uat.pdf',
            'original_filename' => 'uat.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'version' => 1,
            'status' => 'active',
            'uploaded_by' => $this->unitKerjaUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson('/api/aplikasi');

        $response->assertStatus(200);

        $item = collect($response->json('data'))->firstWhere('id', $uatApp->id);
        $this->assertNotNull($item);
        $this->assertTrue((bool) ($item['has_active_uat_document'] ?? false));
    }

    public function test_unit_kerja_cannot_see_other_unit_uat_application(): void
    {
        $otherUnit = User::factory()->create(['role' => 'unit_kerja']);
        $otherUnitApp = Aplikasi::factory()->create([
            'created_by' => $otherUnit->id,
            'status' => Aplikasi::STATUS_UAT,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson('/api/aplikasi');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($otherUnitApp->id, $ids);

        $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson("/api/aplikasi/{$otherUnitApp->id}")
            ->assertStatus(404);
    }

    /**
     * Test unit kerja cannot open detail aplikasi from other users.
     */
    public function test_unit_kerja_cannot_open_other_users_aplikasi_detail(): void
    {
        $otherApp = Aplikasi::factory()->create(['created_by' => $this->pengelolaUser->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson("/api/aplikasi/{$otherApp->id}");

        $response->assertStatus(404);
    }

    /**
     * Test unauthenticated cannot access aplikasi
     */
    public function test_unauthenticated_cannot_access_aplikasi(): void
    {
        $response = $this->getJson('/api/aplikasi');
        $response->assertStatus(401);
    }

    public function test_pengelola_can_list_unit_kerja_pengajuan_notifications(): void
    {
        Aplikasi::factory()->create([
            'status' => 'diajukan',
            'created_by' => $this->unitKerjaUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->getJson('/api/aplikasi/pengelola-notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.status', 'diajukan');
    }

    public function test_pengelola_notifications_excludes_pengelola_created_pengajuan(): void
    {
        Aplikasi::factory()->create([
            'status' => 'diajukan',
            'created_by' => $this->pengelolaUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->pengelolaToken)
            ->getJson('/api/aplikasi/pengelola-notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 0);
    }

    public function test_unit_kerja_cannot_access_pengelola_notifications(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unitKerjaToken)
            ->getJson('/api/aplikasi/pengelola-notifications');

        $response->assertStatus(403);
    }
}
