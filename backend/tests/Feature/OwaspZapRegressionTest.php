<?php

namespace Tests\Feature;

use App\Models\AnalisaDesain;
use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwaspZapRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_filters_reject_path_and_sql_like_payloads(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $this->withToken($pengelola->createToken('zap-regression')->plainTextToken);

        $this->getJson('/api/aplikasi?per_page=30&page=%2Faplikasi')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        $this->getJson('/api/aplikasi?per_page=30%20OR%201%3D1%20--&page=1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/aplikasi?status=deployed_production%20OR%201%3D1%20--')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->getJson('/api/rfc?per_page=30&page=rfc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        $this->getJson('/api/analisa-desain?aplikasi_id=%5Canalisa-desain')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aplikasi_id']);
    }

    public function test_analysis_list_and_application_filter_do_not_disclose_application_errors(): void
    {
        $analis = User::factory()->create(['role' => 'analis_desain']);
        $aplikasi = Aplikasi::factory()->create();
        $analysis = AnalisaDesain::query()->create([
            'aplikasi_id' => $aplikasi->id,
            'ui_platform' => 'layanan',
        ]);

        $this->withToken($analis->createToken('zap-regression')->plainTextToken);

        $this->getJson('/api/analisa-desain')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $analysis->id)
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');

        $this->getJson("/api/analisa-desain?aplikasi_id={$aplikasi->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.aplikasi_id', $aplikasi->id)
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }

    public function test_batch_analysis_rejects_sql_operator_payload_without_writing_data(): void
    {
        $analis = User::factory()->create(['role' => 'analis_desain']);
        $aplikasi = Aplikasi::factory()->create();

        $this->withToken($analis->createToken('zap-regression')->plainTextToken)
            ->putJson("/api/analisa-desain/batch/{$aplikasi->id}", [
                'items' => [
                    ['interop_type' => 'test OR 1=1 --'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.interop_type']);

        $this->assertDatabaseMissing('analisa_desains', [
            'aplikasi_id' => $aplikasi->id,
            'interop_type' => 'test OR 1=1 --',
        ]);
    }

    public function test_invalid_workflow_target_is_validated_before_business_state(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_DEPLOYED_PRODUCTION]);

        $this->withToken($pengelola->createToken('zap-regression')->plainTextToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/verifikasi-pengajuan", [
                'status_target' => "terverifikasi' OR '1'='1' --",
                'catatan' => 'Probe keamanan.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status_target']);

        $this->assertSame(Aplikasi::STATUS_DEPLOYED_PRODUCTION, $aplikasi->fresh()->status);
    }

    public function test_note_body_is_stored_as_text_and_never_used_as_a_filesystem_path(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $aplikasi = Aplikasi::factory()->create();

        $this->withToken($pengelola->createToken('zap-regression')->plainTextToken)
            ->postJson("/api/aplikasi/{$aplikasi->id}/notes", [
                'body' => '\\notes',
                'note_type' => 'info',
            ])
            ->assertCreated()
            ->assertJsonPath('data.note.body', '\\notes')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');

        $this->assertDatabaseHas('aplikasi_notes', [
            'aplikasi_id' => $aplikasi->id,
            'body' => '\\notes',
        ]);
    }

    public function test_login_password_is_not_interpreted_as_sql(): void
    {
        User::factory()->create([
            'email' => 'scanner@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'scanner@example.com',
            'password' => 'password123 AND 1=1 --',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Kredensial tidak valid.')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');

        $this->postJson('/api/login', [
            'email' => 'scanner@example.com',
            'password' => 'password123',
        ])->assertOk();
    }
}
