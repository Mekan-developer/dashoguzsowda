<?php

namespace App\Repositories\Interfaces;

use App\Models\TariffRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TariffRequestRepositoryInterface
{
    /** Незакрытая заявка пользователя — их не может быть больше одной. */
    public function pendingForUser(int $userId): ?TariffRequest;

    /** Последняя заявка пользователя в любом статусе — её показывает мобильный профиль. */
    public function latestForUser(int $userId): ?TariffRequest;

    public function create(User $user, int $tariffId, string $amount): TariffRequest;

    public function markProcessed(TariffRequest $request, string $status, int $adminId, ?string $comment = null): TariffRequest;

    /** Очередь заявок в админке: фильтры status и search (имя/телефон). */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Сколько заявок ждёт подтверждения — счётчик в меню админки. */
    public function countPending(): int;
}
