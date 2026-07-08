<?php

namespace App\Http\Controllers;

use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreAplikasiChecklistRequest;
use App\Http\Requests\StoreAplikasiNoteRequest;
use App\Http\Requests\UpdateAplikasiChecklistRequest;
use App\Http\Requests\UpdateAplikasiNoteRequest;
use App\Models\Aplikasi;
use App\Models\AplikasiChecklist;
use App\Models\AplikasiNote;
use App\Models\User;
use App\Support\AplikasiAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Traits\HandlesAplikasiTransitions;

class AplikasiWorkflowController extends Controller
{
    use HandlesAplikasiTransitions;

    private const ACCESS_DENIED_MESSAGE = 'Akses ditolak.';

    private const IMPLEMENTATION_CATEGORY_BY_ROLE = [
        'tim_implementasi_aplikasi' => 'implementation_progress',
        'devops_developer' => 'devops_progress',
    ];

    private const IMPLEMENTATION_DEFAULT_ITEMS = [
        'implementation_progress' => [
            'Implementasi UI sesuai laporan analisa desain',
            'Integrasi endpoint API ke antarmuka',
            'Uji responsif dan aksesibilitas antarmuka',
            'Implementasi endpoint transaksi sesuai analisa',
            'Integrasi storage/database dan validasi data',
            'Uji error handling, logging, dan keamanan dasar',
            'Unggah template UAT dan petunjuk aplikasi ke sistem',
        ],
        'devops_progress' => [
            'Siapkan environment staging dan konfigurasi rahasia',
            'Konfigurasi pipeline build/deploy',
            'Siapkan monitoring, rollback, dan checklist release',
        ],
    ];

    public function index(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        if (! $this->canAccessWorkflowAplikasi($aplikasi, $request->user())) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }

