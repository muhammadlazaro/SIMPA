<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $aplikasi_id
 * @property int|null $parent_id
 * @property string $note_type
 * @property string $body
 * @property bool $is_checked
 * @property int|null $created_by
 * @property int|null $checked_by
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AplikasiNote extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'parent_id',
        'note_type',
        'body',
        'is_checked',
        'created_by',
        'checked_by',
        'checked_at',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
            'checked_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class, 'aplikasi_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
