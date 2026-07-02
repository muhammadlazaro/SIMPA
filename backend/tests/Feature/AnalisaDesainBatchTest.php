<?php

namespace Tests\Feature;

use App\Models\AnalisaDesain;
use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaDesainBatchTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_HEADER_PREFIX = 'Bearer ';

    public function test_batch_update_rejects_unsupported_storage_type(): void
    {
        $analis = User::factory()->create(['role' => 'analis_desain']);
        $token = $analis->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create();

        $response = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/analisa-desain/batch/{$app->id}", [
                'items' => [
                    ['storage_type' => 'cache'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.storage_type']);

        $this->assertDatabaseMissing('analisa_desains', [
            'aplikasi_id' => $app->id,
            'storage_type' => 'cache',
        ]);
    }

    public function test_batch_update_accepts_supported_storage_type(): void
    {
        $analis = User::factory()->create(['role' => 'analis_desain']);
        $token = $analis->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create();

        $response = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->putJson("/api/analisa-desain/batch/{$app->id}", [
                'items' => [
                    ['storage_type' => 'object-storage'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1);

        $this->assertDatabaseHas('analisa_desains', [
            'aplikasi_id' => $app->id,
            'storage_type' => 'object-storage',
        ]);
    }
}
