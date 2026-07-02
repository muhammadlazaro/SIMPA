<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevopsConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'project',
        'dbt_dev',
        'dbt',
        'spl_dev',
        'spl',
        'auth',
        'env_staging',
        'env_production',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
    ];

    /**
     * Get the aplikasi that owns the devops config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
