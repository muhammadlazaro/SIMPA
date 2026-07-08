<?php

namespace App\Support;

use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AplikasiAccess
{
    private const UNIT_KERJA_UAT_REVIEW_STATUSES = [
        Aplikasi::STATUS_UAT,
        Aplikasi::STATUS_PERBAIKAN_UAT,
    ];

    public static function scopeVisibleToUnitKerja(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visible) use ($user) {
            $visible
                ->where('created_by', $user->getKey())
                ->orWhere(function (Builder $review) {
                    $review
                        ->whereIn('status', self::UNIT_KERJA_UAT_REVIEW_STATUSES)
                        ->whereDoesntHave('creator', function (Builder $creator) {
                            $creator->where('role', 'unit_kerja');
                        });
                });
        });
    }

    public static function canUnitKerjaAccess(User $user, Aplikasi $aplikasi): bool
    {
        if (! $user->isUnitKerja()) {
            return false;
        }

        if ((int) $aplikasi->getAttribute('created_by') === (int) $user->getKey()) {
            return true;
        }

        return self::requiresSharedUnitKerjaUatReview($aplikasi);
    }

    private static function requiresSharedUnitKerjaUatReview(Aplikasi $aplikasi): bool
    {
        if (! in_array((string) $aplikasi->getAttribute('status'), self::UNIT_KERJA_UAT_REVIEW_STATUSES, true)) {
            return false;
        }

        $creator = $aplikasi->relationLoaded('creator')
            ? $aplikasi->getRelationValue('creator')
            : $aplikasi->creator()->select('id', 'role')->first();

        return ! $creator || ! $creator->isUnitKerja();
    }
}
