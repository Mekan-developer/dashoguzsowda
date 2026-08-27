<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\StorePhoto;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StoreRepository implements StoreRepositoryInterface
{
    public function popular(int $limit = 20): Collection
    {
        return Store::with('photos', 'category')
            ->where('is_popular', true)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    public function findByUser(int $userId): ?Store
    {
        return Store::with('category')->where('user_id', $userId)->first();
    }

    public function upsertForUser(User $user, array $data): Store
    {
        return Store::updateOrCreate(['user_id' => $user->id], $data);
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Store::with('user', 'category', 'photos')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when(array_key_exists('is_popular', $filters) && $filters['is_popular'] !== null && $filters['is_popular'] !== '',
                fn ($q) => $q->where('is_popular', (bool) $filters['is_popular']))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderedPopular(): Collection
    {
        return Store::where('is_popular', true)->orderBy('sort_order')->get();
    }

    public function update(Store $store, array $data): Store
    {
        $store->update($data);

        return $store->fresh();
    }

    public function delete(Store $store): void
    {
        $store->delete();
    }

    public function maxPopularSortOrder(): int
    {
        return (int) Store::where('is_popular', true)->max('sort_order');
    }

    public function createPhoto(Store $store, array $attributes): StorePhoto
    {
        return $store->photos()->create($attributes);
    }

    public function deletePhoto(StorePhoto $photo): void
    {
        $photo->delete();
    }

    public function maxPhotoOrder(Store $store): int
    {
        return (int) $store->photos()->max('order');
    }

    public function countPhotos(Store $store): int
    {
        return $store->photos()->count();
    }
}
