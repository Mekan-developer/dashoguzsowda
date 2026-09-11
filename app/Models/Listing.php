<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Можно ли заказать товар через корзину.
     *
     * Заказ есть только у товаров магазина с доставкой: без неё покупатель и
     * продавец, как и раньше, созваниваются сами. Товар без цены (договорная)
     * заказать тоже нельзя — нечего фиксировать в позиции заказа.
     */
    public function isOrderable(): bool
    {
        return $this->status === 'approved'
            && $this->store !== null
            && $this->store->isPublic()
            && (bool) $this->store->has_delivery
            && ($this->stock_qty === null || $this->stock_qty > 0)
            && ($this->price !== null || $this->wholesale_price !== null);
    }

    /**
     * Оптовое предложение: у товара только оптовая цена или его магазин торгует
     * одним оптом. Клиенту такие не показываются — опт видит только розничный
     * продавец (User::seesWholesale, CLAUDE.md → «Магазины»).
     */
    public function isWholesaleOnly(): bool
    {
        return ($this->price === null && $this->wholesale_price !== null)
            || ($this->store !== null && ! $this->store->sells_retail);
    }

    /** Кому отдавать оптовую цену и партию: владельцу и тем, кто видит опт. */
    public function showsWholesaleTo(?User $viewer): bool
    {
        return $viewer !== null && ($viewer->id === $this->user_id || $viewer->seesWholesale());
    }

    /** Доступна ли карточка: одобрена (или своя) и не оптовое предложение для клиента. */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $this->user_id) {
            return true;
        }

        return $this->status === 'approved'
            && (! $this->isWholesaleOnly() || $viewer?->seesWholesale() === true);
    }

    /**
     * То же, что ! isWholesaleOnly(), но для запросов: розничные предложения и
     * обычные объявления. Выдача и карточка обязаны считать одинаково.
     */
    public function scopeRetailOffers(Builder $query): void
    {
        $query
            ->where(fn ($q) => $q->whereNull('wholesale_price')->orWhereNotNull('price'))
            ->where(fn ($q) => $q->whereNull('store_id')
                ->orWhereHas('store', fn ($s) => $s->where('sells_retail', true)));
    }
}
