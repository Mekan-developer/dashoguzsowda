<?php

namespace App\Services;

use App\Repositories\Interfaces\SearchQueryLogRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Популярные поисковые запросы по всему сайту (mobile_docs/BACKEND_API.md §4).
 * Источник — search_query_logs, счётчик которой инкрементится событием
 * ListingSearched при каждом поиске объявлений (в т.ч. гостями) — в отличие
 * от search_recents, это персональная история только авторизованных.
 */
class SearchPopularService
{
    private const CACHE_KEY   = 'search.popular.v1';
    private const CACHE_TTL   = 300;
    private const LIMIT       = 10;
    private const WINDOW_DAYS = 30;

    public function __construct(
        private readonly SearchQueryLogRepositoryInterface $searchQueryLogs,
    ) {}

    public function logQuery(string $rawQuery): void
    {
        $query = trim($rawQuery);

        if ($query === '') {
            return;
        }

        $this->searchQueryLogs->logQuery($query, mb_strtolower($query));
    }

    /** @return Collection<int, string> */
    public function popular(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => $this->searchQueryLogs->topQueries(self::LIMIT, now()->subDays(self::WINDOW_DAYS)),
        );
    }
}
