<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Helpers\ApiResponse;
use App\Http\Helpers\QueryHelper;
use App\Http\Requests\StoreRfcRequest;
use App\Http\Requests\UpdateRfcRequest;
use App\Models\Aplikasi;
use App\Models\AppNotification;
use App\Models\Rfc;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RfcController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'aplikasi_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $query = Rfc::with(['aplikasi:id,nama_aplikasi', 'creator:id,name', 'updater:id,name']);
        $this->scopeVisibleToUser($query, $request->user());

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $escaped = QueryHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('deskripsi', 'like', "%{$escaped}%")
                    ->orWhere('tipe_rfc', 'like', "%{$escaped}%");
            });
        }

        if ($aplikasiId = ($validated['aplikasi_id'] ?? null)) {
            $query->where('aplikasi_id', $aplikasiId);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);

        return ApiResponse::paginated($query->orderByDesc('id')->paginate($perPage));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $query = Rfc::with(['aplikasi:id,nama_aplikasi', 'creator:id,name', 'updater:id,name']);
        $this->scopeVisibleToUser($query, $request->user());

        $rfc = $query->findOrFail($id);

        return ApiResponse::success($rfc);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Rfc::query();
        $this->scopeVisibleToUser($query, $request->user());

        $counts = (clone $query)
            ->select('status_tindaklanjut', DB::raw('COUNT(*) as total'))
            ->groupBy('status_tindaklanjut')
            ->pluck('total', 'status_tindaklanjut');

        $diproses = collect([
            Rfc::STATUS_ANALISA_DESAIN,
            Rfc::STATUS_DEV_STAGING,
            Rfc::STATUS_UAT,
        ])->sum(fn (string $status): int => (int) ($counts[$status] ?? 0));

        return ApiResponse::success([
            'total' => (clone $query)->count(),
            'diajukan' => (int) ($counts[Rfc::STATUS_DIAJUKAN] ?? 0),
            'diproses' => $diproses,
            'production' => (int) ($counts[Rfc::STATUS_PRODUCTION] ?? 0),
        ]);
    }

    public function store(StoreRfcRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $request->user();
            $file = $request->file('formulir_rfc');
            unset($data['formulir_rfc']);

            $aplikasi = Aplikasi::select('id', 'nama_aplikasi', 'status', 'created_by')
                ->findOrFail($data['aplikasi_id']);

            if ($user?->isUnitKerja()) {
                if ((int) $aplikasi->created_by !== (int) $user->getKey()) {
                    return ApiResponse::forbidden('Anda hanya dapat mengajukan RFC untuk aplikasi milik unit kerja Anda.');
                }

                if ($aplikasi->status !== Aplikasi::STATUS_DEPLOYED_PRODUCTION) {
                    return ApiResponse::error(
                        'RFC hanya dapat diajukan untuk aplikasi yang sudah deployed production.',
                        null,
                        422
                    );
                }

                $data['pelaksana'] = null;
                $data['status_tindaklanjut'] = Rfc::STATUS_DIAJUKAN;
            } else {
                $data['status_tindaklanjut'] ??= Rfc::STATUS_ANALISA_DESAIN;
            }

            if ($this->hasOpenRfcForAplikasi((int) $aplikasi->getKey())) {
                return ApiResponse::error(
                    'Aplikasi ini masih memiliki RFC yang belum selesai. Selesaikan RFC tersebut sebelum membuat RFC baru.',
                    null,
                    422
                );
            }

            $path = $file->store('rfc_documents', 'public');

            $rfc = DB::transaction(function () use ($data, $file, $path) {
                $data['formulir_path'] = $path;
                $data['formulir_original_filename'] = $file->getClientOriginalName();
                $data['formulir_mime_type'] = $file->getClientMimeType();
                $data['formulir_file_size'] = $file->getSize();

                return Rfc::create($data);
            });

            if ($user?->isUnitKerja()) {
                $this->notifyPengelolaForUnitKerjaSubmission($rfc->load('aplikasi:id,nama_aplikasi'));
            }

            Log::info('RFC created', [
                'rfc_id' => $rfc->getKey(),
                'user_id' => $request->user()?->getKey(),
            ]);

            return ApiResponse::created($rfc->load(['aplikasi:id,nama_aplikasi']), 'RFC berhasil dibuat');
        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Failed to create RFC', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey(),
            ]);

            return ApiResponse::error('Gagal membuat RFC.', null, 500);
        }
    }

    public function update(UpdateRfcRequest $request, string $id): JsonResponse
    {
        $newPath = null;
        $oldPath = null;

        try {
            $payload = $request->validated();
            unset($payload['formulir_rfc']);

            $file = $request->file('formulir_rfc');

            if (array_key_exists('aplikasi_id', $payload)) {
                $currentRfc = Rfc::select('id', 'aplikasi_id')->findOrFail($id);
                $targetAplikasiId = (int) $payload['aplikasi_id'];

                if (
                    $targetAplikasiId !== (int) $currentRfc->aplikasi_id
                    && $this->hasOpenRfcForAplikasi($targetAplikasiId, (int) $currentRfc->getKey())
                ) {
                    return ApiResponse::error(
                        'Aplikasi ini masih memiliki RFC yang belum selesai. Selesaikan RFC tersebut sebelum memindahkan RFC lain ke aplikasi ini.',
                        null,
                        422
                    );
                }
            }

            if ($file) {
                $newPath = $file->store('rfc_documents', 'public');
            }

            $rfc = DB::transaction(function () use ($id, $payload, $file, &$oldPath, $newPath) {
                $rfc = Rfc::findOrFail($id);

                if ($file && $newPath) {
                    $oldPath = $rfc->getAttribute('formulir_path');
                    $payload['formulir_path'] = $newPath;
                    $payload['formulir_original_filename'] = $file->getClientOriginalName();
                    $payload['formulir_mime_type'] = $file->getClientMimeType();
                    $payload['formulir_file_size'] = $file->getSize();
                }

                $rfc->update($payload);

                return $rfc;
            });

            if ($oldPath) {
                Storage::disk('public')->delete((string) $oldPath);
            }

            Log::info('RFC updated', [
                'rfc_id' => $rfc->getKey(),
                'changes' => array_keys($payload),
                'user_id' => $request->user()?->getKey(),
            ]);

            return ApiResponse::success($rfc->load(['aplikasi:id,nama_aplikasi']), 'RFC berhasil diperbarui');
        } catch (\Exception $e) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            Log::error('Failed to update RFC', [
                'rfc_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey(),
            ]);

            return ApiResponse::error('Gagal memperbarui RFC.', null, 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $path = DB::transaction(function () use ($request, $id) {
                $rfc = Rfc::withTrashed()->findOrFail($id);
                $path = $rfc->getAttribute('formulir_path');

                Log::warning('RFC permanently deleted', [
                    'rfc_id' => $rfc->getKey(),
                    'user_id' => $request->user()?->getKey(),
                ]);

                $rfc->forceDelete();

                return $path;
            });

            if ($path) {
                Storage::disk('public')->delete((string) $path);
            }

            return ApiResponse::success(null, 'RFC berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Failed to delete RFC', [
                'rfc_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->getKey(),
            ]);

            return ApiResponse::error('Gagal menghapus RFC.', null, 500);
        }
    }

    private function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if ($user?->isUnitKerja()) {
            $query->whereHas('aplikasi', function (Builder $aplikasiQuery) use ($user) {
                $aplikasiQuery->where('created_by', $user->getKey());
            });
        }

        return $query;
    }

    private function hasOpenRfcForAplikasi(int $aplikasiId, ?int $exceptRfcId = null): bool
    {
        $query = Rfc::query()
            ->where('aplikasi_id', $aplikasiId)
            ->whereIn('status_tindaklanjut', Rfc::OPEN_STATUS_VALUES);

        if ($exceptRfcId !== null) {
            $query->where('id', '!=', $exceptRfcId);
        }

        return $query->exists();
    }

    private function notifyPengelolaForUnitKerjaSubmission(Rfc $rfc): void
    {
        $appName = $rfc->aplikasi?->nama_aplikasi ?? 'aplikasi';

        User::where('role', UserRole::PENGELOLA_APLIKASI->value)
            ->get()
            ->each(function (User $pengelola) use ($rfc, $appName) {
                AppNotification::create([
                    'user_id' => $pengelola->getKey(),
                    'aplikasi_id' => $rfc->aplikasi_id,
                    'type' => 'action_required',
                    'title' => 'Pengajuan RFC Baru',
                    'body' => sprintf('RFC untuk aplikasi "%s" diajukan oleh Unit Kerja dan menunggu tindak lanjut.', $appName),
                ]);
            });
    }
}
