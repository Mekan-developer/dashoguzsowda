<?php

namespace App\Services\Sms;

interface SmsSenderInterface
{
    /**
     * Доставить код подтверждения на номер.
     *
     * Реализация получает именно код, а не готовый текст сообщения: шлюз
     * передаёт телефону `{phone_number, otp}`, а текст SMS собирается уже
     * на стороне телефона-отправителя.
     */
    public function sendOtp(string $phone, string $code): void;
}