        return ApiResponse::success([
            'checklists' => $aplikasi->checklists()
                ->with(['creator:id,name', 'updater:id,name'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'notes' => $aplikasi->notes()
                ->with(['creator:id,name', 'checker:id,name'])
                ->latest('id')
                ->get(),
        ]);
    }

    public function storeChecklist(StoreAplikasiChecklistRequest $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        $userId = $user instanceof User ? (int) $user->getKey() : null;
        $item = $aplikasi->checklists()->create([
            'category' => $request->input('category', 'studi_kelayakan'),
            'title' => $request->input('title'),
            'item_status' => $request->input('item_status', 'pending'),
            'notes' => $request->input('notes'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $item->load(['creator:id,name', 'updater:id,name']);

        return ApiResponse::created(['checklist' => $item], 'Checklist berhasil ditambahkan');
    }

    public function updateChecklist(UpdateAplikasiChecklistRequest $request, Aplikasi $aplikasi, AplikasiChecklist $checklist): JsonResponse
    {
        if ((int) $checklist->getAttribute('aplikasi_id') !== (int) $aplikasi->getKey()) {
            return ApiResponse::notFound('Checklist tidak ditemukan');
        }

        $payload = $request->validated();
        $user = $request->user();
        $payload['updated_by'] = $user instanceof User ? (int) $user->getKey() : null;
        $checklist->fill($payload)->save();
        $checklist->load(['creator:id,name', 'updater:id,name']);

        return ApiResponse::success(['checklist' => $checklist], 'Checklist berhasil diperbarui');
    }
    public function destroyChecklist(Request $request, Aplikasi $aplikasi, AplikasiChecklist $checklist): JsonResponse
    {
        if ((int) $checklist->getAttribute('aplikasi_id') !== (int) $aplikasi->getKey()) {
            return ApiResponse::notFound('Checklist tidak ditemukan');
        }

        $checklist->delete();

        return ApiResponse::success(null, 'Checklist berhasil dihapus');
    }

    public function storeNote(StoreAplikasiNoteRequest $request, Aplikasi $aplikasi): JsonResponse
    {
        if (! $this->canAccessWorkflowAplikasi($aplikasi, $request->user())) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }

        $user = $request->user();
        $userId = $user instanceof User ? (int) $user->getKey() : null;
        $note = $aplikasi->notes()->create([
            'note_type' => $request->input('note_type', 'perbaikan'),
            'body' => $request->input('body'),
            'created_by' => $userId,
        ]);

        $note->load(['creator:id,name', 'checker:id,name']);

        return ApiResponse::created(['note' => $note], 'Catatan berhasil ditambahkan');
    }

    public function updateNote(UpdateAplikasiNoteRequest $request, Aplikasi $aplikasi, AplikasiNote $note): JsonResponse
    {
        if (! $this->canAccessWorkflowAplikasi($aplikasi, $request->user())) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }

        if ((int) $note->getAttribute('aplikasi_id') !== (int) $aplikasi->getKey()) {
            return ApiResponse::notFound('Catatan tidak ditemukan');
        }

        $payload = $request->validated();
        $user = $request->user();
        $userId = $user instanceof User ? (int) $user->getKey() : null;

        if (array_key_exists('is_checked', $payload)) {
            if ((bool) $payload['is_checked']) {
                $payload['checked_by'] = $userId;
                $payload['checked_at'] = now();
            } else {
                $payload['checked_by'] = null;
                $payload['checked_at'] = null;
            }
        }

        $note->fill($payload)->save();
        $note->load(['creator:id,name', 'checker:id,name']);

        return ApiResponse::success(['note' => $note], 'Catatan berhasil diperbarui');
    }

    public function destroyNote(Request $request, Aplikasi $aplikasi, AplikasiNote $note): JsonResponse
    {
        if (! $this->canAccessWorkflowAplikasi($aplikasi, $request->user())) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }

        if ((int) $note->getAttribute('aplikasi_id') !== (int) $aplikasi->getKey()) {
            return ApiResponse::notFound('Catatan tidak ditemukan');
        }

        $note->delete();

        return ApiResponse::success(null, 'Catatan berhasil dihapus');
    }

    public function implementationIndex(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        $category = $this->implementationCategoryForUser($user);
        if ($category === null) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }
        if (! $this->implementationChecklistAllowedForStatus($category, (string) $aplikasi->getAttribute('status'))) {
            return ApiResponse::error('Checklist implementasi belum tersedia pada tahap aplikasi saat ini.', null, 422);
        }

        $this->ensureImplementationChecklistSeed($aplikasi, $category, $user);

        $checklists = $aplikasi->checklists()
            ->where('category', $category)
            ->with(['creator:id,name', 'updater:id,name'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success([
            'category' => $category,
            'checklists' => $checklists,
        ]);
    }

    public function implementationStore(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        $category = $this->implementationCategoryForUser($user);
        if ($category === null) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }
        if (! $this->implementationChecklistAllowedForStatus($category, (string) $aplikasi->getAttribute('status'))) {
            return ApiResponse::error('Checklist implementasi belum dapat diubah pada tahap aplikasi saat ini.', null, 422);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'item_status' => ['nullable', 'in:pending,in_progress,done'],
        ]);

        $nextOrder = (int) $aplikasi->checklists()
            ->where('category', $category)
            ->max('sort_order');

        $item = $aplikasi->checklists()->create([
            'category' => $category,
            'title' => $payload['title'],
            'notes' => $payload['notes'] ?? null,
            'item_status' => $payload['item_status'] ?? 'pending',
            'sort_order' => $nextOrder + 1,
            'created_by' => $user?->getKey(),
            'updated_by' => $user?->getKey(),
        ]);

        $item->load(['creator:id,name', 'updater:id,name']);

        return ApiResponse::created(['checklist' => $item], 'Item progress berhasil ditambahkan');
    }

    public function implementationUpdate(Request $request, Aplikasi $aplikasi, AplikasiChecklist $checklist): JsonResponse
    {
        if ((int) $checklist->getAttribute('aplikasi_id') !== (int) $aplikasi->getKey()) {
            return ApiResponse::notFound('Checklist tidak ditemukan');
        }

        $user = $request->user();
        $category = $this->implementationCategoryForUser($user);
        if ($category === null || (string) $checklist->getAttribute('category') !== $category) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }
        if (! $this->implementationChecklistAllowedForStatus($category, (string) $aplikasi->getAttribute('status'))) {
            return ApiResponse::error('Checklist implementasi belum dapat diubah pada tahap aplikasi saat ini.', null, 422);
        }

