<?php

namespace App\Actions;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Отмена заказа.
 *
 * Покупатель отменяет только пока заказ никто не взял в работу (pending) —
 * дальше вопрос решается с админом по телефону, иначе отменённый заказ уже
 * будет ехать к покупателю. Админ может отменить и подтверждённый; остатки,
 * списанные при подтверждении, возвращаются.
 */
class CancelOrderAction
{
    /** Статусы, из которых заказ ещё можно отменить админу. */
    private const ADMIN_CANCELABLE = ['pending', 'approved'];

    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Order $order, ?User $admin = null): Order
    {
        $allowed = $admin
            ? in_array($order->status, self::ADMIN_CANCELABLE, true)
            : $order->isCancelableByBuyer();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'status' => $admin
                    ? __('messages.order_already_processed')
                    : __('messages.order_cannot_cancel'),
            ]);
        }

        $this->orderService->releaseStock($order);

        // Заказ снят — части магазинов закрываются вместе с ним, чтобы не
        // висеть в «ждём ответа» (declined оставляем: магазин уже ответил)
        $this->orderService->closeSuborders($order, 'canceled', ['pending', 'accepted']);

        $canceled = $this->orderService->changeStatus($order, 'canceled', $admin);

        // Покупателю сообщаем, только если отменил не он сам
        if ($admin) {
            event(new OrderStatusChanged($canceled));
        }

        return $canceled;
    }
}
