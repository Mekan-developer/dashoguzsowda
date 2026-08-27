<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQueryLog extends Model
{
    protected $fillable = ['query', 'query_key', 'hits', 'last_searched_at'];

    protected function casts(): array
    {
        return ['last_searched_at' => 'datetime'];
    }
}
