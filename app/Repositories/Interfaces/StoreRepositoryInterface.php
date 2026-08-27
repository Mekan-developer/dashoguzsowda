<?php

namespace App\Repositories\Interfaces;

use App\Models\Store;
use App\Models\StorePhoto;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StoreRepositoryInterface
{
    /** is_popular=true, отсортированы по sort_order — для GET /v1/stores/popular. */
    public function popular(int $limit = 20): Collection;

    public function findByUser(int $userId): ?Store;

    /** Создаёт/обновляет магазин владельца (один магазин на пользователя). */
    public function upsertForUser(User $user, array $data): Store;

    /** Список для админки: поиск/фильтр по is_popular. */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Все is_popular=true по sort_order — для перестановки (move up/down). */
    public function orderedPopular(): Collection;

    public function update(Store $store, array $data): Store;

    public function delete(Store $store): void;

    public function maxPopularSortOrder(): int;

    public function createPhoto(Store $store, array $attributes): StorePhoto;

    public function deletePhoto(StorePhoto $photo): void;

    public function maxPhotoOrder(Store $store): int;

    public function countPhotos(Store $store): int;
}
