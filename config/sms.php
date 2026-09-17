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

    /*
    | Чем отправлять коды:
    |   log   — писать в laravel.log (dev, значение по умолчанию)
    |   modem — слать в socket-server → телефон-отправитель (прод)
    | Биндинг SmsSenderInterface собирается по этому значению в AppServiceProvider.
    */
    'driver' => env('SMS_DRIVER', 'log'),

    // Локальный модем/шлюз (LocalModemSmsService) — прод-отправка, см. Settings → SMS-шлюз
    'gateway_url'  => env('SMS_GATEWAY_URL'),
    'device_label' => env('SMS_GATEWAY_DEVICE_LABEL', 'Локальный модем'),

    /*
    | Общий секрет между backend, socket-server и телефоном-отправителем.
    | Backend шлёт его в заголовке X-Otp-Secret, телефон (domains/otp) —
    | в socket.handshake.auth.token (шлюз также принимает auth.secret).
    | Одно и то же значение должно стоять в .env сервера и в настройках телефона.
    */
    'otp_secret' => env('OTP_SECRET'),
];
