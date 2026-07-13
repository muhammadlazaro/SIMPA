<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Rfc extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DIAJUKAN = 'Diajukan';
    public const STATUS_ANALISA_DESAIN = 'Analisa Desain';
    public const STATUS_DEV_STAGING = 'Dev-Staging';
    public const STATUS_PRODUCTION = 'Production';
    public const STATUS_UAT = 'UAT';

    public const STATUS_VALUES = [
        self::STATUS_DIAJUKAN,
        self::STATUS_ANALISA_DESAIN,
        self::STATUS_DEV_STAGING,
        self::STATUS_PRODUCTION,
        self::STATUS_UAT,
    ];

    public const OPEN_STATUS_VALUES = [
        self::STATUS_DIAJUKAN,
        self::STATUS_ANALISA_DESAIN,
        self::STATUS_DEV_STAGING,
        self::STATUS_UAT,
    ];

    public const TIPE_VALUES = [
        'Medium',
        'Standar',
        'Minor',
        'Major',
        'Darurat',
    ];

    public const PELAKSANA_VALUES = [
        'Internal Pusdatik',
        'Eksternal',
        'Internal D13',
    ];

    protected $fillable = [
        'aplikasi_id',
        'tipe_rfc',
        'deskripsi',
        'formulir_path',
        'formulir_original_filename',
        'formulir_mime_type',
        'formulir_file_size',
        'pelaksana',
        'status_tindaklanjut',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'formulir_url',
    ];

    protected function casts(): array
    {
        return [
            'formulir_file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getFormulirUrlAttribute(): ?string
    {
        $path = $this->getAttribute('formulir_path');

        return $path ? Storage::disk('public')->url((string) $path) : null;
    }
}

