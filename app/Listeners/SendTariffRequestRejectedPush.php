<?php

namespace App\Listeners;

use App\Events\TariffRequestRejected;
use App\Services\PushNotificationService;

class SendTariffRequestRejectedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(TariffRequestRejected $event): void
    {
        $user = $event->tariffRequest->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_tariff_rejected_title'),
            __('messages.push_tariff_rejected_body', [
                'tariff' => $event->tariffRequest->tariff?->name ?? '',
            ]),
            ['type' => 'tariff', 'id' => (string) $event->tariffRequest->tariff_id],
        );
    }
}
