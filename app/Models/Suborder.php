<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Часть заказа, относящаяся к одному магазину.
 *
 * Владелец отвечает первым: заказ приходит ему сразу после оформления, он
 * подтверждает наличие товара, и только потом админ, видя ответы магазинов,
 * подтверждает заказ целиком (см. CLAUDE.md → «Заказы и корзина»).
 */
class Suborder extends Model
{
    protected $fillable = [
        'order_id', 'store_id', 'user_id', 'status', 'subtotal', 'comment', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'     => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function store() { return $this->belongsTo(Store::class); }
    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }

    /**
     * Ждём ли ответа владельца. Отвечает он один раз и только пока заказ не
     * закрыт админом: после approve/reject/cancel вопрос решается по телефону.
     */
    public function isAwaitingOwner(): bool
    {
        return $this->status === 'pending' && $this->order?->status === 'pending';
    }
}
