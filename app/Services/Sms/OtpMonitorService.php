<?php

namespace App\Services\Sms;

use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;

/**
 * Ручной просмотр выданных OTP-кодов (Настройки → Мониторинг OTP).
 *
 * Нужен ровно на случай, когда телефон-отправитель отвалился: код в хранилище
 * уже лежит, а до пользователя не дошёл — админ читает его глазами и диктует.
 * Никаких текстов здесь нет: наружу уходят ключи статусов, подписи собирает
 * фронт через vue-i18n.
 */
class OtpMonitorService
{
    /** Сколько последних кодов показывать в админке. */
    public const LIMIT = 20;

    public function __construct(
        private readonly SmsCodeRepositoryInterface $smsCodes,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(?string $phone = null, int $limit = self::LIMIT): array
    {
        $codes = $this->smsCodes->recent($limit, $phone);

        // Имена владельцев — одним запросом: связи между кодом и пользователем
        // нет, номер может принадлежать ещё не зарегистрированному.
        $names = $this->users->namesByPhones(array_map(fn (SmsCode $code) => $code->phone, $codes));

        return array_map(fn (SmsCode $code) => $this->present($code, $names[$code->phone] ?? null), $codes);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SmsCode $code, ?string $userName): array
    {
        return [
            'id'           => $code->id,
            'phone'        => $code->phone,
            'user_name'    => $userName,
            'code'         => $code->code,
            // Протухших статусов тут не бывает: хранилище удаляет код по TTL,
            // а «Истёк» между двумя опросами дорисовывает фронт по таймеру.
            'status'       => $code->isUsed() ? 'used' : 'active',
            'attempts'     => $code->attempts,
            'max_attempts' => (int) config('sms.max_attempts'),
            'created_at'   => $code->createdAt->toIso8601String(),
            'expires_at'   => $code->expiresAt->toIso8601String(),
            // Остаток жизни на момент ответа: фронт крутит из него обратный
            // отсчёт, не полагаясь на часы браузера.
            'expires_in'   => $code->secondsLeft(),
        ];
    }
}
