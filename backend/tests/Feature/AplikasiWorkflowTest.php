<?php

namespace Tests\Feature;

use App\Enums\AplikasiJenisDokumen;
use App\Models\Aplikasi;
use App\Models\AplikasiChecklist;
use App\Models\AplikasiDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AplikasiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_HEADER_PREFIX = 'Bearer ';

    public function test_pengelola_can_manage_checklists_and_notes(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $token = $pengelola->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create();

        $checklist = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/checklists", [
                'title' => 'Dokumen pengusulan diverifikasi',
                'notes' => 'Perlu lampiran pendukung',
            ]);
        $checklist->assertStatus(201);

        $checklistId = $checklist->json('data.checklist.id');

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->patchJson("/api/aplikasi/{$aplikasi->id}/checklists/{$checklistId}", [
                'item_status' => 'done',
            ])->assertStatus(200)
            ->assertJsonPath('data.checklist.item_status', 'done');

        $note = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/notes", [
                'body' => 'Perlu tindak lanjut ke tim uji keamanan.',
            ]);
        $note->assertStatus(201);
        $noteId = $note->json('data.note.id');

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->patchJson("/api/aplikasi/{$aplikasi->id}/notes/{$noteId}", [
                'is_checked' => true,
            ])->assertStatus(200)
            ->assertJsonPath('data.note.is_checked', true);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/workflow")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.checklists')
            ->assertJsonCount(1, 'data.notes');
    }

    public function test_participating_roles_can_view_workflow_data(): void
    {
        $user = User::factory()->create(['role' => 'unit_kerja']);
        $token = $user->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['created_by' => $user->id]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/workflow")
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['checklists', 'notes']]);
    }

    public function test_unit_kerja_cannot_view_other_application_workflow_data(): void
    {
        $user = User::factory()->create(['role' => 'unit_kerja']);
        $token = $user->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create();

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/workflow")
            ->assertStatus(403);
    }

    public function test_tim_implementasi_can_manage_implementation_checklist(): void
    {
        $implementer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $token = $implementer->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_PENGEMBANGAN]);

        $index = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists");

        $index->assertStatus(200)
            ->assertJsonPath('data.category', 'implementation_progress')
            ->assertJsonCount(7, 'data.checklists');

        $checklistId = $index->json('data.checklists.0.id');

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->patchJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists/{$checklistId}", [
                'item_status' => 'done',
            ])->assertStatus(200)
            ->assertJsonPath('data.checklist.item_status', 'done')
            ->assertJsonPath('data.checklist.category', 'implementation_progress');

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists", [
                'title' => 'Finalisasi komponen dan validasi UI',
                'notes' => 'Tunggu umpan balik pengguna internal',
            ])->assertStatus(201)
            ->assertJsonPath('data.checklist.category', 'implementation_progress');
    }

    public function test_devops_can_manage_devops_checklist_on_deployment_stage(): void
    {
        $devops = User::factory()->create(['role' => 'devops_developer']);
        $token = $devops->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_SIAP_DEPLOY]);

        $index = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists");

        $index->assertStatus(200)
            ->assertJsonPath('data.category', 'devops_progress')
            ->assertJsonCount(3, 'data.checklists');

        $checklistId = $index->json('data.checklists.0.id');

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->patchJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists/{$checklistId}", [
                'item_status' => 'done',
            ])->assertStatus(200)
            ->assertJsonPath('data.checklist.item_status', 'done')
            ->assertJsonPath('data.checklist.category', 'devops_progress');
    }

    public function test_implementation_checklist_is_isolated_by_role_category(): void
    {
        $implementer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $implementerToken = $implementer->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_PENGEMBANGAN]);

        $frontendChecklist = AplikasiChecklist::query()->create([
            'aplikasi_id' => $aplikasi->id,
            'category' => 'frontend_progress',
            'title' => 'Implementasi UI',
            'item_status' => 'pending',
            'notes' => null,
            'sort_order' => 1,
            'created_by' => $implementer->id,
            'updated_by' => $implementer->id,
        ]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$implementerToken)
            ->patchJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists/{$frontendChecklist->id}", [
                'item_status' => 'done',
            ])->assertStatus(403);

        $this->assertDatabaseHas('aplikasi_checklists', [
            'id' => $frontendChecklist->id,
            'category' => 'frontend_progress',
            'item_status' => 'pending',
        ]);
    }

    public function test_pengajuan_cannot_be_approved_without_formulir_pengajuan(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $token = $pengelola->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_DIAJUKAN]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-pengajuan", [
                'status_target' => Aplikasi::STATUS_TERVERIFIKASI,
                'catatan' => 'Dokumen sudah dicek.',
            ])
            ->assertStatus(422);

        $this->createActiveDocument($aplikasi, AplikasiJenisDokumen::FormulirPengajuan, $pengelola);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-pengajuan", [
                'status_target' => Aplikasi::STATUS_TERVERIFIKASI,
                'catatan' => 'Dokumen sudah dicek.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Aplikasi::STATUS_TERVERIFIKASI);
    }

    public function test_unit_kerja_cannot_resubmit_other_users_correction(): void
    {
        $owner = User::factory()->create(['role' => 'unit_kerja']);
        $other = User::factory()->create(['role' => 'unit_kerja']);
        $otherToken = $other->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create([
            'created_by' => $owner->id,
            'status' => Aplikasi::STATUS_PERLU_PERBAIKAN,
        ]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$otherToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/perbaikan-pengajuan", [
                'catatan' => 'Saya coba kirim ulang.',
            ])
            ->assertStatus(403);
    }

    public function test_pengembangan_cannot_start_without_laporan_analisa_desain(): void
    {
        $implementer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $token = $implementer->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_ANALISA_DESAIN]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/mulai-pengembangan", [
                'catatan' => 'Mulai implementasi.',
            ])
            ->assertStatus(422);

        $this->createActiveDocument($aplikasi, AplikasiJenisDokumen::LaporanAnalisaDesain, $implementer);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/mulai-pengembangan", [
                'catatan' => 'Mulai implementasi.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Aplikasi::STATUS_PENGEMBANGAN);
    }

    public function test_siap_uat_requires_completed_implementation_checklist_and_documents(): void
    {
        $implementer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $token = $implementer->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_PENGEMBANGAN]);

        AplikasiChecklist::query()->create([
            'aplikasi_id' => $aplikasi->id,
            'category' => 'implementation_progress',
            'title' => 'Implementasi endpoint transaksi',
            'item_status' => 'pending',
            'notes' => null,
            'sort_order' => 1,
            'created_by' => $implementer->id,
            'updated_by' => $implementer->id,
        ]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/siap-uat", [
                'catatan' => 'Siap diuji.',
            ])
            ->assertStatus(422);

        AplikasiChecklist::query()
            ->where('aplikasi_id', $aplikasi->id)
            ->where('category', 'implementation_progress')
            ->update(['item_status' => 'done']);

        $this->createActiveDocument($aplikasi, AplikasiJenisDokumen::TemplateUat, $implementer);
        $this->createActiveDocument($aplikasi, AplikasiJenisDokumen::PetunjukAplikasi, $implementer);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/siap-uat", [
                'catatan' => 'Siap diuji.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Aplikasi::STATUS_UAT);
    }

    public function test_uat_approval_and_security_result_require_supporting_documents(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $security = User::factory()->create(['role' => 'tim_uji_keamanan']);
        $pengelolaToken = $pengelola->createToken('t')->plainTextToken;
        $securityToken = $security->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_UAT]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$pengelolaToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-uat", [
                'is_sesuai' => true,
                'catatan' => 'UAT sesuai.',
            ])
            ->assertStatus(422);

        $this->createActiveDocument($aplikasi, AplikasiJenisDokumen::Uat, $pengelola);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$pengelolaToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-uat", [
                'is_sesuai' => true,
                'catatan' => 'UAT sesuai.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Aplikasi::STATUS_UJI_KEAMANAN);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$securityToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/hasil-uji-keamanan", [
                'is_lolos' => true,
                'catatan' => 'Lolos uji keamanan.',
            ])
            ->assertStatus(422);

        $this->createActiveDocument($aplikasi->fresh(), AplikasiJenisDokumen::LaporanUjiKeamanan, $security);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$securityToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/hasil-uji-keamanan", [
                'is_lolos' => true,
                'catatan' => 'Lolos uji keamanan.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Aplikasi::STATUS_SIAP_DEPLOY);
    }

    public function test_deployment_must_follow_staging_then_production_sequence(): void
    {
        $devops = User::factory()->create(['role' => 'devops_developer']);
        $token = $devops->createToken('t')->plainTextToken;

        $notReady = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_PENGEMBANGAN]);
        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$notReady->id}/deployment-status", [
                'environment' => 'staging',
                'deployed' => true,
            ])
            ->assertStatus(422);

        $ready = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_SIAP_DEPLOY]);
        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$ready->id}/workflow/deploy", [
                'catatan' => 'Coba endpoint lama.',
            ])
            ->assertStatus(410);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$ready->id}/deployment-status", [
                'environment' => 'production',
                'deployed' => true,
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$ready->id}/deployment-status", [
                'environment' => 'staging',
                'deployed' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.deployment.staging.deployed', true);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$ready->id}/deployment-status", [
                'environment' => 'production',
                'deployed' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.deployment.production.deployed', true);

        $this->assertDatabaseHas('aplikasis', [
            'id' => $ready->id,
            'status' => Aplikasi::STATUS_DEPLOYED_PRODUCTION,
        ]);
    }

    private function createActiveDocument(Aplikasi $aplikasi, AplikasiJenisDokumen $type, User $user): AplikasiDocument
    {
        return AplikasiDocument::query()->create([
            'aplikasi_id' => $aplikasi->id,
            'document_type' => $type,
            'storage_path' => 'aplikasi_documents/dummy.pdf',
            'original_filename' => $type->value.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'version' => 1,
            'status' => 'active',
            'uploaded_by' => $user->id,
        ]);
    }
}
