<?php

namespace App\Repositories\Interfaces;

use App\Services\Sms\SmsCode;

interface SmsCodeRepositoryInterface
{
    public function create(string $phone, string $code, int $ttlSeconds): SmsCode;

    /**
     * Последние выданные коды — для ручного просмотра в админке, когда шлюз лежит.
     * Протухшие в выдачу не попадают: хранилище удаляет их по TTL.
     * $phone — необязательный фильтр по части номера.
     *
     * @return array<int, SmsCode>
     */
    public function recent(int $limit, ?string $phone = null): array;

    /** Последний неиспользованный и непросроченный код для номера. */
    public function findActive(string $phone): ?SmsCode;

    /** Последний созданный код для номера независимо от статуса. */
    public function findLatest(string $phone): ?SmsCode;

    /** Запретить повторную отправку на номер на $seconds секунд. */
    public function startResendCooldown(string $phone, int $seconds): void;

    /** Сколько секунд осталось до разрешённой повторной отправки; 0 — можно слать. */
    public function resendCooldownRemaining(string $phone): int;

    public function markUsed(SmsCode $smsCode): void;

    public function incrementAttempts(SmsCode $smsCode): void;

    /** Погасить все активные коды номера (перед выдачей нового). */
    public function invalidateActive(string $phone): void;
}
