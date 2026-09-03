<?php

namespace App\Actions;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Заказ доставлен и оплачен на месте — админ закрывает его.
 *
 * Единственный шаг после подтверждения: промежуточного «в пути» нет, доставку
 * ведёт сам админ и отмечает только результат. Отмена и отказ — отдельные
 * действия: у них есть побочные эффекты с остатками.
 */
class CompleteOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Order $order, User $admin): Order
    {
        if ($order->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => __('messages.order_status_transition_invalid'),
            ]);
        }

        $completed = $this->orderService->changeStatus($order, 'completed', $admin);

        event(new OrderStatusChanged($completed));

        return $completed;
    }
}
