<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Позиция заказа. title и unit_price — снимок на момент оформления: объявление
 * могут отредактировать или удалить, а заказ должен остаться читаемым.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'suborder_id', 'listing_id',
        'title', 'unit_price', 'is_wholesale', 'qty', 'total', 'commission_amount', 'stock_taken',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'   => 'decimal:2',
            'total'        => 'decimal:2',
            // Комиссия платформы с этой позиции: total × ставка магазина.
            // Процент один на весь подзаказ, здесь только его сумма
            'commission_amount' => 'decimal:2',
            'is_wholesale' => 'boolean',
            'stock_taken'  => 'boolean',
            'qty'          => 'integer',
        ];
    }

    public function order()    { return $this->belongsTo(Order::class); }
    public function suborder() { return $this->belongsTo(Suborder::class); }
    public function listing()  { return $this->belongsTo(Listing::class); }
}
