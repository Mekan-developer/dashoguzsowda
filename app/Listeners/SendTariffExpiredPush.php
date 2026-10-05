<?php

namespace App\Listeners;

use App\Events\TariffExpired;
use App\Services\PushNotificationService;

class SendTariffExpiredPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(TariffExpired $event): void
    {
        $hidden = $event->suspendedListings + $event->suspendedVideos;

        $this->pushNotificationService->sendToUser(
            $event->user,
            __('messages.push_tariff_expired_title'),
            $hidden > 0
                ? __('messages.push_tariff_expired_body_hidden', [
                    'tariff'   => $event->expiredTariff?->name ?? '',
                    'listings' => $event->suspendedListings,
                    'videos'   => $event->suspendedVideos,
                ])
                : __('messages.push_tariff_expired_body', [
                    'tariff' => $event->expiredTariff?->name ?? '',
                ]),
            ['type' => 'tariff', 'id' => (string) ($event->user->tariff_id ?? '')],
        );
    }
}
