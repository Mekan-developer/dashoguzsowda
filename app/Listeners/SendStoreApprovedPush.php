<?php

namespace App\Listeners;

use App\Events\StoreApproved;
use App\Services\PushNotificationService;

class SendStoreApprovedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(StoreApproved $event): void
    {
        $user = $event->store->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_store_approved_title'),
            __('messages.push_store_approved_body', ['name' => $event->store->name]),
            ['type' => 'store', 'id' => (string) $event->store->id],
        );
    }
}
