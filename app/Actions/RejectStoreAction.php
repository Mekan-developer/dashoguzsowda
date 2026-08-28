<?php

namespace App\Actions;

use App\Events\StoreRejected;
use App\Models\Store;
use App\Services\StoreService;

class RejectStoreAction
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    public function execute(Store $store, int $rejectionReasonId): void
    {
        $store = $this->storeService->reject($store, $rejectionReasonId);

        event(new StoreRejected($store));
    }
}
