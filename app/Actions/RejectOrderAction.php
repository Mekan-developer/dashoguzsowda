<?php

namespace App\Actions;

use App\Events\OrderRejected;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Отказ по заказу: товара нет, покупатель недоступен или адрес вне доставки.
 * Причина уходит покупателю в push и остаётся в карточке заказа.
 */
class RejectOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Order $order, User $admin, ?string $comment = null): Order
    {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('messages.order_already_processed'),
            ]);
        }

        // Заказа не будет — магазинам отвечать больше не на что
        $this->orderService->closeSuborders($order, 'canceled', ['pending', 'accepted']);

        $rejected = $this->orderService->changeStatus($order, 'rejected', $admin, $comment);

        event(new OrderRejected($rejected));

        return $rejected;
    }
}
