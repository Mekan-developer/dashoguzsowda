<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\PushNotificationService;

/**
 * Продавец отметил заказ доставленным — покупателю уходит push.
 *
 * Отмена сюда не попадает: отменяет заказ сам покупатель, уведомлять его о
 * собственном действии незачем — о ней узнаёт продавец
 * (SendOrderCanceledToStorePush). У подтверждения и отказа свои события.
 */
class SendOrderStatusPush
{
    /** Статус → ключ пары заголовок/текст в messages. */
    private const TEXTS = [
        'completed' => 'push_order_completed',
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
