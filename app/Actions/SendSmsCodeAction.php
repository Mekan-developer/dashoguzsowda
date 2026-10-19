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

        $testMode = $this->isTestMode($phone);
        $code = $testMode ? (string) config('sms.test_code') : $this->generateCode();
        $this->smsCodeRepository->create($phone, $code, config('sms.ttl'));
        $this->smsCodeRepository->startResendCooldown($phone, config('sms.resend_cooldown'));

        // Тестовый код известен заранее — слать его некуда и незачем
        if (! $testMode) {
            SmsCodeRequested::dispatch($phone, $code);
        }
    }

    /**
     * В production тестовый код действует только для номеров из OTP_TEST_PHONES:
     * иначе по известному коду вошёл бы кто угодно в любой аккаунт.
     * Вне production пустой список — тестовый код для всех номеров.
     */
    private function isTestMode(string $phone): bool
    {
        if (! config('sms.test_mode')) {
            return false;
        }

        $phones = array_map($this->digits(...), config('sms.test_phones'));

        if ($phones === []) {
            return ! app()->environment('production');
        }

        // В списке можно писать номер без кода страны (62724494) — сверяем по
        // хвосту. Короче 8 цифр не берём: «4» совпало бы с каждым четвёртым
        $digits = $this->digits($phone);

        foreach ($phones as $listed) {
            if (strlen($listed) >= 8 && str_ends_with($digits, $listed)) {
                return true;
            }
        }

        return false;
    }

    // Номер приходит и с «+», и без — сравниваем только цифры
    private function digits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
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
