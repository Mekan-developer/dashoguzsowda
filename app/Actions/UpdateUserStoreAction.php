<?php

namespace App\Actions;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;

class UpdateUserStoreAction
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    public function execute(User $user, array $data): Store
    {
        return $this->storeService->updateOwnStore($user, $data);
    }
}
