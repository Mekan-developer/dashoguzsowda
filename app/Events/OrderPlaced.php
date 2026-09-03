<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Покупатель оформил заказ. Отсюда он уходит владельцам магазинов: отвечают
 * они первыми, а админ подтверждает заказ уже по их ответам.
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
