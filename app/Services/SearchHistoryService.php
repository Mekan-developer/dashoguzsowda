<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\SearchRecentRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * История поиска авторизованного пользователя
 * (mobile_docs/CLAUDE_CODE_BACKEND_PLAN.md, задача 2).
 */
class SearchHistoryService
{
    /** Сколько запросов помнит сервер (по контракту с мобильным приложением). */
    public const MAX_ITEMS = 8;

    public function __construct(
        private readonly SearchRecentRepositoryInterface $searchRecents,
    ) {}

    /** @return Collection<int, string> */
    public function recent(User $user): Collection
    {
        return $this->searchRecents->listForUser($user, self::MAX_ITEMS);
    }

    /**
     * Добавляет запрос в историю и возвращает обновлённый список.
     *
     * Дедупликация регистронезависимая и без учёта крайних пробелов:
     * «iPhone», «iphone » и «IPHONE» — один и тот же запрос, наверху остаётся
     * последнее написание. Ключ считается здесь, а не unique-индексом по `query`,
     * потому что регистронезависимость индекса зависит от collation БД.
     *
     * @return Collection<int, string>
     */
    public function remember(User $user, string $query): Collection
    {
        $query = trim($query);

        $this->searchRecents->push($user, $query, mb_strtolower($query));
        $this->searchRecents->trim($user, self::MAX_ITEMS);

        return $this->recent($user);
    }

    public function clear(User $user): void
    {
        $this->searchRecents->clear($user);
    }
}
