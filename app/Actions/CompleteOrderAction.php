<?php

namespace App\Actions;

use App\Events\OrderStatusChanged;
use App\Models\Suborder;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Заказ доставлен и оплачен на месте — владелец магазина закрывает его.
 *
 * Доставку делает сам продавец, поэтому и отметку ставит он: промежуточного
 * «в пути» нет, отмечается только результат. Отмена — отдельное действие
 * покупателя, у неё свои побочные эффекты с остатками.
 */
class CompleteOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Suborder $suborder, User $owner): Suborder
    {
        if ($suborder->user_id !== $owner->id) {
            throw ValidationException::withMessages([
                'status' => __('messages.forbidden'),
            ]);
        }

        $order = $suborder->order;

        // Закрывать можно только принятый заказ: пока продавец не подтвердил
        // наличие, доставлять нечего
        if ($order?->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => __('messages.order_status_transition_invalid'),
            ]);
        }

        event(new OrderStatusChanged(
            $this->orderService->changeStatus($order, 'completed', $owner),
        ));

        return $this->orderService->refreshSuborder($suborder);
    }
}
