<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['page_id', 'item_id', 'day', 'kind', 'device', 'source', 'country', 'count'];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }
}
