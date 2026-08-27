<?php

namespace App\Listeners;

use App\Events\ListingSearched;
use App\Services\SearchPopularService;

class LogSearchQuery
{
    public function __construct(
        private readonly SearchPopularService $searchPopularService,
    ) {}

    public function handle(ListingSearched $event): void
    {
        $this->searchPopularService->logQuery($event->query);
    }
}
