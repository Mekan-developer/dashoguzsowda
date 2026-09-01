<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'category_id', 'title', 'description', 'type', 'price',
        'wholesale_price', 'min_order_qty', 'stock_qty',
        'region_id', 'city_id', 'district_id', 'phone', 'tags', 'location', 'status',
        'rejection_reason_id', 'is_boosted', 'boosted_at',
    ];

    protected $casts = [
        'tags'       => 'array',
        'location'   => 'array',
        'is_boosted' => 'boolean',
        'boosted_at' => 'datetime',
    ];

    public function user()            { return $this->belongsTo(User::class); }
    public function store()           { return $this->belongsTo(Store::class); }
    public function category()        { return $this->belongsTo(Category::class); }
    public function region()          { return $this->belongsTo(Region::class); }
    public function city()            { return $this->belongsTo(City::class); }
    public function district()        { return $this->belongsTo(District::class); }
    public function media()           { return $this->hasMany(ListingMedia::class)->orderBy('order'); }
    public function rejectionReason() { return $this->belongsTo(RejectionReason::class); }
    public function complaints()      { return $this->hasMany(Complaint::class); }
    public function favorites()       { return $this->hasMany(Favorite::class); }
    public function reviews()         { return $this->hasMany(Review::class); }
}
