<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use App\Models\AplikasiNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AplikasiSecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_HEADER_PREFIX = 'Bearer ';

    public function test_tim_uji_keamanan_can_read_and_update_security_review(): void
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
            ->assertJsonPath('data.review.security_test_passed', false)
            ->assertJsonPath('data.review.security_test_notes', 'Masih ada temuan validasi input.');

        $aplikasi->refresh();
        $this->assertSame($timUji->id, $aplikasi->security_tested_by);
        $this->assertFalse($aplikasi->security_test_passed);
        $this->assertNotNull($aplikasi->security_tested_at);

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
            ->assertJsonPath('data.review.security_test_passed', true);

        $this->assertSame(1, AplikasiNote::query()->where('aplikasi_id', $aplikasi->id)->where('note_type', 'uji_keamanan')->count());
        $this->assertDatabaseHas('aplikasi_notes', [
            'aplikasi_id' => $aplikasi->id,
            'note_type' => 'uji_keamanan',
            'body' => "Ringkasan Hasil Uji:\nSemua skenario lolos.",
            'created_by' => $timUji->id,
        ]);
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
