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

    /*
    | Тестовый вход: вместо случайного кода — всегда OTP_TEST, SMS не уходит.
    | Чтобы тестировщик мобилки входил без телефона-шлюза и без доступа к логам.
    | OTP_TEST_PHONES — номера через запятую, для которых он действует.
    | В production без списка не действует ни для кого (SendSmsCodeAction),
    | вне production пустой список = все номера.
    */
    'test_mode'   => (bool) env('OTP_GATEWAY_TEST', false),
    'test_code'   => (string) env('OTP_TEST', '123456'),
    'test_phones' => array_values(array_filter(array_map('trim', explode(',', (string) env('OTP_TEST_PHONES', ''))))),
];
