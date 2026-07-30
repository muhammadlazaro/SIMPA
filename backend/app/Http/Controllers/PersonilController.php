<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Helpers\ApiResponse;
use App\Http\Helpers\QueryHelper;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class PersonilController extends Controller
{
    private const LAST_ACTIVE_ADMIN_MESSAGE = 'Minimal harus ada satu Admin Sistem aktif';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'all';
        $query = User::query();

        if ($status === 'all') {
            $query->withTrashed();
        } elseif ($status === 'inactive') {
            $query->onlyTrashed();
        }

        $role = $validated['role'] ?? null;
        if ($role && $role !== 'all') {
            $query->where('role', $role);
        }

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $safeSearch = '%'.QueryHelper::escapeLike($search).'%';
            $query->where(function ($subQuery) use ($safeSearch) {
                $subQuery
                    ->where('name', 'like', $safeSearch)
                    ->orWhere('email', 'like', $safeSearch)
                    ->orWhere('role', 'like', $safeSearch);
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginator = $query
            ->orderByRaw('deleted_at IS NOT NULL')
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::paginated($paginator, 'Daftar personil berhasil dimuat');
    }

    public function stats(): JsonResponse
    {
        $active = User::query()->count();
        $inactive = User::onlyTrashed()->count();
        $roleCounts = User::query()
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        $roles = collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role): array => [$role->value => (int) ($roleCounts[$role->value] ?? 0)])
            ->all();

        return ApiResponse::success([
            'total' => $active + $inactive,
            'active' => $active,
            'inactive' => $inactive,
            'roles' => $roles,
        ], 'Statistik personil berhasil dimuat');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => [
                'required',
                'string',
                'max:128',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make((string) $validated['password']),
            'role' => $validated['role'],
        ]);

        Log::info('Personil created', [
            'personil_id' => $user->getKey(),
            'role' => $user->role,
            'created_by' => $request->user()?->getKey(),
            'ip' => $request->ip(),
        ]);

        return ApiResponse::created($this->serializeUser($user), 'Personil berhasil ditambahkan');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $personil = User::withTrashed()->find($id);
        if (! $personil) {
            return ApiResponse::notFound('Personil tidak ditemukan');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($personil->getKey()),
            ],
            'role' => ['required', new Enum(UserRole::class)],
            'password' => [
                'nullable',
                'string',
                'max:128',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $currentUser = $request->user();
        $newRole = (string) $validated['role'];
        $authorizationError = null;
        if ($currentUser && (int) $currentUser->getKey() === (int) $personil->getKey() && $newRole !== UserRole::ADMIN_SISTEM->value) {
            $authorizationError = ApiResponse::forbidden('Akun sendiri tidak boleh dipindahkan dari role Admin Sistem');
        } elseif ($this->wouldRemoveLastActiveAdminSistem($personil, $newRole)) {
            $authorizationError = ApiResponse::forbidden(self::LAST_ACTIVE_ADMIN_MESSAGE);
        }
        if ($authorizationError !== null) {
            return $authorizationError;
        }

        $personil->name = $validated['name'];
        $personil->email = $validated['email'];
        $personil->role = $newRole;

        if (! empty($validated['password'])) {
            $personil->password = Hash::make((string) $validated['password']);
            $personil->tokens()->delete();
        }

        $personil->save();

        Log::info('Personil updated', [
            'personil_id' => $personil->getKey(),
            'role' => $personil->role,
            'updated_by' => $currentUser?->getKey(),
            'ip' => $request->ip(),
        ]);

        return ApiResponse::success($this->serializeUser($personil), 'Personil berhasil diperbarui');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $personil = User::find($id);
        $currentUser = $request->user();
        $authorizationError = null;
        if (! $personil) {
            $authorizationError = ApiResponse::notFound('Personil tidak ditemukan atau sudah nonaktif');
        } elseif ($currentUser && (int) $currentUser->getKey() === (int) $personil->getKey()) {
            $authorizationError = ApiResponse::forbidden('Akun sendiri tidak boleh dinonaktifkan');
        } elseif ($this->wouldRemoveLastActiveAdminSistem($personil, null)) {
            $authorizationError = ApiResponse::forbidden(self::LAST_ACTIVE_ADMIN_MESSAGE);
        }
        if ($authorizationError !== null) {
            return $authorizationError;
        }

        $personil->tokens()->delete();
        $personil->delete();

        Log::info('Personil deactivated', [
            'personil_id' => $personil->getKey(),
            'deactivated_by' => $currentUser?->getKey(),
            'ip' => $request->ip(),
        ]);

        return ApiResponse::success($this->serializeUser($personil), 'Personil berhasil dinonaktifkan');
    }

    public function forceDestroy(Request $request, string $id): JsonResponse
    {
        $personil = User::withTrashed()->find($id);
        $currentUser = $request->user();
        $authorizationError = null;
        if (! $personil) {
            $authorizationError = ApiResponse::notFound('Personil tidak ditemukan');
        } elseif ($currentUser && (int) $currentUser->getKey() === (int) $personil->getKey()) {
            $authorizationError = ApiResponse::forbidden('Akun sendiri tidak boleh dihapus permanen');
        } elseif ($this->wouldRemoveLastActiveAdminSistem($personil, null)) {
            $authorizationError = ApiResponse::forbidden(self::LAST_ACTIVE_ADMIN_MESSAGE);
        }
        if ($authorizationError !== null) {
            return $authorizationError;
        }

        $personilId = $personil->getKey();
        $personilEmail = $personil->email;
        $personilRole = $personil->role;

        $personil->tokens()->delete();
        $personil->forceDelete();

        Log::warning('Personil permanently deleted', [
            'personil_id' => $personilId,
            'email' => $personilEmail,
            'role' => $personilRole,
            'deleted_by' => $currentUser?->getKey(),
            'ip' => $request->ip(),
        ]);

        return ApiResponse::success(null, 'Personil berhasil dihapus permanen');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $personil = User::onlyTrashed()->find($id);
        if (! $personil) {
            return ApiResponse::notFound('Personil nonaktif tidak ditemukan');
        }

        $personil->restore();

        Log::info('Personil restored', [
            'personil_id' => $personil->getKey(),
            'restored_by' => $request->user()?->getKey(),
            'ip' => $request->ip(),
        ]);

        return ApiResponse::success($this->serializeUser($personil), 'Personil berhasil diaktifkan kembali');
    }

    private function wouldRemoveLastActiveAdminSistem(User $personil, ?string $newRole): bool
    {
        if ($personil->trashed() || $personil->role !== UserRole::ADMIN_SISTEM->value) {
            return false;
        }

        if ($newRole === UserRole::ADMIN_SISTEM->value) {
            return false;
        }

        $activeAdminCount = User::query()
            ->where('role', UserRole::ADMIN_SISTEM->value)
            ->count();

        return $activeAdminCount <= 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'deleted_at' => $user->deleted_at,
        ];
    }
}
