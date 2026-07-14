<?php

namespace App\Listeners;

use App\Events\ListingApproved;
use App\Services\PushNotificationService;

class SendListingApprovedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(ListingApproved $event): void
    {
        $user = $event->listing->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_listing_approved_title'),
            __('messages.push_listing_approved_body', ['title' => $event->listing->title]),
            ['type' => 'listing', 'id' => $event->listing->id],
        );
    }
}
