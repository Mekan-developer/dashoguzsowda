<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Прод-реализация отправки SMS через локальный модем/телефон (без сторонних SMS-сервисов).
 * Не забинжена в SmsSenderInterface по умолчанию (см. AppServiceProvider) — переключить
 * биндинг с LogSmsService на этот класс, когда шлюз будет физически настроен и протестирован.
 */
class LocalModemSmsService implements SmsSenderInterface
{
    public function send(string $phone, string $message): void
    {
        $url = config('sms.gateway_url');

        if (! $url) {
            throw new \RuntimeException('SMS-шлюз не настроен: не задан SMS_GATEWAY_URL');
        }

        $response = Http::timeout(5)
            ->withToken((string) config('sms.gateway_token'))
            ->post($url, ['phone' => $phone, 'message' => $message]);

        if (! $response->successful()) {
            throw new \RuntimeException("SMS-шлюз вернул ошибку: HTTP {$response->status()}");
        }

        \App\Models\Setting::set('sms_gateway_last_sync_at', now()->toIso8601String());
    }

    public function status(): array
    {
        $url = config('sms.gateway_url');

        $connected = false;
        $clients   = null;

        if ($url) {
            try {
                $response  = Http::timeout(2)->get(rtrim($url, '/') . '/health');
                $connected = $response->successful();
                if ($connected) {
                    $clients = (int) $response->json('clients', 0);
                    \App\Models\Setting::set('sms_gateway_last_sync_at', now()->toIso8601String());
                }
            } catch (\Throwable $e) {
                Log::warning('SMS gateway healthcheck failed: ' . $e->getMessage());
                $connected = false;
            }
        }

        return [
            'connected'    => $connected,
            'configured'   => (bool) $url,
            'device'       => config('sms.device_label'),
            'address'      => $url,
            'clients'      => $clients,
            'last_sync_at' => \App\Models\Setting::get('sms_gateway_last_sync_at'),
        ];
    }
}
