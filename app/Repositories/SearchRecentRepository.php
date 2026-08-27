<?php

namespace App\Repositories;

use App\Models\SearchRecent;
use App\Models\User;
use App\Repositories\Interfaces\SearchRecentRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchRecentRepository implements SearchRecentRepositoryInterface
{
    public function listForUser(User $user, int $limit): Collection
    {
        return SearchRecent::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('query');
    }

    /**
     * Повторный запрос не создаёт вторую строку, а поднимается наверх.
     *
     * Старая строка удаляется и создаётся заново, а не обновляется touch():
     * timestamps хранятся с точностью до секунды, поэтому у двух запросов,
     * сделанных подряд, updated_at совпадает и порядок пришлось бы разрешать
     * по id — а он остался бы от первого добавления. Пересоздание делает
     * возрастающий id честным признаком свежести.
     */
    public function push(User $user, string $query, string $queryKey): void
    {
        DB::transaction(function () use ($user, $query, $queryKey) {
            SearchRecent::where('user_id', $user->id)
                ->where('query_key', $queryKey)
                ->delete();

            SearchRecent::create([
                'user_id'   => $user->id,
                'query'     => $query,
                'query_key' => $queryKey,
            ]);
        });
    }

    public function trim(User $user, int $limit): void
    {
        $keepIds = SearchRecent::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        SearchRecent::where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    public function clear(User $user): void
    {
        SearchRecent::where('user_id', $user->id)->delete();
    }
}
