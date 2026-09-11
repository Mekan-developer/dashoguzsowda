<?php

namespace App\Actions;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Отмена заказа покупателем.
 *
 * Отменить можно, только пока продавец не ответил (pending): дальше он уже
 * собирает товар и едет, и вопрос решается с ним по телефону — его номер
 * покупатель видит в карточке заказа.
 *
 * Продавцу об отмене уходит push: заказ ведёт он, и узнать об этом должен
 * первым.
 */
class CancelOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Order $order): Order
    {
        if (! $order->isCancelableByBuyer()) {
            throw ValidationException::withMessages([
                'status' => __('messages.order_cannot_cancel'),
            ]);
        }

        // Остатки списываются только при подтверждении продавца, то есть у
        // отменяемого заказа списаний нет. Вызов оставлен намеренно: возврат
        // идёт строго по позициям с stock_taken и лишнего не тронет
        $this->orderService->releaseStock($order);
        $this->orderService->closeSuborders($order, 'canceled', ['pending']);

        $canceled = $this->orderService->changeStatus($order, 'canceled');

        event(new OrderStatusChanged($canceled));

        return $canceled;
    }
}
