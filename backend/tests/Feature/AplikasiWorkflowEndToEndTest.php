<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AplikasiWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $actors = [];

    /** @var array<string, string> */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'unit_kerja',
            'pengelola_aplikasi',
            'analis_desain',
            'tim_implementasi_aplikasi',
            'tim_uji_keamanan',
            'devops_developer',
        ] as $role) {
            $this->actors[$role] = User::factory()->create(['role' => $role]);
            $this->tokens[$role] = $this->actors[$role]->createToken("e2e-{$role}")->plainTextToken;
        }
    }

    public function test_rejection_and_correction_comments_reach_the_application_owner(): void
    {
        $correctionApp = $this->submitApplication('Koreksi E2E');
        $correctionNote = 'Mohon perbaiki ruang lingkup dan lampiran pengajuan.';

        $this->asRole('pengelola_aplikasi')
            ->postJson("/api/aplikasi/{$correctionApp->id}/workflow/verifikasi-pengajuan", [
                'status_target' => Aplikasi::STATUS_PERLU_PERBAIKAN,
                'catatan' => $correctionNote,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_PERLU_PERBAIKAN);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->actors['unit_kerja']->id,
            'aplikasi_id' => $correctionApp->id,
            'title' => 'Pengajuan Perlu Perbaikan',
            'type' => 'action_required',
        ]);

        $this->asRole('unit_kerja')
            ->getJson("/api/aplikasi/{$correctionApp->id}/workflow")
            ->assertOk()
            ->assertJsonFragment([
                'aksi' => 'Verifikasi Pengajuan',
                'catatan' => $correctionNote,
            ]);

        $notifications = $this->asRole('unit_kerja')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Pengajuan Perlu Perbaikan'])
            ->json('data.notifications');
        $correctionNotification = collect($notifications)->firstWhere('title', 'Pengajuan Perlu Perbaikan');
        $this->assertNotNull($correctionNotification);
        $this->asRole('unit_kerja')
            ->patchJson("/api/notifications/{$correctionNotification['id']}/read")
            ->assertOk();

        $note = $this->asRole('unit_kerja')
            ->postJson("/api/aplikasi/{$correctionApp->id}/notes", [
                'body' => 'Catatan tindak lanjut dari pemilik pengajuan.',
                'note_type' => 'info',
            ])
            ->assertCreated();
        $noteId = $note->json('data.note.id');
        $this->asRole('pengelola_aplikasi')
            ->deleteJson("/api/aplikasi/{$correctionApp->id}/notes/{$noteId}")
            ->assertForbidden();

        $this->asRole('unit_kerja')
            ->postJson("/api/aplikasi/{$correctionApp->id}/workflow/perbaikan-pengajuan", [
                'catatan' => 'Ruang lingkup dan lampiran sudah diperbaiki.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_DIAJUKAN);

        $this->asRole('unit_kerja')
            ->deleteJson("/api/aplikasi/{$correctionApp->id}/notes/{$noteId}")
            ->assertOk();

        $this->asRole('pengelola_aplikasi')
            ->postJson("/api/aplikasi/{$correctionApp->id}/workflow/verifikasi-pengajuan", [
                'status_target' => Aplikasi::STATUS_TERVERIFIKASI,
                'catatan' => 'Perbaikan sudah sesuai.',
            ])
            ->assertOk();

        $this->asRole('analis_desain')
            ->postJson("/api/aplikasi/{$correctionApp->id}/workflow/mulai-analisa-desain")
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_ANALISA_DESAIN);

        $this->asRole('analis_desain')
            ->postJson("/api/aplikasi/{$correctionApp->id}/workflow/studi-kelayakan", [
                'is_layak' => false,
                'catatan' => 'Belum memenuhi prioritas dan kapasitas layanan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_TIDAK_LAYAK);

        $rejectedApp = $this->submitApplication('Ditolak E2E');
        $rejectionNote = 'Pengajuan ditolak karena berada di luar ruang lingkup layanan.';

        $this->asRole('pengelola_aplikasi')
            ->postJson("/api/aplikasi/{$rejectedApp->id}/workflow/verifikasi-pengajuan", [
                'status_target' => Aplikasi::STATUS_DITOLAK,
                'catatan' => $rejectionNote,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_DITOLAK);

        $this->asRole('unit_kerja')
            ->getJson("/api/aplikasi/{$rejectedApp->id}/workflow")
            ->assertOk()
            ->assertJsonFragment(['catatan' => $rejectionNote]);

        $outsider = User::factory()->create(['role' => 'unit_kerja']);
        $outsiderToken = $outsider->createToken('e2e-outsider')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($outsiderToken)
            ->getJson("/api/aplikasi/{$correctionApp->id}/workflow")
            ->assertForbidden();
    }

    public function test_pengelola_created_application_does_not_create_a_submission_notification(): void
    {
        $response = $this->asRole('pengelola_aplikasi')->postJson('/api/aplikasi', [
            'nama_layanan' => 'Layanan Internal Pengelola',
            'nama_singkat' => 'INTPG',
            'nama_aplikasi' => 'Aplikasi Internal Pengelola',
            'jenis_layanan_aplikasi' => 'internal',
            'kode_unitOrganisasi' => 'BSSN-INT',
            'tipe_akuisisi' => 'Custom-Made',
        ]);

        $response->assertCreated();

        $this->assertDatabaseMissing('app_notifications', [
            'aplikasi_id' => $response->json('data.aplikasi.id'),
            'title' => 'Pengajuan Baru Masuk',
        ]);
    }

    public function test_full_workflow_exercises_rework_branches_and_reaches_production(): void
    {
        $aplikasi = $this->submitApplication('Production E2E');

        $this->asRole('pengelola_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-pengajuan", [
                'status_target' => Aplikasi::STATUS_TERVERIFIKASI,
                'catatan' => 'Formulir lengkap dan identitas layanan valid.',
            ])->assertOk();

        $this->asRole('analis_desain')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/mulai-analisa-desain")
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_ANALISA_DESAIN);

        $this->uploadDocument('analis_desain', $aplikasi, 'laporan_analisa_desain', 'laporan-analisis-v1.pdf')
            ->assertCreated();

        $this->asRole('analis_desain')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/studi-kelayakan", [
                'is_layak' => true,
                'catatan' => 'Hasil analisis menunjukkan aplikasi layak dikembangkan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_LAYAK);

        $this->asRole('tim_implementasi_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/mulai-pengembangan", [
                'catatan' => 'Spesifikasi diterima dan implementasi dimulai.',
            ])->assertOk();

        $checklists = $this->asRole('tim_implementasi_aplikasi')
            ->getJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists")
            ->assertOk()
            ->json('data.checklists');

        foreach ($checklists as $checklist) {
            $this->asRole('tim_implementasi_aplikasi')
                ->patchJson("/api/aplikasi/{$aplikasi->id}/implementation-checklists/{$checklist['id']}", [
                    'item_status' => 'done',
                    'notes' => 'Diverifikasi pada pengujian end-to-end.',
                ])->assertOk();
        }

        $this->uploadDocument('tim_implementasi_aplikasi', $aplikasi, 'template_uat', 'template-uat.pdf')->assertCreated();
        $this->uploadDocument('tim_implementasi_aplikasi', $aplikasi, 'petunjuk_aplikasi', 'petunjuk-aplikasi.pdf')->assertCreated();

        $this->asRole('tim_implementasi_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/siap-uat", [
                'catatan' => 'Build dan dokumen pendukung siap diuji.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_UAT);

        $this->uploadDocument('unit_kerja', $aplikasi, 'uat', 'hasil-uat-v1.pdf')->assertCreated();

        $this->asRole('pengelola_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-uat", [
                'is_sesuai' => false,
                'catatan' => 'Alur persetujuan belum sesuai hasil UAT.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_PERBAIKAN_UAT);

        $this->asRole('tim_implementasi_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/selesai-perbaikan-uat", [
                'catatan' => 'Alur persetujuan sudah diperbaiki dan diuji ulang.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_UAT);

        $this->uploadDocument('unit_kerja', $aplikasi, 'uat', 'hasil-uat-v2.pdf')
            ->assertCreated()
            ->assertJsonPath('data.document.version', 2);

        $this->asRole('pengelola_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-uat", [
                'is_sesuai' => true,
                'catatan' => 'UAT ulang sesuai dan dapat masuk uji keamanan.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_UJI_KEAMANAN);

        $this->uploadDocument('tim_uji_keamanan', $aplikasi, 'laporan_uji_keamanan', 'keamanan-v1.pdf')->assertCreated();

        $this->asRole('tim_uji_keamanan')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/hasil-uji-keamanan", [
                'is_lolos' => false,
                'catatan' => 'Header keamanan dan pembatasan akses perlu diperbaiki.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_PERBAIKAN_KEAMANAN);

        $this->asRole('tim_implementasi_aplikasi')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/selesai-perbaikan-keamanan", [
                'catatan' => 'Header dan kontrol akses sudah diperbaiki.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_UJI_KEAMANAN);

        $this->uploadDocument('tim_uji_keamanan', $aplikasi, 'laporan_uji_keamanan', 'keamanan-v2.pdf')
            ->assertCreated()
            ->assertJsonPath('data.document.version', 2);

        $this->asRole('tim_uji_keamanan')
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/hasil-uji-keamanan", [
                'is_lolos' => true,
                'catatan' => 'Pengujian ulang lulus tanpa temuan kritis.',
            ])->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_SIAP_DEPLOY);

        $this->asRole('devops_developer')
            ->putJson("/api/aplikasi/{$aplikasi->id}/deployment-status", [
                'environment' => 'production',
                'deployed' => true,
                'notes' => 'Percobaan production sebelum staging.',
            ])->assertUnprocessable();

        $this->asRole('devops_developer')
            ->putJson("/api/aplikasi/{$aplikasi->id}/deployment-status", [
                'environment' => 'staging',
                'deployed' => true,
                'notes' => 'Deployment staging dan smoke test berhasil.',
            ])->assertOk();

        $this->asRole('devops_developer')
            ->putJson("/api/aplikasi/{$aplikasi->id}/deployment-status", [
                'environment' => 'production',
                'deployed' => true,
                'notes' => 'Deployment production berhasil.',
            ])->assertOk()
            ->assertJsonPath('data.deployment.production.deployed', true);

        $this->assertDatabaseHas('aplikasis', [
            'id' => $aplikasi->id,
            'status' => Aplikasi::STATUS_DEPLOYED_PRODUCTION,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->actors['unit_kerja']->id,
            'aplikasi_id' => $aplikasi->id,
            'title' => 'Aplikasi Anda Sudah Live!',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->actors['devops_developer']->id,
            'aplikasi_id' => $aplikasi->id,
            'title' => 'Siap untuk Deployment',
        ]);

        foreach (array_keys($this->actors) as $role) {
            $this->asRole($role)
                ->getJson("/api/aplikasi/{$aplikasi->id}/workflow")
                ->assertOk()
                ->assertJsonFragment(['catatan' => 'Deployment production berhasil.']);
        }
    }

    private function submitApplication(string $suffix): Aplikasi
    {
        $response = $this->asRole('unit_kerja')->postJson('/api/aplikasi', [
            'nama_layanan' => "Layanan {$suffix}",
            'nama_singkat' => 'E2E'.random_int(10, 99),
            'nama_aplikasi' => "Aplikasi {$suffix}",
            'jenis_layanan_aplikasi' => 'internal',
            'kode_unitOrganisasi' => 'BSSN-E2E',
            'tipe_akuisisi' => 'Custom-Made',
        ]);

        $response->assertCreated()->assertJsonPath('data.aplikasi.status', Aplikasi::STATUS_DIAJUKAN);
        $aplikasi = Aplikasi::query()->findOrFail($response->json('data.aplikasi.id'));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->actors['pengelola_aplikasi']->id,
            'aplikasi_id' => $aplikasi->id,
            'title' => 'Pengajuan Baru Masuk',
        ]);

        $this->uploadDocument('unit_kerja', $aplikasi, 'formulir_pengajuan', 'formulir-pengajuan.pdf')
            ->assertCreated();

        return $aplikasi;
    }

    private function uploadDocument(string $role, Aplikasi $aplikasi, string $type, string $filename)
    {
        return $this->asRole($role)->post("/api/aplikasi/{$aplikasi->id}/documents", [
            'document_type' => $type,
            'file' => UploadedFile::fake()->create($filename, 32, 'application/pdf'),
            'notes' => "Dokumen {$filename} untuk pengujian end-to-end.",
        ], ['Accept' => 'application/json']);
    }

    private function asRole(string $role): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokens[$role]);
    }
}
