<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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

    protected $casts = [
        'role' => 'string',
    ];

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is admin1 (can manage apps and RFCs)
     */
    public function isAdmin1(): bool
    {
        return $this->role === 'admin' || $this->role === 'admin1';
    }

    /**
     * Check if user is admin2 (can only manage analisa desain)
     */
    public function isAdmin2(): bool
    {
        return $this->role === 'admin2';
    }

    /**
     * Check if user is regular user (backward compatibility)
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if user is frontend role
     */
    public function isFrontend(): bool
    {
        return $this->role === 'frontend';
    }

    /**
     * Check if user is backend role
     */
    public function isBackend(): bool
    {
        return $this->role === 'backend';
    }

    /**
     * Check if user is devops role
     */
    public function isDevops(): bool
    {
        return $this->role === 'devops';
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
