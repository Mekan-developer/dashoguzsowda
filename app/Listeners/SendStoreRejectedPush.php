<?php

namespace App\Listeners;

use App\Events\StoreRejected;
use App\Services\PushNotificationService;

class SendStoreRejectedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(StoreRejected $event): void
    {
        $user = $event->store->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_store_rejected_title'),
            __('messages.push_store_rejected_body', ['name' => $event->store->name]),
            ['type' => 'store', 'id' => (string) $event->store->id],
        );
    }
}
