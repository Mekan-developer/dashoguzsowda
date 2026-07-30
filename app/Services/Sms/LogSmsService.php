<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Dev-реализация: пишет код в laravel.log вместо реальной отправки.
 * Включается при SMS_DRIVER=log (значение по умолчанию), см. AppServiceProvider.
 */
class LogSmsService implements SmsSenderInterface
{
    public function sendOtp(string $phone, string $code): void
    {
        Log::info("SMS to {$phone}: " . __('messages.sms_code_text', ['code' => $code]));
    }
}
