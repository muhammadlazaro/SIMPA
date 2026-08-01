<?php

namespace App\Http\Controllers;

use App\Http\Helpers\ApiResponse;
use App\Http\Helpers\QueryHelper;
use App\Http\Requests\StoreAnalisaDesainRequest;
use App\Http\Requests\UpdateAnalisaDesainRequest;
use App\Models\AnalisaDesain;
use App\Models\Aplikasi;
use App\Services\AutoGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalisaDesainController extends Controller
{
    protected $autoGenerationService;

    public function __construct(AutoGenerationService $autoGenerationService)
    {
        $this->autoGenerationService = $autoGenerationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'aplikasi_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $query = AnalisaDesain::query();

        if (isset($validated['aplikasi_id'])) {
            $query->where('aplikasi_id', $validated['aplikasi_id']);
        }

        if ($search = ($validated['q'] ?? null)) {
            $escaped = QueryHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('ui_platform', 'like', "%{$escaped}%")
                    ->orWhere('interop_type', 'like', "%{$escaped}%")
                    ->orWhere('storage_type', 'like', "%{$escaped}%")
                    ->orWhere('nama_aktor', 'like', "%{$escaped}%")
                    ->orWhere('method', 'like', "%{$escaped}%")
                    ->orWhere('url', 'like', "%{$escaped}%")
                    ->orWhere('tipe_resource', 'like', "%{$escaped}%")
                    ->orWhere('aktor_transaksi', 'like', "%{$escaped}%")
                  // Search by application name
                    ->orWhereHas('aplikasi', function ($appQuery) use ($escaped) {
                        $appQuery->where('nama_aplikasi', 'like', "%{$escaped}%")
                            ->orWhere('nama_layanan', 'like', "%{$escaped}%")
                            ->orWhere('nama_singkat', 'like', "%{$escaped}%");
                    });
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $items = $query->with('aplikasi')->orderByDesc('id')->paginate($perPage);

        return ApiResponse::paginated($items);
    }

    /**
     * Ringkasan field analisa per banyak aplikasi (satu query, untuk tabel dashboard).
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'aplikasi_ids' => ['required', 'array', 'max:100'],
            'aplikasi_ids.*' => ['integer', 'min:1'],
        ]);

        $ids = array_values(array_unique($validated['aplikasi_ids']));

        $rows = AnalisaDesain::query()
            ->whereIn('aplikasi_id', $ids)
            ->get([
                'aplikasi_id',
                'ui_platform',
                'interop_type',
                'storage_type',
                'nama_aktor',
                'method',
                'url',
            ]);

        $out = $this->initializeSummaryByIds($ids);

        foreach ($rows as $row) {
            $this->appendSummaryRow($out, $row);
        }

        return ApiResponse::success($out);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAnalisaDesainRequest $request): JsonResponse
    {
        $item = AnalisaDesain::create($request->validated());

        return ApiResponse::created(
            $item->load('aplikasi'),
            'Analisa desain berhasil disimpan'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $item = AnalisaDesain::with('aplikasi')->findOrFail($id);

        return ApiResponse::success($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAnalisaDesainRequest $request, string $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $item = AnalisaDesain::findOrFail($id);
                $item->update($request->validated());

                return ApiResponse::success(
                    $item->load('aplikasi'),
                    'Analisa desain berhasil diperbarui'
                );
            });
        } catch (\Exception $e) {
            Log::error('Failed to update analisa desain', [
                'analisa_desain_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal memperbarui analisa desain', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id) {
                $item = AnalisaDesain::findOrFail($id);
                $item->delete();

                return ApiResponse::success(null, 'Analisa desain berhasil dihapus');
            });
        } catch (\Exception $e) {
            Log::error('Failed to delete analisa desain', [
                'analisa_desain_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal menghapus analisa desain', null, 500);
        }
    }

    /**
     * Batch update analisa desain for an aplikasi
     * This replaces all existing data with new data in one transaction
     */
    public function batchUpdate(Request $request, string $aplikasiId): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:100'],
            'items.*.ui_platform' => ['nullable', 'in:dws,layanan'],
            'items.*.interop_type' => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pN][\pL\pN .,_:\/()&+\-]*$/u'],
            'items.*.storage_type' => ['nullable', 'in:db,object-storage'],
            'items.*.nama_aktor' => ['nullable', 'string', 'max:255'],
            'items.*.method' => ['nullable', 'in:GET,POST,PUT,DELETE,PATCH'],
            'items.*.url' => ['nullable', 'string', 'max:500'],
            'items.*.tipe_resource' => ['nullable', 'in:terbuka,tertutup'],
            'items.*.aktor_transaksi' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return DB::transaction(function () use ($request, $validated, $aplikasiId) {
                // Verify aplikasi exists
                Aplikasi::findOrFail($aplikasiId);

                // Delete all existing analisa desain for this aplikasi (except UI Platform)
                AnalisaDesain::where('aplikasi_id', $aplikasiId)
                    ->whereNull('ui_platform') // Keep auto-generated UI Platform
                    ->delete();

                $user = $request->user();
                $userId = $user?->getKey();

                // Bulk insert new data — pass userId so created_by gets set
                // (Model::insert() bypasses Eloquent events, so we must set it manually)
                $items = $this->buildBatchRows($validated['items'], $aplikasiId, $userId);

                if (! empty($items)) {
                    AnalisaDesain::insert($items);
                }

                Log::info('Analisa desain batch updated', [
                    'aplikasi_id' => $aplikasiId,
                    'items_count' => count($items),
                    'user_id' => $userId,
                ]);

                return ApiResponse::success([
                    'count' => count($items),
                    'aplikasi_id' => $aplikasiId,
                ], 'Data analisa desain berhasil diupdate');
            });
        } catch (\Exception $e) {
            Log::error('Failed to batch update analisa desain', [
                'aplikasi_id' => $aplikasiId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal mengupdate data', null, 500);
        }
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<string, array{ui: array<int, string>, interop: array<int, string>, storage: array<int, string>, aktor: array<int, string>, transaksiCount: int}>
     */
    private function initializeSummaryByIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $out[(string) $id] = [
                'ui' => [],
                'interop' => [],
                'storage' => [],
                'aktor' => [],
                'transaksiCount' => 0,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{ui: array<int, string>, interop: array<int, string>, storage: array<int, string>, aktor: array<int, string>, transaksiCount: int}>  $summary
     */
    private function appendSummaryRow(array &$summary, object $row): void
    {
        $aplikasiId = (string) ($row->aplikasi_id ?? '');
        if (! isset($summary[$aplikasiId])) {
            return;
        }

        $this->appendUniqueString($summary[$aplikasiId]['ui'], (string) ($row->ui_platform ?? ''));
        $this->appendUniqueString($summary[$aplikasiId]['interop'], (string) ($row->interop_type ?? ''));
        $this->appendUniqueString($summary[$aplikasiId]['storage'], (string) ($row->storage_type ?? ''));
        $this->appendUniqueString($summary[$aplikasiId]['aktor'], (string) ($row->nama_aktor ?? ''));

        if (($row->method ?? null) && ($row->url ?? null)) {
            $summary[$aplikasiId]['transaksiCount']++;
        }
    }

    /**
     * @param  array<int, string>  $bucket
     */
    private function appendUniqueString(array &$bucket, string $value): void
    {
        $clean = trim($value);
        if ($clean === '' || in_array($clean, $bucket, true)) {
            return;
        }

        $bucket[] = $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputItems
     * @return array<int, array<string, mixed>>
     */
    private function buildBatchRows(array $inputItems, string $aplikasiId, ?int $userId = null): array
    {
        $rows = [];
        foreach ($inputItems as $item) {
            if (! $this->hasAnyValue($item)) {
                continue;
            }

            $rows[] = $this->normalizeBatchRow($item, $aplikasiId, $userId);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function hasAnyValue(array $item): bool
    {
        foreach ($item as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeBatchRow(array $item, string $aplikasiId, ?int $userId = null): array
    {
        $now = now();

        return [
            'aplikasi_id' => $aplikasiId,
            'ui_platform' => $item['ui_platform'] ?? null,
            'interop_type' => $item['interop_type'] ?? null,
            'storage_type' => $item['storage_type'] ?? null,
            'nama_aktor' => $item['nama_aktor'] ?? null,
            'method' => $item['method'] ?? null,
            'url' => $item['url'] ?? null,
            'tipe_resource' => $item['tipe_resource'] ?? null,
            'aktor_transaksi' => $item['aktor_transaksi'] ?? null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
