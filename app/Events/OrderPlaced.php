<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Покупатель оформил заказ. Отсюда он уходит владельцу магазина: решение по
 * заказу принимает он — подтверждает наличие, везёт сам и получает деньги.
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
