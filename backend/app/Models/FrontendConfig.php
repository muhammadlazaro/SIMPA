<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontendConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'nama_modul',
        'local_url',
        'feat_staging_production_url',
        'check',
    ];

    protected $casts = [
        'check' => 'boolean',
    ];

    /**
     * Get the aplikasi that owns the frontend config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
