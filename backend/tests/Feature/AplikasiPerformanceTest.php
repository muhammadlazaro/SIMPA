<?php

namespace Tests\Feature;

use App\Models\AnalisaDesain;
use App\Models\Aplikasi;
use App\Models\AplikasiChecklist;
use App\Models\AplikasiNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AplikasiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const STATS_ENDPOINT = '/api/aplikasi/stats';

    public function test_aplikasi_index_avoids_n_plus_one_queries(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        Sanctum::actingAs($pengelola);

        Aplikasi::factory()->count(40)->create([
            'created_by' => $pengelola->id,
            'updated_by' => $pengelola->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/aplikasi?per_page=20')
            ->assertStatus(200)
            ->assertJsonCount(20, 'data');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Expect only pagination + eager loading queries, not one query per row.
        $this->assertLessThanOrEqual(6, $queryCount, 'Too many queries detected for aplikasi index.');
    }

    public function test_aplikasi_stats_uses_cache_between_calls(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        Sanctum::actingAs($pengelola);

        Aplikasi::factory()->count(10)->create();

        Cache::forget('aplikasi:stats:v1');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson(self::STATS_ENDPOINT)
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['development', 'operational', 'inactive', 'stopped']]);

        $firstSqlLog = DB::getQueryLog();
        $firstAplikasiQueryCount = count(array_filter(
            $firstSqlLog,
            static fn (array $entry): bool => str_contains(strtolower((string) ($entry['query'] ?? '')), 'from "aplikasis"')
        ));

        DB::flushQueryLog();

        $this->getJson(self::STATS_ENDPOINT)
            ->assertStatus(200);

        $secondSqlLog = DB::getQueryLog();
        DB::disableQueryLog();

        $secondAplikasiQueryCount = count(array_filter(
            $secondSqlLog,
            static fn (array $entry): bool => str_contains(strtolower((string) ($entry['query'] ?? '')), 'from "aplikasis"')
        ));

        $this->assertGreaterThanOrEqual(1, $firstAplikasiQueryCount, 'First stats call should hit aplikasi aggregate query.');
        $this->assertSame(0, $secondAplikasiQueryCount, 'Second stats call should be served from cache.');
    }

    public function test_aplikasi_stats_cache_invalidated_after_update(): void
    {
        $devops = User::factory()->create(['role' => 'devops_developer']);
        Sanctum::actingAs($devops);

        $aplikasi = Aplikasi::factory()->create([
            'status' => 'deployed_staging',
            'deployed_staging_at' => now(),
            'deployed_staging_by' => $devops->id,
            'created_by' => $devops->id,
            'updated_by' => $devops->id,
        ]);

        Cache::forget('aplikasi:stats:v1');

        $this->getJson(self::STATS_ENDPOINT)
            ->assertStatus(200)
            ->assertJsonPath('data.development', 1)
            ->assertJsonPath('data.operational', 0);

        $this->putJson('/api/aplikasi/'.$aplikasi->getKey().'/deployment-status', [
            'environment' => 'production',
            'deployed' => true,
        ])->assertStatus(200);

        $this->getJson(self::STATS_ENDPOINT)
            ->assertStatus(200)
            ->assertJsonPath('data.development', 0)
            ->assertJsonPath('data.operational', 1);
    }

    public function test_aplikasi_detail_query_count_remains_stable_with_related_data(): void
    {
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        Sanctum::actingAs($pengelola);

        $aplikasi = Aplikasi::factory()->create([
            'created_by' => $pengelola->id,
            'updated_by' => $pengelola->id,
            'security_tested_by' => $pengelola->id,
        ]);

        for ($i = 0; $i < 25; $i++) {
            AplikasiChecklist::query()->create([
                'aplikasi_id' => $aplikasi->getKey(),
                'category' => 'studi_kelayakan',
                'title' => 'Checklist '.$i,
                'item_status' => 'pending',
                'notes' => null,
                'sort_order' => $i + 1,
                'created_by' => $pengelola->id,
                'updated_by' => $pengelola->id,
            ]);
        }

        for ($i = 0; $i < 25; $i++) {
            AplikasiNote::query()->create([
                'aplikasi_id' => $aplikasi->getKey(),
                'note_type' => 'info',
                'body' => 'Note '.$i,
                'is_checked' => false,
                'created_by' => $pengelola->id,
                'checked_by' => null,
                'checked_at' => null,
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            AnalisaDesain::query()->create([
                'aplikasi_id' => $aplikasi->getKey(),
                'ui_platform' => 'web',
                'interop_type' => 'api',
                'storage_type' => 'database',
                'nama_aktor' => 'Aktor '.$i,
                'method' => 'GET',
                'url' => '/resource-'.$i,
                'tipe_resource' => 'public',
                'aktor_transaksi' => 'User',
                'created_by' => $pengelola->id,
                'updated_by' => $pengelola->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/aplikasi/'.$aplikasi->getKey())
            ->assertStatus(200)
            ->assertJsonCount(25, 'data.checklists')
            ->assertJsonCount(25, 'data.notes')
            ->assertJsonCount(10, 'data.analisa_desains');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Eager-loaded relations should keep query count bounded regardless of child row counts.
        $this->assertLessThanOrEqual(25, $queryCount, 'Detail endpoint query count is unexpectedly high.');
    }
}
