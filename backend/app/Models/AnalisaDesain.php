<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalisaDesain extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'aplikasi_id',
        'ui_platform',
        'interop_type',
        'storage_type',
        'nama_aktor',
        'method',
        'url',
        'tipe_resource',
        'aktor_transaksi',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tipe_resource' => 'string',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Automatically set created_by when creating
        static::creating(function ($model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->id();
            }
        });

        // Automatically set updated_by when updating
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Get the aplikasi that owns the analisa desain.
     */
    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }

    /**
     * Get the user who created this analisa desain.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this analisa desain.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
