<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Item extends Model
{
    public const TYPES = ['link', 'heading', 'text', 'image', 'youtube', 'twitch', 'spotify', 'countdown', 'gallery'];

    protected $fillable = [
        'type', 'title', 'url', 'icon', 'body', 'data', 'effect', 'is_visible',
        'schedule', 'age_gate', 'position',
    ];

    protected $hidden = ['password_hash'];

    protected $appends = ['has_password'];

    public function getHasPasswordAttribute(): bool
    {
        return $this->password_hash !== null;
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'schedule' => 'array',
            'is_visible' => 'boolean',
            'age_gate' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function isProtected(): bool
    {
        return $this->password_hash !== null || $this->age_gate;
    }

    /**
     * Zeitsteuerung: schedule = ['days' => [1..7], 'from' => 'HH:MM', 'to' => 'HH:MM'].
     */
    public function isActiveAt(?Carbon $now = null): bool
    {
        if (! $this->is_visible) {
            return false;
        }
        $s = $this->schedule;
        if (empty($s) || empty($s['enabled'])) {
            return true;
        }
        $now ??= now();
        $days = $s['days'] ?? [];
        if ($days && ! in_array($now->dayOfWeekIso, array_map('intval', $days), true)) {
            return false;
        }
        $from = $s['from'] ?? null;
        $to = $s['to'] ?? null;
        if ($from && $to) {
            $t = $now->format('H:i');
            return $from <= $to ? ($t >= $from && $t < $to) : ($t >= $from || $t < $to);
        }

        return true;
    }
}
