<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['name', 'email', 'body'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
