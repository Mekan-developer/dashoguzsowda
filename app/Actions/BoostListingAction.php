<?php

namespace App\Actions;

use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Validation\ValidationException;

class BoostListingAction
{
    public function __construct(
        private readonly ListingService $listingService,
        private readonly CheckBoostLimitAction $checkBoostLimitAction,
    ) {}

    /**
     * @param  bool  $ignoreTariffLimit  поднятие из админки: лимит поднятий
     *                                   тарифа владельца на админа не действует,
     *                                   интервал между поднятиями — действует
     */
    public function execute(Listing $listing, bool $ignoreTariffLimit = false): void
    {
        if (! $this->listingService->canBoost($listing)) {
            throw ValidationException::withMessages([
                'listing' => [__('messages.boost_interval_not_passed')],
            ]);
        }

        if (! $ignoreTariffLimit) {
            $this->checkBoostLimitAction->execute($listing);
        }

        $this->listingService->boost($listing);
    }
}
