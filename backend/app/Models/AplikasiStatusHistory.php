<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AplikasiStatusHistory extends Model
{
    protected $fillable = [
        'aplikasi_id',
        'status_sebelumnya',
        'status_baru',
        'aksi',
        'catatan',
        'changed_by',
    ];

    public function aplikasi()
    {
        return $this->belongsTo(Aplikasi::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
