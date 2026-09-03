<?php

namespace App\Listeners;

use App\Events\OrderRejected;
use App\Services\PushNotificationService;

class SendOrderRejectedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(OrderRejected $event): void
    {
        $buyer = $event->order->user;

        if (! $buyer) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $buyer,
            __('messages.push_order_rejected_title'),
            __('messages.push_order_rejected_body', ['number' => $event->order->number]),
            ['type' => 'order', 'id' => (string) $event->order->id],
        );
    }
}
