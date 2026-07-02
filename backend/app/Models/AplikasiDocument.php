<?php

namespace App\Models;

use App\Enums\AplikasiJenisDokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $aplikasi_id
 * @property AplikasiJenisDokumen|string $document_type
 * @property string $storage_path
 * @property string $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property int $version
 * @property string $status
 * @property int|null $uploaded_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AplikasiDocument extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'document_type',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size',
        'version',
        'status',
        'uploaded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => AplikasiJenisDokumen::class,
            'file_size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class, 'aplikasi_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
