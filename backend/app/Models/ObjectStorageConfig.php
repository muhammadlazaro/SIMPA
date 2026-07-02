<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectStorageConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'environment',
        'minio_bucket',
        'minio_default_region',
        'minio_endpoint',
        'minio_url',
        'minio_use_path_style_endpoint',
    ];

    protected $casts = [
        'environment' => 'string',
        'minio_use_path_style_endpoint' => 'boolean',
    ];

    /**
     * Get the aplikasi that owns the object storage config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
