<?php

namespace App\Listeners;

use App\Events\ListingRejected;
use App\Services\PushNotificationService;

class SendListingRejectedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(ListingRejected $event): void
    {
        $user = $event->listing->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_listing_rejected_title'),
            __('messages.push_listing_rejected_body', ['title' => $event->listing->title]),
            ['type' => 'listing', 'id' => $event->listing->id],
        );
    }
}
