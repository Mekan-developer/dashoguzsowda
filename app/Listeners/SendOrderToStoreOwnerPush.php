<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\PushNotificationService;

/**
 * Оформленный заказ сразу уходит владельцу магазина: решение по заказу
 * принимает он — подтверждает наличие, везёт сам и получает деньги.
 *
 * Магазин в заказе один, но идём по частям заказа: id части и есть адрес
 * заказа в магазинном API (/v1/my/store/orders/{id}).
 *
 * Deep-link type `store_order` мобилка пока не знает и открывает /home —
 * штатный fallback, описанный в CLAUDE.md → «Push-уведомления».
 */
class SendOrderToStoreOwnerPush
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
