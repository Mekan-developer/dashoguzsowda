<?php

return [
    // Длина кода подтверждения
    'code_length' => 6,

    // Время жизни кода, секунд (управляется через .env: OTP_TTL)
    'ttl' => env('OTP_TTL', 300),

    // Минимальный интервал между повторными отправками на один номер, секунд
    'resend_cooldown' => 60,

    // Максимум неверных попыток ввода одного кода
    'max_attempts' => 5,

    // Локальный модем/шлюз (LocalModemSmsService) — прод-отправка, см. Settings → SMS-шлюз
    'gateway_url'   => env('SMS_GATEWAY_URL'),
    'gateway_token' => env('SMS_GATEWAY_TOKEN'),
    'device_label'  => env('SMS_GATEWAY_DEVICE_LABEL', 'Локальный модем'),
];
