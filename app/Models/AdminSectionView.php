<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSectionView extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'section', 'last_seen_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
