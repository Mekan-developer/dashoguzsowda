<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'region_id', 'city_id', 'district_id',
        'name', 'description', 'phone', 'address',
        'sells_retail', 'sells_wholesale', 'has_delivery', 'commission_percent',
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
            // Комиссия платформы с каждого проданного товара этого магазина.
            // Ставит админ, у каждого магазина своя, по умолчанию — 0.
            'commission_percent' => 'decimal:2',
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

    /**
     * Чем у этого магазина можно расплатиться. Деньги покупатель отдаёт лично
     * продавцу, поэтому и условия расчёта — его: он отмечает свои способы из
     * справочника админа, а покупатель выбирает при заказе только из них.
     */
    public function paymentMethods()
    {
        return $this->belongsToMany(PaymentMethod::class)
            ->orderBy('sort_order')
            ->orderBy('payment_methods.id');
    }

    /** Виден ли магазин в публичной витрине: прошёл модерацию и тариф владельца не истёк. */
    public function isPublic(): bool
    {
        return $this->status === 'approved' && $this->is_active;
    }

    /** Показывать ли, что магазин торгует оптом: владельцу и тем, кто видит опт. */
    public function showsWholesaleTo(?User $viewer): bool
    {
        return $viewer !== null && ($viewer->id === $this->user_id || $viewer->seesWholesale());
    }

    /**
     * Витрина для конкретного зрителя: публичная, а чисто оптовая — только
     * тем, кто видит опт (CLAUDE.md → «Магазины»), и самому владельцу.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        return $this->isPublic() && ($this->sells_retail || $this->showsWholesaleTo($viewer));
    }
}
