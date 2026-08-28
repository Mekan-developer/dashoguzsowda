<?php

namespace App\Actions;

use App\Events\StoreApproved;
use App\Models\Store;
use App\Services\StoreService;

class ApproveStoreAction
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    public function execute(Store $store): void
    {
        $this->storeService->approve($store);

        event(new StoreApproved($store));
    }
}
