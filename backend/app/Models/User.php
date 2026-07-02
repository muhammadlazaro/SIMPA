<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * Admin sistem (kelola personil dan akses sistem).
     */
    public function isAdminSistem(): bool
    {
        return $this->role === 'admin_sistem';
    }

    /**
     * Pengelola aplikasi (full management of aplikasi / RFC per app rules).
     */
    public function isPengelolaAplikasi(): bool
    {
        return $this->role === 'pengelola_aplikasi';
    }

    /**
     * Analis desain (analisa & desain workflows).
     */
    public function isAnalisDesain(): bool
    {
        return $this->role === 'analis_desain';
    }

    /**
     * Unit kerja (pemilik aplikasi/pengaju).
     */
    public function isUnitKerja(): bool
    {
        return $this->role === 'unit_kerja';
    }

    /**
     * Aplikasi yang diajukan oleh user ini (pemilik pengajuan / unit kerja).
     */
    public function aplikasiDiajukan(): HasMany
    {
        return $this->hasMany(Aplikasi::class, 'created_by');
    }

    /**
     * Check if user is implementation team member.
     */
    public function isTimImplementasiAplikasi(): bool
    {
        return $this->role === 'tim_implementasi_aplikasi';
    }

    /**
     * Check if user is devops developer.
     */
    public function isDevops(): bool
    {
        return $this->role === 'devops_developer';
    }

    /**
     * Check if user is security testing team.
     */
    public function isTimUjiKeamanan(): bool
    {
        return $this->role === 'tim_uji_keamanan';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
