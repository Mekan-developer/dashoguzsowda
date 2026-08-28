<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'region_id', 'city_id', 'district_id',
        'name', 'description', 'phone', 'address',
        'sells_retail', 'sells_wholesale', 'has_delivery',
        'logo', 'status', 'rejection_reason_id', 'is_active',
        'is_popular', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_popular'      => 'boolean',
            'sells_retail'    => 'boolean',
            'sells_wholesale' => 'boolean',
            'has_delivery'    => 'boolean',
            'is_active'       => 'boolean',
        ];
    }

    public function user()            { return $this->belongsTo(User::class); }
    public function category()        { return $this->belongsTo(Category::class); }
    public function region()          { return $this->belongsTo(Region::class); }
    public function city()            { return $this->belongsTo(City::class); }
    public function district()        { return $this->belongsTo(District::class); }
    public function rejectionReason() { return $this->belongsTo(RejectionReason::class); }
    public function listings()        { return $this->hasMany(Listing::class); }
    public function photos()          { return $this->hasMany(StorePhoto::class)->orderBy('order'); }

    /** Виден ли магазин в публичной витрине: прошёл модерацию и тариф владельца не истёк. */
    public function isPublic(): bool
    {
        return $this->status === 'approved' && $this->is_active;
    }
}
