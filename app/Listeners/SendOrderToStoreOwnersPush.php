<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\PushNotificationService;

/**
 * Оформленный заказ сразу разъезжается по владельцам магазинов: каждому — своя
 * часть, поэтому push отправляется по одному, с id этой части. Владелец
 * подтверждает наличие первым, админ подтверждает заказ уже после него.
 *
 * Deep-link type `store_order` мобилка пока не знает и открывает /home —
 * штатный fallback, описанный в CLAUDE.md → «Push-уведомления».
 */
class SendOrderToStoreOwnersPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('suborders.user');

        foreach ($order->suborders as $suborder) {
            if (! $suborder->user) {
                continue;
            }

            $this->pushNotificationService->sendToUser(
                $suborder->user,
                __('messages.push_store_order_title'),
                __('messages.push_store_order_body', ['number' => $order->number]),
                ['type' => 'store_order', 'id' => (string) $suborder->id],
            );
        }
    }
}
