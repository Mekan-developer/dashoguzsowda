<?php

namespace App\Services\Sms;

/**
 * Достижимость шлюза (Settings → мониторинг) проверяется всегда напрямую по
 * config('sms.gateway_url'), независимо от того, что сейчас реально забинжено в
 * SmsSenderInterface — это два разных вопроса: "шлюз физически доступен" и
 * "через него ли сейчас реально уходят SMS" (переключение биндинга — отдельный
 * ручной шаг, см. AppServiceProvider).
 */
class SmsGatewayStatusService
{
    public function __construct(private readonly LocalModemSmsService $gateway)
    {
    }

    public function resolve(): array
    {
        return $this->gateway->status();
    }
}
