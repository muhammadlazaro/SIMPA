<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'env_name',
        'env_value',
    ];

    /**
     * Get the aplikasi that owns the environment config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
