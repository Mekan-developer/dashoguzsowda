<?php

namespace App\Actions;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Http\UploadedFile;

/**
 * Создание/правка своего магазина из мобильного приложения — и через
 * PUT /v1/profile (поле `store`), и через отдельные /v1/my/store.
 *
 * @param UploadedFile[] $photos
 */
class UpdateUserStoreAction
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    public function execute(User $user, array $data, ?UploadedFile $logo = null, array $crop = [], array $photos = []): Store
    {
        return $this->storeService->saveOwnStore($user, $data, $logo, $crop, $photos);
    }
}
