<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = [
        'name', 'name_ru', 'name_tk', 'price', 'listings_limit', 'videos_limit', 'boost_limit',
        'duration_days', 'is_free', 'is_active', 'can_have_store', 'can_see_wholesale',
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'is_free'            => 'boolean',
            'is_active'          => 'boolean',
            'can_have_store'     => 'boolean',
            'can_see_wholesale'  => 'boolean',
        ];
    }

    public function users()   { return $this->hasMany(User::class); }
    public function requests() { return $this->hasMany(TariffRequest::class); }

    public function canHaveStore(): bool
    {
        return (bool) $this->can_have_store;
    }

    /** Видят ли подписчики этого тарифа оптовые цены и чисто оптовые магазины. */
    public function canSeeWholesale(): bool
    {
        return (bool) $this->can_see_wholesale;
    }
}
