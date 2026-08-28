<?php

namespace App\Listeners;

use App\Events\TariffRequestApproved;
use App\Services\PushNotificationService;

class SendTariffRequestApprovedPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(TariffRequestApproved $event): void
    {
        $user = $event->tariffRequest->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_tariff_approved_title'),
            __('messages.push_tariff_approved_body', [
                'tariff' => $event->tariffRequest->tariff?->name ?? '',
            ]),
            ['type' => 'tariff', 'id' => (string) $event->tariffRequest->tariff_id],
        );
    }
}
