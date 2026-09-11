<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\PushNotificationService;

/**
 * Покупатель отменил заказ — продавцу об этом надо узнать первым: заказ ведёт
 * он и мог уже начать собирать товар.
 *
 * Отменить можно только заказ без ответа продавца, поэтому push уходит ровно
 * тому, кто ещё держит его у себя в «новых».
 */
class SendOrderCanceledToStorePush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->order->status !== 'canceled') {
            return;
        }

        $order = $event->order->loadMissing('suborders.user');

        foreach ($order->suborders as $suborder) {
            if (! $suborder->user) {
                continue;
            }

            $this->pushNotificationService->sendToUser(
                $suborder->user,
                __('messages.push_store_order_canceled_title'),
                __('messages.push_store_order_canceled_body', ['number' => $order->number]),
                ['type' => 'store_order', 'id' => (string) $suborder->id],
            );
        }
    }
}
