<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Прод-реализация: код уходит в socket-server (socket-server/index.js), который
 * ре-эмитит его как socket.io-событие телефону-отправителю SMS.
 * Включается при SMS_DRIVER=modem, см. AppServiceProvider.
 *
 * Контракт со шлюзом (обе стороны должны совпадать):
 *   POST {SMS_GATEWAY_URL}/emit-otp
 *   Header: X-Otp-Secret: {OTP_SECRET}
 *   Body:   {"phone_number": "+993...", "otp": "123456"}
 * Ответы: 200 — событие отправлено, 401 — неверный секрет,
 *         503 — телефон-шлюз не подключён.
 */
class LocalModemSmsService implements SmsSenderInterface
{
    public function sendOtp(string $phone, string $code): void
    {
        $url = config('sms.gateway_url');

        if (! $url) {
            throw new \RuntimeException('SMS-шлюз не настроен: не задан SMS_GATEWAY_URL');
        }

        $secret = (string) config('sms.otp_secret');

        if ($secret === '') {
            throw new \RuntimeException('SMS-шлюз не настроен: не задан OTP_SECRET');
        }

        $response = Http::timeout(5)
            ->withHeaders(['X-Otp-Secret' => $secret])
            ->post(rtrim($url, '/') . '/emit-otp', [
                'phone_number' => $phone,
                'otp'          => $code,
            ]);

        if ($response->status() === 503) {
            throw new \RuntimeException('Телефон SMS-шлюза не подключён к серверу');
        }

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
