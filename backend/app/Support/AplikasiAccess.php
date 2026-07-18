<?php

namespace App\Support;

use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AplikasiAccess
{
    public static function scopeVisibleToUnitKerja(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->getKey());
    }

    public static function canUnitKerjaAccess(User $user, Aplikasi $aplikasi): bool
    {
        if (! $user->isUnitKerja()) {
            return false;
        }

        if ((int) $aplikasi->getAttribute('created_by') === (int) $user->getKey()) {
            return true;
        }

        return false;
    }
}
