<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackendConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'deployment',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'method',
        'url_endpoint',
        'check',
    ];

    protected $casts = [
        'deployment' => 'string',
        'check' => 'boolean',
    ];

    /**
     * Get the aplikasi that owns the backend config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
