<?php

namespace App\Repositories\Interfaces;

use App\Models\SmsCode;
use Illuminate\Database\Eloquent\Collection;

interface SmsCodeRepositoryInterface
{
    public function create(string $phone, string $code, int $ttlSeconds): SmsCode;

    /**
     * Последние выданные коды — для ручного просмотра в админке, когда шлюз лежит.
     * $phone — необязательный фильтр по части номера.
     *
     * @return Collection<int, SmsCode>
     */
    public function recent(int $limit, ?string $phone = null): Collection;

    /** Последний неиспользованный и непросроченный код для номера. */
    public function findActive(string $phone): ?SmsCode;

    /** Последний созданный код для номера (для проверки cooldown-а повторной отправки). */
    public function findLatest(string $phone): ?SmsCode;

    public function markUsed(SmsCode $smsCode): void;

    public function incrementAttempts(SmsCode $smsCode): void;

    /** Погасить все активные коды номера (перед выдачей нового). */
    public function invalidateActive(string $phone): void;
}
