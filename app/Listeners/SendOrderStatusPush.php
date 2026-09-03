<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\PushNotificationService;

/**
 * Заказ доставлен или отменён админом — покупателю уходит push.
 * Статусы pending/approved/rejected сюда не попадают: у них свои события.
 */
class SendOrderStatusPush
{
    /** Статус → ключ пары заголовок/текст в messages. */
    private const TEXTS = [
        'completed' => 'push_order_completed',
        'canceled'  => 'push_order_canceled',
    ];

    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $buyer = $event->order->user;
        $key   = self::TEXTS[$event->order->status] ?? null;

        if (! $buyer || ! $key) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $buyer,
            __("messages.{$key}_title"),
            __("messages.{$key}_body", ['number' => $event->order->number]),
            ['type' => 'order', 'id' => (string) $event->order->id],
        );
    }
}
