<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proyek extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'modul',
        'jenis',
    ];

    protected $casts = [
        'jenis' => 'string',
    ];

    /**
     * Get the aplikasi that owns the proyek.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
