<?php

namespace Tests\Feature;

use App\Enums\AplikasiJenisDokumen;
use App\Models\Aplikasi;
use App\Models\AplikasiDocument;
use App\Models\AplikasiNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AplikasiSecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_HEADER_PREFIX = 'Bearer ';

    public function test_tim_uji_keamanan_can_read_and_save_security_review_notes_without_finalizing_verdict(): void
    {
        $timUji = User::factory()->create(['role' => 'tim_uji_keamanan']);
        $token = $timUji->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_UJI_KEAMANAN]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/security-review")
            ->assertStatus(200)
            ->assertJsonPath('data.review.security_test_passed', null);

        $payload = [
            'security_test_passed' => false,
            'security_test_notes' => 'Masih ada temuan validasi input.',
            'note' => 'Perbaiki sanitasi payload sebelum retest.',
        ];

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$aplikasi->id}/security-review", $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.review.security_test_passed', null)
            ->assertJsonPath('data.review.security_test_notes', 'Masih ada temuan validasi input.');

        $aplikasi->refresh();
        $this->assertNull($aplikasi->security_tested_by);
        $this->assertNull($aplikasi->security_test_passed);
        $this->assertNull($aplikasi->security_tested_at);

        $this->assertDatabaseHas('aplikasi_notes', [
            'aplikasi_id' => $aplikasi->id,
            'note_type' => 'uji_keamanan',
            'body' => "Ringkasan Hasil Uji:\nMasih ada temuan validasi input.\n\nCatatan Perbaikan:\nPerbaiki sanitasi payload sebelum retest.",
            'created_by' => $timUji->id,
        ]);
    }

    public function test_pengelola_can_view_but_cannot_update_security_review(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $token = $pengelola->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create();

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/security-review")
            ->assertStatus(200);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$aplikasi->id}/security-review", [
                'security_test_passed' => true,
            ])
            ->assertStatus(403);
    }

    public function test_non_security_roles_cannot_access_security_review(): void
    {
        $implementer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $token = $implementer->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create();

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$aplikasi->id}/security-review")
            ->assertStatus(403);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$aplikasi->id}/security-review", [
                'security_test_passed' => true,
            ])
            ->assertStatus(403);
    }

    public function test_update_with_security_summary_creates_security_history_note(): void
    {
        $timUji = User::factory()->create(['role' => 'tim_uji_keamanan']);
        $token = $timUji->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_UJI_KEAMANAN]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$aplikasi->id}/security-review", [
                'security_test_passed' => true,
                'security_test_notes' => 'Semua skenario lolos.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.review.security_test_passed', null);

        $this->assertSame(1, AplikasiNote::query()->where('aplikasi_id', $aplikasi->id)->where('note_type', 'uji_keamanan')->count());
        $this->assertDatabaseHas('aplikasi_notes', [
            'aplikasi_id' => $aplikasi->id,
            'note_type' => 'uji_keamanan',
            'body' => "Ringkasan Hasil Uji:\nSemua skenario lolos.",
            'created_by' => $timUji->id,
        ]);
    }

    public function test_security_verdict_is_saved_atomically_and_cannot_be_submitted_twice(): void
    {
        $timUji = User::factory()->create(['role' => 'tim_uji_keamanan']);
        $token = $timUji->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create([
            'status' => Aplikasi::STATUS_UJI_KEAMANAN,
            'security_test_passed' => null,
        ]);

        AplikasiDocument::query()->create([
            'aplikasi_id' => $aplikasi->id,
            'document_type' => AplikasiJenisDokumen::LaporanUjiKeamanan,
            'storage_path' => 'aplikasi_documents/security-report.pdf',
            'storage_disk' => 'local',
            'original_filename' => 'security-report.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'version' => 1,
            'status' => 'active',
            'uploaded_by' => $timUji->id,
        ]);

        $payload = [
            'is_lolos' => true,
            'catatan' => 'Seluruh pengujian keamanan berhasil.',
            'security_test_notes' => 'Tidak ditemukan kerentanan kritis.',
            'note' => 'Pantau dependency secara berkala.',
        ];

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/hasil-uji-keamanan", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_SIAP_DEPLOY);

        $aplikasi->refresh();
        $this->assertSame(Aplikasi::STATUS_SIAP_DEPLOY, $aplikasi->status);
        $this->assertTrue($aplikasi->security_test_passed);
        $this->assertSame('Tidak ditemukan kerentanan kritis.', $aplikasi->security_test_notes);
        $this->assertSame($timUji->id, $aplikasi->security_tested_by);
        $this->assertNotNull($aplikasi->security_tested_at);
        $this->assertDatabaseHas('aplikasi_notes', [
            'aplikasi_id' => $aplikasi->id,
            'note_type' => 'uji_keamanan',
            'body' => "Ringkasan Hasil Uji:\nTidak ditemukan kerentanan kritis.\n\nCatatan Perbaikan:\nPantau dependency secara berkala.",
            'created_by' => $timUji->id,
        ]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/hasil-uji-keamanan", [
                ...$payload,
                'is_lolos' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Keputusan uji keamanan sudah disimpan dan tidak dapat dikirim ulang.');

        $this->assertSame(1, $aplikasi->notes()->where('note_type', 'uji_keamanan')->count());
        $this->assertSame(1, $aplikasi->statusHistories()->where('aksi', 'Hasil Uji Keamanan')->count());
    }

    public function test_completed_security_remediation_resets_previous_verdict_for_retest(): void
    {
        $implementer = User::factory()->create(['role' => 'tim_implementasi_aplikasi']);
        $token = $implementer->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create([
            'status' => Aplikasi::STATUS_PERBAIKAN_KEAMANAN,
            'security_test_passed' => false,
            'security_test_notes' => 'Masih ditemukan celah kontrol akses.',
            'security_tested_by' => $implementer->id,
            'security_tested_at' => now(),
        ]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->postJson("/api/aplikasi/{$aplikasi->id}/workflow/selesai-perbaikan-keamanan", [
                'catatan' => 'Kontrol akses sudah diperbaiki dan siap diuji ulang.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Aplikasi::STATUS_UJI_KEAMANAN);

        $aplikasi->refresh();
        $this->assertSame(Aplikasi::STATUS_UJI_KEAMANAN, $aplikasi->status);
        $this->assertNull($aplikasi->security_test_passed);
        $this->assertNull($aplikasi->security_test_notes);
        $this->assertNull($aplikasi->security_tested_by);
        $this->assertNull($aplikasi->security_tested_at);
    }

    public function test_security_review_cannot_be_updated_before_security_stage(): void
    {
        $timUji = User::factory()->create(['role' => 'tim_uji_keamanan']);
        $token = $timUji->createToken('t')->plainTextToken;
        $aplikasi = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_PENGEMBANGAN]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/aplikasi/{$aplikasi->id}/security-review", [
                'security_test_passed' => true,
            ])
            ->assertStatus(422);
    }
}
