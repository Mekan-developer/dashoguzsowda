<?php

namespace App\Actions;

use App\Events\SmsCodeRequested;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use Illuminate\Validation\ValidationException;

class SendSmsCodeAction
{
    public function __construct(
        private readonly SmsCodeRepositoryInterface $smsCodeRepository,
    ) {}

    public function execute(string $phone): void
    {
        $this->ensureCooldownPassed($phone);

        // Старые коды гасим — активен всегда только последний
        $this->smsCodeRepository->invalidateActive($phone);

        $code = $this->generateCode();
        $this->smsCodeRepository->create($phone, $code, config('sms.ttl'));
        $this->smsCodeRepository->startResendCooldown($phone, config('sms.resend_cooldown'));

        SmsCodeRequested::dispatch($phone, $code);
    }

    private function ensureCooldownPassed(string $phone): void
    {
        $seconds = $this->smsCodeRepository->resendCooldownRemaining($phone);

        if ($seconds > 0) {
            throw ValidationException::withMessages([
                'phone' => __('messages.sms_resend_wait', ['seconds' => $seconds]),
            ]);
        }
    }

    private function generateCode(): string
    {
        $length = config('sms.code_length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }
}
