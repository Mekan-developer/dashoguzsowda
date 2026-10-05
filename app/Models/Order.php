<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Заказ товаров одного магазина: решение по нему принимает владелец магазина,
 * он же доставляет (см. CLAUDE.md → «Заказы и корзина»).
 */
class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'total', 'commission_total',
        'contact_name', 'phone', 'region_id', 'city_id', 'district_id', 'address', 'latitude', 'longitude', 'comment',
        'payment_method_id',
        'decision_comment', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'total'            => 'decimal:2',
            'commission_total' => 'decimal:2',
            'latitude'         => 'float',
            'longitude'        => 'float',
            'decided_at'       => 'datetime',
        ];
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function region()    { return $this->belongsTo(Region::class); }
    public function city()      { return $this->belongsTo(City::class); }
    public function district()  { return $this->belongsTo(District::class); }
    /** Кто решил по заказу: владелец магазина. При отмене покупателем — никто. */
    public function decider()   { return $this->belongsTo(User::class, 'decided_by'); }
    /** Чем покупатель рассчитается. Пусто — договорятся по телефону. */
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    public function suborders() { return $this->hasMany(Suborder::class); }
    public function items()     { return $this->hasMany(OrderItem::class); }

    /**
     * Человекочитаемый номер для звонка покупателю: id с ведущими нулями.
     * Отдельной колонки нет намеренно — она потребовала бы генерации с
     * блокировкой, а id уникален сам по себе.
     */
    public function getNumberAttribute(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Сколько из суммы заказа уходит магазинам: комиссия платформы удерживается
     * с них, покупатель платит total целиком.
     */
    public function getPayoutAttribute(): float
    {
        return round((float) $this->total - (float) $this->commission_total, 2);
    }

    /** Может ли покупатель ещё отменить заказ — только пока продавец не ответил. */
    public function isCancelableByBuyer(): bool
    {
        return $this->status === 'pending';
    }
}
