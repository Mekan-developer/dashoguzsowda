<?php

namespace App\Observers;

use App\Events\ListingSubmitted;
use App\Models\Listing;

class ListingObserver
{
    public function creating(Listing $listing): void
    {
        if (empty($listing->status)) {
            $listing->status = 'pending';
        }
    }

    // Объявление админа создаётся сразу approved — сообщать некому
    public function created(Listing $listing): void
    {
        if ($listing->status === 'pending') {
            ListingSubmitted::dispatch($listing);
        }
    }
}
