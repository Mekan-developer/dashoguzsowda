<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'name', 'description', 'phone', 'address',
        'logo', 'is_popular', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_popular' => 'boolean'];
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function photos()   { return $this->hasMany(StorePhoto::class)->orderBy('order'); }
}
