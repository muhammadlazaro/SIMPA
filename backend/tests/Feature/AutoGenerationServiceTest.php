<?php

namespace Tests\Feature;

use App\Models\AnalisaDesain;
use App\Models\Aplikasi;
use App\Services\AutoGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_preserves_expected_analysis_dimensions(): void
    {
        $aplikasi = Aplikasi::factory()->publik()->create();
        $service = app(AutoGenerationService::class);

        $result = $service->generateAllConfigurations($aplikasi->getKey());

        $this->assertTrue($result['success']);
        $this->assertCount(10, $result['data']['analisa_desain']);
        $this->assertDatabaseHas('analisa_desains', [
            'aplikasi_id' => $aplikasi->getKey(),
            'ui_platform' => 'layanan',
        ]);
        $this->assertDatabaseHas('analisa_desains', [
            'aplikasi_id' => $aplikasi->getKey(),
            'method' => 'GET',
            'url' => '/api/documents',
            'tipe_resource' => 'terbuka',
        ]);

        $aplikasi->update(['jenis_layanan_aplikasi' => 'internal']);
        $updated = $service->generateAllConfigurations($aplikasi->getKey());

        $this->assertTrue($updated['success']);
        $this->assertCount(9, $updated['data']['analisa_desain']);
        $this->assertDatabaseMissing('analisa_desains', [
            'aplikasi_id' => $aplikasi->getKey(),
            'ui_platform' => 'layanan',
            'deleted_at' => null,
        ]);
        $this->assertSame(
            9,
            AnalisaDesain::query()->where('aplikasi_id', $aplikasi->getKey())->count(),
        );
    }
}
