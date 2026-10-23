<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const REASONS = ['spam' => 'Spam oder Betrug', 'illegal' => 'Illegale Inhalte', 'adult' => 'Unangemessene Inhalte', 'impersonation' => 'Identitätsmissbrauch', 'other' => 'Sonstiges'];

    protected $fillable = ['reason', 'details'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
