<?php

namespace App\Repositories\Interfaces;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SearchQueryLogRepositoryInterface
{
    /** Инкрементирует счётчик запроса (создаёт строку, если это первый поиск). */
    public function logQuery(string $query, string $queryKey): void;

    /** @return Collection<int,string> */
    public function topQueries(int $limit, ?CarbonInterface $since = null): Collection;
}
