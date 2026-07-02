<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'deployment',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
    ];

    protected $casts = [
        'deployment' => 'string',
    ];

    /**
     * Get the aplikasi that owns the database config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
