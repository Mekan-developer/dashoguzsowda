<?php

namespace App\Services\Sms;

use App\Models\SmsCode;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;

/**
 * Ручной просмотр выданных OTP-кодов (Настройки → Мониторинг OTP).
 *
 * Нужен ровно на случай, когда телефон-отправитель отвалился: код в базе уже
 * лежит, а до пользователя не дошёл — админ читает его глазами и диктует.
 * Никаких текстов здесь нет: наружу уходят ключи статусов, подписи собирает
 * фронт через vue-i18n.
 */
class OtpMonitorService
{
    /** Сколько последних кодов показывать в админке. */
    public const LIMIT = 20;

    public function __construct(
        private readonly SmsCodeRepositoryInterface $smsCodes,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(?string $phone = null, int $limit = self::LIMIT): array
    {
        return $this->smsCodes->recent($limit, $phone)
            ->map(fn (SmsCode $code) => $this->present($code))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SmsCode $code): array
    {
        $expiresIn = $code->used_at === null && ! $code->isExpired()
            ? (int) now()->diffInSeconds($code->expires_at, absolute: true)
            : 0;

        return [
            'id'           => $code->id,
            'phone'        => $code->phone,
            'user_name'    => $code->user_name,
            'code'         => $code->code,
            'status'       => $this->status($code),
            'attempts'     => (int) $code->attempts,
            'max_attempts' => (int) config('sms.max_attempts'),
            'created_at'   => $code->created_at?->toIso8601String(),
            'expires_at'   => $code->expires_at->toIso8601String(),
            // Остаток жизни на момент ответа: фронт крутит из него обратный
            // отсчёт, не полагаясь на часы браузера.
            'expires_in'   => $expiresIn,
        ];
    }

    /** used — код уже введён (или сожжён попытками), expired — протух, active — годен. */
    private function status(SmsCode $code): string
    {
        if ($code->used_at !== null) {
            return 'used';
        }

        return $code->isExpired() ? 'expired' : 'active';
    }
}
