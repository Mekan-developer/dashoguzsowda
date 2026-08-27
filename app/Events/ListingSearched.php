<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Побочный эффект любого поиска объявлений — источник для GET /v1/search/popular. */
class ListingSearched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $query,
    ) {}
}
