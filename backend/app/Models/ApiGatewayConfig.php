<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiGatewayConfig extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'environment',
        'service_name',
        'host',
        'path',
        'route_name',
        'route_path',
    ];

    protected $casts = [
        'environment' => 'string',
    ];

    /**
     * Get the aplikasi that owns the api gateway config.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
