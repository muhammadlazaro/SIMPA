<?php

namespace App\Http\Controllers;

use App\Enums\AplikasiStatus;
use App\Http\Requests\StoreAplikasiRequest;
use App\Http\Requests\UpdateAplikasiRequest;
use App\Http\Helpers\ApiResponse;
use App\Http\Helpers\QueryHelper;
use App\Models\Aplikasi;
use App\Models\Rfc;
use App\Services\AutoGenerationService;
use App\Support\AplikasiAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AplikasiController extends Controller
{
    public function __construct(
        protected AutoGenerationService $autoGenerationService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                $allowedStatuses = AplikasiStatus::allValues();
                $statuses = array_filter(array_map('trim', explode(',', (string) $value)));

                if ($statuses === [] || count(array_diff($statuses, $allowedStatuses)) > 0) {
                    $fail('Status aplikasi tidak valid.');
                }
            }],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Aplikasi::query();
        $user = $request->user();

        // Eager load creator and updater to avoid N+1 queries
        $query->with(['creator:id,name', 'updater:id,name']);

        // Unit kerja melihat pengajuan miliknya sendiri, plus aplikasi UAT lama
        // yang dibuat non-Unit Kerja dan perlu ditindaklanjuti oleh role Unit Kerja.
        if ($user && $user->isUnitKerja()) {
            AplikasiAccess::scopeVisibleToUnitKerja($query, $user);
        }

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $escaped = QueryHelper::escapeLike($search);
            $query->where(function($q) use ($escaped) {
                $q->where('nama_layanan', 'like', "%{$escaped}%")
                  ->orWhere('nama_singkat', 'like', "%{$escaped}%")
                  ->orWhere('nama_aplikasi', 'like', "%{$escaped}%")
                  ->orWhere('kode_unitOrganisasi', 'like', "%{$escaped}%")
                  ->orWhere('tipe_akuisisi', 'like', "%{$escaped}%")
                  ->orWhere('status', 'like', "%{$escaped}%");
            });
        }

        $status = (string) ($validated['status'] ?? '');
        if ($status !== '') {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $status))));
            count($statuses) > 1
                ? $query->whereIn('status', $statuses)
                : $query->where('status', $statuses[0]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $items = $query->orderByDesc('id')->paginate($perPage);
        return ApiResponse::paginated($items);
    }

    /**
     * Global status statistics for dashboard cards.
     */
    public function stats(): JsonResponse
    {
        $devStatuses = AplikasiStatus::developmentValues();
        $opStatuses = AplikasiStatus::operationalValues();
        $inactiveStatuses = AplikasiStatus::inactiveValues();
        $stoppedStatuses = AplikasiStatus::stoppedValues();

        $devPlaceholders = implode(',', array_fill(0, count($devStatuses), '?'));
        $opPlaceholders = implode(',', array_fill(0, count($opStatuses), '?'));
        $inactivePlaceholders = implode(',', array_fill(0, count($inactiveStatuses), '?'));
        $stoppedPlaceholders = implode(',', array_fill(0, count($stoppedStatuses), '?'));

        $stats = Cache::remember('aplikasi:stats:v1', now()->addMinutes(5), function () use (
            $devStatuses, $opStatuses, $inactiveStatuses, $stoppedStatuses,
            $devPlaceholders, $opPlaceholders, $inactivePlaceholders, $stoppedPlaceholders
        ) {
            $row = Aplikasi::query()
                ->selectRaw("
                    SUM(CASE WHEN status IN ({$devPlaceholders}) THEN 1 ELSE 0 END) AS development,
                    SUM(CASE WHEN status IN ({$opPlaceholders}) THEN 1 ELSE 0 END) AS operational,
                    SUM(CASE WHEN status IN ({$inactivePlaceholders}) THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN status IN ({$stoppedPlaceholders}) THEN 1 ELSE 0 END) AS stopped
                ", [...$devStatuses, ...$opStatuses, ...$inactiveStatuses, ...$stoppedStatuses])
                ->first();

            return [
                'development' => (int) ($row->development ?? 0),
                'operational' => (int) ($row->operational ?? 0),
                'inactive' => (int) ($row->inactive ?? 0),
                'stopped' => (int) ($row->stopped ?? 0),
            ];
        });

        return ApiResponse::success($stats);
    }

    /**
     * Ringkasan pengajuan baru dari unit kerja (untuk notifikasi pengelola).
     * Hanya aplikasi berstatus Pengajuan yang dibuat oleh pengguna berperan unit_kerja.
     */
    public function pengelolaNotifications(Request $request): JsonResponse
    {
        $query = Aplikasi::query()
            ->where('status', Aplikasi::STATUS_DIAJUKAN)
            ->whereHas('creator', function ($q) {
                $q->where('role', 'unit_kerja');
            })
            ->with(['creator:id,name,email,role'])
            ->orderByDesc('id');

        $count = (clone $query)->count();
        $recent = $query->limit(10)->get();

        $items = $recent->map(function (Aplikasi $a) {
            $creator = $a->getRelationValue('creator');

            return [
                'id' => $a->getKey(),
                'nama_layanan' => $a->getAttribute('nama_layanan'),
                'nama_singkat' => $a->getAttribute('nama_singkat'),
                'nama_aplikasi' => $a->getAttribute('nama_aplikasi'),
                'kode_unitOrganisasi' => $a->getAttribute('kode_unitOrganisasi'),
                'status' => $a->getAttribute('status'),
                'created_at' => $a->getAttribute('created_at')?->toIso8601String(),
                'creator' => $creator ? [
                    'id' => $creator->getKey(),
                    'name' => $creator->getAttribute('name'),
                    'email' => $creator->getAttribute('email'),
                ] : null,
            ];
        })->values()->all();

        return ApiResponse::success([
            'count' => $count,
            'items' => $items,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAplikasiRequest $request)
    {
        try {
            $item = DB::transaction(function () use ($request) {
                $data = $request->validated();

                if ($request->hasFile('surat_pengajuan')) {
                    $path = $request->file('surat_pengajuan')->store('aplikasi_docs', 'public');
                    $data['doc_pengajuan_path'] = $path;
                }
                unset($data['surat_pengajuan']);
                $data['status'] = Aplikasi::STATUS_DIAJUKAN;

                $item = Aplikasi::create($data);

                Log::info('Aplikasi created', [
                    'aplikasi_id' => $item->getKey(),
                    'nama_aplikasi' => $item->getAttribute('nama_aplikasi'),
                    'user_id' => $request->user()?->getKey(),
                    'user_email' => $request->user()?->getAttribute('email')
                ]);

                return $item;
            });

            $this->forgetStatsCache();

            return ApiResponse::created(['aplikasi' => $item], 'Data berhasil disimpan');
        } catch (\Exception $e) {
            Log::error('Failed to create aplikasi', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey()
            ]);
            return ApiResponse::error('Gagal menyimpan data', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $query = Aplikasi::with([
            'proyeks',
            'databaseConfigs',
            'objectStorageConfigs',
            'apiGatewayConfigs',
            'environmentConfigs',
            'devopsConfigs',
            'frontendConfigs',
            'backendConfigs',
            'analisaDesains',
            'checklists.creator:id,name',
            'checklists.updater:id,name',
            'notes.creator:id,name',
            'notes.checker:id,name',
            'creator:id,name',
            'updater:id,name',
            'securityTester:id,name'
        ]);

        $user = $request->user();
        if ($user && $user->isUnitKerja()) {
            AplikasiAccess::scopeVisibleToUnitKerja($query, $user);
        }

        $item = $query->findOrFail($id);
        return ApiResponse::success($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAplikasiRequest $request, string $id)
    {
        try {
            $item = DB::transaction(function () use ($request, $id) {
                $item = Aplikasi::findOrFail($id);

                $payload = $request->validated();
                $oldJenisLayanan = $item->getAttribute('jenis_layanan_aplikasi');
                $newJenisLayanan = $payload['jenis_layanan_aplikasi'] ?? $oldJenisLayanan;

                if ($request->hasFile('surat_dokumen')) {
                    $path = $request->file('surat_dokumen')->store('aplikasi_docs', 'public');
                    $item->setAttribute('doc_studi_kelayakan_path', $path);
                }

                $item->fill(collect($payload)->except(['surat_dokumen'])->all());

                $item->save();

                Log::info('Aplikasi updated', [
                    'aplikasi_id' => $item->getKey(),
                    'nama_aplikasi' => $item->getAttribute('nama_aplikasi'),
                    'changes' => $request->validated(),
                    'user_id' => $request->user()?->getKey(),
                    'user_email' => $request->user()?->getAttribute('email')
                ]);

                if ($oldJenisLayanan !== $newJenisLayanan) {
                    $this->autoGenerationService->updateUIAndProyekOnly($item->getKey());
                } else {
                    $this->autoGenerationService->generateAllConfigurations($item->getKey());
                }

                return $item;
            });

            $this->forgetStatsCache();

            return ApiResponse::success(['aplikasi' => $item], 'Data berhasil diupdate');
        } catch (\Exception $e) {
            Log::error('Failed to update aplikasi', [
                'aplikasi_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey()
            ]);
            return ApiResponse::error('Gagal mengupdate data', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $paths = DB::transaction(function () use ($request, $id) {
                $item = Aplikasi::with([
                    'documents:id,aplikasi_id,storage_path',
                ])->findOrFail($id);

                $rfcFormPaths = Rfc::withTrashed()
                    ->where('aplikasi_id', $item->getKey())
                    ->pluck('formulir_path');

                $paths = collect([
                    $item->getAttribute('doc_pengajuan_path'),
                    $item->getAttribute('doc_permohonan_path'),
                    $item->getAttribute('doc_studi_kelayakan_path'),
                ])
                    ->merge($item->documents->pluck('storage_path'))
                    ->merge($rfcFormPaths)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                Log::warning('Aplikasi permanently deleted', [
                    'aplikasi_id' => $item->getKey(),
                    'nama_aplikasi' => $item->getAttribute('nama_aplikasi'),
                    'user_id' => $request->user()?->getKey(),
                    'user_email' => $request->user()?->getAttribute('email')
                ]);

                $item->notifications()->delete();
                $item->forceDelete();

                return $paths;
            });

            if (! empty($paths)) {
                Storage::disk('public')->delete($paths);
            }

            $this->forgetStatsCache();

            return ApiResponse::success(null, 'Aplikasi dan seluruh data terkait berhasil dihapus permanen');
        } catch (\Exception $e) {
            Log::error('Failed to delete aplikasi', [
                'aplikasi_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey()
            ]);
            return ApiResponse::error('Gagal menghapus data aplikasi', null, 500);
        }
    }

    /**
     * Unit Kerja menarik pengajuan miliknya sendiri.
     * Hanya diizinkan selama status masih "Pengajuan" (belum diproses siapapun).
     */
    public function withdraw(Request $request, string $id)
    {
        $user = $request->user();

        try {
            return DB::transaction(function () use ($id, $user) {
                // Pastikan hanya milik user ini
                $item = Aplikasi::where('id', $id)
                    ->where('created_by', $user->getKey())
                    ->firstOrFail();

                // Blokir jika sudah diproses (status bukan Pengajuan)
                if ($item->getAttribute('status') !== Aplikasi::STATUS_DIAJUKAN) {
                    return ApiResponse::error(
                        'Pengajuan tidak dapat ditarik karena sudah mulai diproses oleh tim pengelola.',
                        422
                    );
                }

                Log::info('Aplikasi withdrawn by unit kerja', [
                    'aplikasi_id' => $item->getKey(),
                    'nama_aplikasi' => $item->getAttribute('nama_aplikasi'),
                    'user_id' => $user->getKey(),
                ]);

                $item->delete();
                $this->forgetStatsCache();

                return ApiResponse::success(null, 'Pengajuan berhasil ditarik.');
            });
        } catch (\Exception $e) {
            Log::error('Failed to withdraw aplikasi', [
                'aplikasi_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $user->getKey(),
            ]);
            return ApiResponse::error('Gagal menarik pengajuan.', null, 500);
        }
    }

    /**
     * Tandai aplikasi sebagai nonaktif tanpa menghapus data historisnya.
     */
    public function nonaktifkan(Request $request, string $id): JsonResponse
    {
        try {
            $item = DB::transaction(function () use ($request, $id) {
                $item = Aplikasi::findOrFail($id);
                $statusSebelumnya = (string) $item->getAttribute('status');

                if ($statusSebelumnya === Aplikasi::STATUS_NONAKTIF) {
                    return $item;
                }

                if ($statusSebelumnya !== Aplikasi::STATUS_DEPLOYED_PRODUCTION) {
                    throw new \RuntimeException('Aplikasi hanya dapat dinonaktifkan setelah berstatus deployed production.');
                }

                $catatan = trim((string) $request->input('catatan', 'Aplikasi ditandai nonaktif oleh pengelola aplikasi.'));

                $item->setAttribute('status', Aplikasi::STATUS_NONAKTIF);
                $item->save();

                $item->statusHistories()->create([
                    'status_sebelumnya' => $statusSebelumnya,
                    'status_baru' => Aplikasi::STATUS_NONAKTIF,
                    'aksi' => 'Nonaktifkan Aplikasi',
                    'catatan' => $catatan !== '' ? $catatan : null,
                    'changed_by' => $request->user()?->getKey(),
                ]);

                Log::info('Aplikasi deactivated', [
                    'aplikasi_id' => $item->getKey(),
                    'nama_aplikasi' => $item->getAttribute('nama_aplikasi'),
                    'status_sebelumnya' => $statusSebelumnya,
                    'user_id' => $request->user()?->getKey(),
                    'user_email' => $request->user()?->getAttribute('email'),
                ]);

                return $item;
            });

            $this->forgetStatsCache();

            return ApiResponse::success(['aplikasi' => $item->fresh()], 'Aplikasi berhasil dinonaktifkan');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            Log::error('Failed to deactivate aplikasi', [
                'aplikasi_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey(),
            ]);
            return ApiResponse::error('Gagal menonaktifkan aplikasi', null, 500);
        }
    }

    /**
     * Restore a soft-deleted aplikasi
     */
    public function restore(Request $request, string $id)
    {
        try {
            $item = Aplikasi::onlyTrashed()->findOrFail($id);
            $item->restore();
            $this->forgetStatsCache();
            
            Log::info('Aplikasi restored', [
                'aplikasi_id' => $item->getKey(),
                'nama_aplikasi' => $item->getAttribute('nama_aplikasi'),
                'user_id' => $request->user()?->getKey()
            ]);
            
            return ApiResponse::success($item, 'Data berhasil dipulihkan');
        } catch (\Exception $e) {
            Log::error('Failed to restore aplikasi', [
                'aplikasi_id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Gagal memulihkan data', null, 500);
        }
    }

    /**
     * Get list of soft-deleted aplikasi
     */
    public function trashed(Request $request)
    {
        $query = Aplikasi::onlyTrashed();

        if ($search = $request->get('q')) {
            $escaped = QueryHelper::escapeLike($search);
            $query->where(function($q) use ($escaped) {
                $q->where('nama_layanan', 'like', "%{$escaped}%")
                  ->orWhere('nama_singkat', 'like', "%{$escaped}%")
                  ->orWhere('nama_aplikasi', 'like', "%{$escaped}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $items = $query->orderByDesc('deleted_at')->paginate($perPage);
        return ApiResponse::paginated($items);
    }

    private function forgetStatsCache(): void
    {
        Cache::forget('aplikasi:stats:v1');
    }
}
