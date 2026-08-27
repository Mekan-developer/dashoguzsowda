<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface SearchRecentRepositoryInterface
{
    /** @return Collection<int, string> запросы пользователя, новые сверху */
    public function listForUser(User $user, int $limit): Collection;

    /** Добавляет запрос, поднимая существующий наверх. */
    public function push(User $user, string $query, string $queryKey): void;

    /** Оставляет только $limit самых свежих записей пользователя. */
    public function trim(User $user, int $limit): void;

    public function clear(User $user): void;
}
