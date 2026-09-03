<?php

namespace App\Listeners;

use App\Events\OrderApproved;
use App\Services\PushNotificationService;

class SendOrderApprovedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(OrderApproved $event): void
    {
        $buyer = $event->order->user;

        if (! $buyer) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $buyer,
            __('messages.push_order_approved_title'),
            __('messages.push_order_approved_body', ['number' => $event->order->number]),
            ['type' => 'order', 'id' => (string) $event->order->id],
        );
    }
}
