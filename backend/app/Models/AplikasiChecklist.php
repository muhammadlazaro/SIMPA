<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $aplikasi_id
 * @property string $category
 * @property string $title
 * @property string $item_status
 * @property string|null $notes
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AplikasiChecklist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'aplikasi_id',
        'category',
        'title',
        'item_status',
        'notes',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class, 'aplikasi_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
