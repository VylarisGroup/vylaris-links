<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title', 'bio', 'theme', 'layout', 'avatar_ring', 'particles', 'cursor_sparks',
        'entrance_enabled', 'entrance_text', 'music_url', 'hide_branding', 'contact_enabled',
        'colors', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'cursor_sparks' => 'boolean',
            'entrance_enabled' => 'boolean',
            'hide_branding' => 'boolean',
            'contact_enabled' => 'boolean',
            'is_published' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('position')->orderBy('id');
    }

    public function socials(): HasMany
    {
        return $this->hasMany(Social::class)->orderBy('position')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest();
    }

    public function isPublic(): bool
    {
        return $this->is_published && $this->blocked_at === null && ! $this->user->isBlocked();
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function backgroundUrl(): ?string
    {
        return $this->background_path ? Storage::disk('public')->url($this->background_path) : null;
    }

    public function publicUrl(): string
    {
        return route('profile.show', $this->slug);
    }
}
