<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = [
        'name', 'name_ru', 'name_tk', 'listings_limit', 'videos_limit', 'boost_limit',
        'duration_days', 'is_free', 'is_active', 'can_have_store',
    ];

    protected function casts(): array
    {
        return [
            'is_free'        => 'boolean',
            'is_active'      => 'boolean',
            'can_have_store' => 'boolean',
        ];
    }

    public function users() { return $this->hasMany(User::class); }

    public function canHaveStore(): bool
    {
        return (bool) $this->can_have_store;
    }
}
