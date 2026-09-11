<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Часть заказа, относящаяся к магазину, — и его сторона сделки: сумма,
 * комиссия платформы и ответ владельца.
 *
 * В заказе всегда один магазин, поэтому подзаказ у заказа один. Таблица
 * осталась отдельной: у неё свой статус, своя сумма и снимок ставки комиссии,
 * а заказ ведёт учёт со стороны покупателя (см. CLAUDE.md → «Заказы и корзина»).
 */
class Suborder extends Model
{
    protected $fillable = [
        'order_id', 'store_id', 'user_id', 'status', 'subtotal',
        'commission_percent', 'commission_total', 'comment', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'           => 'decimal:2',
            // Снимок ставки магазина на момент заказа — договорённость с
            // магазином могут пересмотреть, пока заказ живёт
            'commission_percent' => 'decimal:2',
            'commission_total'   => 'decimal:2',
            'responded_at'       => 'datetime',
        ];
    }

    /** Сумма, которая остаётся магазину после комиссии платформы. */
    public function getPayoutAttribute(): float
    {
        return round((float) $this->subtotal - (float) $this->commission_total, 2);
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function store() { return $this->belongsTo(Store::class); }
    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }

    /**
     * Ждём ли ответа владельца. Отвечает он один раз: его ответ и есть решение
     * по заказу, после него заказ уже принят, отклонён или отменён.
     */
    public function isAwaitingOwner(): bool
    {
        return $this->status === 'pending' && $this->order?->status === 'pending';
    }

    /**
     * Можно ли отметить заказ доставленным: продавец принял его и везёт сам.
     * Отменённый покупателем и отклонённый сюда не попадают.
     */
    public function isDeliverable(): bool
    {
        return $this->status === 'accepted' && $this->order?->status === 'approved';
    }
}
