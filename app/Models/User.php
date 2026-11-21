<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'manager' => 'Manager',
        'warehouse' => 'Warehouse Staff',
        'inspector' => 'Quality Inspector',
        'sales' => 'Sales Team',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'active', 'last_seen_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['role_label', 'initials'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role === 'manager' || in_array($this->role, $roles, true);
    }
}
