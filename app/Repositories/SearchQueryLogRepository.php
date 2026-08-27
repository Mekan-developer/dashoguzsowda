<?php

namespace App\Repositories;

use App\Models\SearchQueryLog;
use App\Repositories\Interfaces\SearchQueryLogRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchQueryLogRepository implements SearchQueryLogRepositoryInterface
{
    public function logQuery(string $query, string $queryKey): void
    {
        DB::transaction(function () use ($query, $queryKey) {
            $log = SearchQueryLog::where('query_key', $queryKey)->lockForUpdate()->first();

            if ($log) {
                $log->increment('hits');
                $log->update(['query' => $query, 'last_searched_at' => now()]);

                return;
            }

            SearchQueryLog::create([
                'query'            => $query,
                'query_key'        => $queryKey,
                'hits'             => 1,
                'last_searched_at' => now(),
            ]);
        });
    }

    public function topQueries(int $limit, ?CarbonInterface $since = null): Collection
    {
        return SearchQueryLog::when($since, fn ($q) => $q->where('last_searched_at', '>=', $since))
            ->orderByDesc('hits')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->pluck('query');
    }
}
