<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'weekly_report'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'weekly_report' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('id');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }
}
