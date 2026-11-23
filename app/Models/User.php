<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OFFICER = 'officer';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_OFFICER];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'position',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOfficer(): bool
    {
        return $this->role === self::ROLE_OFFICER;
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->isAdmin() ? 'Administrator' : 'Student Council Officer';
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ViolationRecord::class, 'reported_by');
    }
}
