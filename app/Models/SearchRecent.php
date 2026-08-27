<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchRecent extends Model
{
    protected $fillable = ['user_id', 'query', 'query_key'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
