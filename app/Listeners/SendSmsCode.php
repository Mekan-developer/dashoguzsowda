<?php

namespace App\Listeners;

use App\Events\SmsCodeRequested;
use App\Services\Sms\SmsSenderInterface;

class SendSmsCode
{
    public function __construct(
        private readonly SmsSenderInterface $smsSender,
    ) {}

    public function handle(SmsCodeRequested $event): void
    {
        $this->smsSender->sendOtp($event->phone, $event->code);
    }
}