        $payload = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'item_status' => ['sometimes', 'required', 'in:pending,in_progress,done'],
        ]);

        $payload['updated_by'] = $user?->getKey();
        $checklist->fill($payload)->save();
        $checklist->load(['creator:id,name', 'updater:id,name']);

        return ApiResponse::success(['checklist' => $checklist], 'Progress berhasil diperbarui');
    }

    public function securityReviewShow(Aplikasi $aplikasi): JsonResponse
    {
        $aplikasi->loadMissing(['securityTester:id,name']);

        $securityNotes = $aplikasi->notes()
            ->with('creator:id,name')
            ->where('note_type', 'uji_keamanan')
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success([
            'review' => [
                'security_test_passed' => $aplikasi->getAttribute('security_test_passed'),
                'security_test_notes' => $aplikasi->getAttribute('security_test_notes'),
                'security_tested_at' => $aplikasi->getAttribute('security_tested_at')?->toIso8601String(),
                'security_tester' => $aplikasi->getRelationValue('securityTester'),
            ],
            'security_notes' => $securityNotes,
        ]);
    }

    public function securityReviewUpdate(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        if (! in_array($aplikasi->getAttribute('status'), [
            Aplikasi::STATUS_UJI_KEAMANAN,
            Aplikasi::STATUS_PERBAIKAN_KEAMANAN,
        ], true)) {
            return ApiResponse::error('Hasil uji keamanan hanya dapat diisi pada tahap uji keamanan.', null, 422);
        }

        $payload = $request->validate([
            'security_test_passed' => ['required', 'boolean'],
            'security_test_notes' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $userId = $user?->getKey();

        $aplikasi->setAttribute('security_test_passed', (bool) $payload['security_test_passed']);
        $aplikasi->setAttribute('security_test_notes', $payload['security_test_notes'] ?? null);
        $aplikasi->setAttribute('security_tested_by', $userId);
        $aplikasi->setAttribute('security_tested_at', now());
        $aplikasi->save();

        $historyText = '';
        if (!empty($payload['security_test_notes'])) {
            $historyText .= "Ringkasan Hasil Uji:\n" . trim((string) $payload['security_test_notes']) . "\n\n";
        }
        
        $noteBody = isset($payload['note']) ? trim((string) $payload['note']) : '';
        if ($noteBody !== '') {
            $historyText .= "Catatan Perbaikan:\n" . $noteBody;
        }

        $historyText = trim($historyText);
        if ($historyText !== '') {
            $aplikasi->notes()->create([
                'note_type' => 'uji_keamanan',
                'body' => $historyText,
                'created_by' => $userId,
            ]);
        }

        return $this->securityReviewShow($aplikasi->fresh());
    }

    private function implementationCategoryForUser(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $role = (string) $user->getAttribute('role');

        return self::IMPLEMENTATION_CATEGORY_BY_ROLE[$role] ?? null;
    }

    private function ensureImplementationChecklistSeed(Aplikasi $aplikasi, string $category, User $user): void
    {
        if ($category === 'implementation_progress') {
            $legacyCount = $aplikasi->checklists()
                ->whereIn('category', ['frontend_progress', 'backend_progress'])
                ->count();
            if ($legacyCount > 0) {
                $aplikasi->checklists()
                    ->whereIn('category', ['frontend_progress', 'backend_progress'])
                    ->update(['category' => 'implementation_progress']);
            }
        }

        $existingCount = $aplikasi->checklists()->where('category', $category)->count();
        if ($existingCount > 0) {
            return;
        }

        $defaults = self::IMPLEMENTATION_DEFAULT_ITEMS[$category] ?? [];
        foreach ($defaults as $index => $title) {
            $aplikasi->checklists()->create([
                'category' => $category,
                'title' => $title,
                'item_status' => 'pending',
                'notes' => null,
                'sort_order' => $index + 1,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
        }
    }

    protected function canAccessWorkflowAplikasi(Aplikasi $aplikasi, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isUnitKerja()) {
            return AplikasiAccess::canUnitKerjaAccess($user, $aplikasi);
        }

        return $user->isPengelolaAplikasi()
            || $user->isAnalisDesain()
            || $user->isTimImplementasiAplikasi()
            || $user->isDevops()
            || $user->isTimUjiKeamanan();
    }

    private function implementationChecklistAllowedForStatus(string $category, string $status): bool
    {
        $allowedStatuses = match ($category) {
            'implementation_progress' => [
                Aplikasi::STATUS_PENGEMBANGAN,
                Aplikasi::STATUS_PERBAIKAN_UAT,
                Aplikasi::STATUS_PERBAIKAN_KEAMANAN,
            ],
            'devops_progress' => [
                Aplikasi::STATUS_SIAP_DEPLOY,
                Aplikasi::STATUS_DEPLOYED_STAGING,
                Aplikasi::STATUS_DEPLOYED_PRODUCTION,
            ],
            default => [],
        };

        return in_array($status, $allowedStatuses, true);
    }

    /**
     * GET /api/aplikasi/{aplikasi}/deployment-status
     * Tampilkan status deployment staging & production.
     */
    public function deploymentShow(Aplikasi $aplikasi): JsonResponse
    {
        $aplikasi->load(['stagingDeployer:id,name', 'productionDeployer:id,name']);

        return ApiResponse::success([
            'deployment' => [
                'staging' => [
                    'deployed'    => $aplikasi->deployed_staging_at !== null,
                    'deployed_at' => $aplikasi->deployed_staging_at,
                    'deployed_by' => $aplikasi->stagingDeployer,
                ],
                'production' => [
                    'deployed'    => $aplikasi->deployed_production_at !== null,
                    'deployed_at' => $aplikasi->deployed_production_at,
                    'deployed_by' => $aplikasi->productionDeployer,
                ],
                'notes' => $aplikasi->deployment_notes,
            ],
        ]);
    }

    /**
     * PUT /api/aplikasi/{aplikasi}/deployment-status
     * DevOps mengonfirmasi deployment ke staging atau production.
     */
    public function deploymentUpdate(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $request->validate([
            'environment' => 'required|in:staging,production',
            'deployed'    => 'required|boolean',
            'notes'       => 'nullable|string|max:500',
        ]);

        $userId = $request->user()?->getKey();
        $user = $request->user();
        $environment = $request->input('environment');
        $deployed = $request->boolean('deployed');
        $statusTarget = null;
        $historyAction = null;

        if ($environment === 'staging') {
            if ($deployed && ! in_array($aplikasi->getAttribute('status'), [
                Aplikasi::STATUS_SIAP_DEPLOY,
                Aplikasi::STATUS_DEPLOYED_STAGING,
            ], true)) {
                return ApiResponse::error(
                    'Deployment staging hanya dapat dilakukan saat aplikasi berstatus siap deploy.',
                    null,
                    422
                );
            }

            $aplikasi->deployed_staging_at = $deployed ? now() : null;
            $aplikasi->deployed_staging_by = $deployed ? $userId : null;

            if (
                $deployed
                && $aplikasi->status !== Aplikasi::STATUS_DEPLOYED_STAGING
                && $aplikasi->status !== Aplikasi::STATUS_DEPLOYED_PRODUCTION
            ) {
                $statusTarget = Aplikasi::STATUS_DEPLOYED_STAGING;
                $historyAction = 'Deployment ke Staging';
            }
        } else {
            if ($deployed && ! in_array($aplikasi->getAttribute('status'), [
                Aplikasi::STATUS_DEPLOYED_STAGING,
                Aplikasi::STATUS_DEPLOYED_PRODUCTION,
            ], true)) {
                return ApiResponse::error(
                    'Deployment production hanya dapat dilakukan setelah aplikasi berstatus deployed staging.',
                    null,
                    422
                );
            }

            if ($deployed && $aplikasi->deployed_staging_at === null) {
                return ApiResponse::error(
                    'Deployment production hanya dapat dilakukan setelah staging selesai.',
                    null,
                    422
                );
            }

            $aplikasi->deployed_production_at = $deployed ? now() : null;
            $aplikasi->deployed_production_by = $deployed ? $userId : null;

            if ($deployed && $aplikasi->status !== Aplikasi::STATUS_DEPLOYED_PRODUCTION) {
                $statusTarget = Aplikasi::STATUS_DEPLOYED_PRODUCTION;
                $historyAction = 'Deployment ke Production';
            }
        }

        if ($request->filled('notes')) {
            $aplikasi->deployment_notes = $request->input('notes');
        }

        if ($statusTarget !== null) {
            $this->recordStatusHistory(
                $aplikasi,
                $historyAction,
                $statusTarget,
                $request->input('notes') ?: null,
                $user
            );
        } else {
            $aplikasi->save();
        }

        \Illuminate\Support\Facades\Log::info('Deployment status updated', [
            'aplikasi_id' => $aplikasi->getKey(),
            'environment' => $environment,
            'deployed'    => $deployed,
            'user_id'     => $userId,
        ]);

        return $this->deploymentShow($aplikasi->fresh());
    }
}
