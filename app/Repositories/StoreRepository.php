<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\StorePhoto;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StoreRepository implements StoreRepositoryInterface
{
    public function popular(int $limit = 20, bool $withWholesale = false): Collection
    {
        return Store::with('photos', 'category', 'region', 'city', 'district', 'paymentMethods')
            ->where('is_popular', true)
            ->tap(fn ($q) => $this->publicScope($q, $withWholesale))
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    public function paginatePublic(array $filters, int $perPage = 20, bool $withWholesale = false): LengthAwarePaginator
    {
        return Store::with('photos', 'category', 'region', 'city', 'district', 'paymentMethods')
            ->tap(fn ($q) => $this->publicScope($q, $withWholesale))
            ->when($filters['region_id'] ?? null, fn ($q, $id) => $q->where('region_id', $id))
            ->when($filters['city_id'] ?? null, fn ($q, $id) => $q->where('city_id', $id))
            ->when($filters['district_id'] ?? null, fn ($q, $id) => $q->where('district_id', $id))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            // type=retail показывает и «оптом и в розницу» — флаги независимы
            ->when(($filters['type'] ?? null) === 'retail', fn ($q) => $q->where('sells_retail', true))
            // Оптовиков клиенту не показываем вовсе — «только опт» для него пуст
            ->when(($filters['type'] ?? null) === 'wholesale', fn ($q) => $withWholesale
                ? $q->where('sells_wholesale', true)
                : $q->whereRaw('1 = 0'))
            ->when(isset($filters['has_delivery']), fn ($q) => $q->where('has_delivery', (bool) $filters['has_delivery']))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%'))
            // Курируемые из админки идут первыми, остальные — свежими
            ->orderByDesc('is_popular')
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUser(int $userId): ?Store
    {
        return Store::with('category', 'region', 'city', 'district', 'photos', 'rejectionReason', 'paymentMethods')
            ->where('user_id', $userId)
            ->first();
    }

    public function upsertForUser(User $user, array $data): Store
    {
        return Store::updateOrCreate(['user_id' => $user->id], $data);
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Store::with('user', 'category', 'photos', 'region', 'city', 'district', 'rejectionReason', 'paymentMethods')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
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

    public function syncPaymentMethods(Store $store, array $paymentMethodIds): void
    {
        $store->paymentMethods()->sync($paymentMethodIds);
    }

    public function countPaymentMethods(Store $store): int
    {
        return $store->paymentMethods()->count();
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

    public function countPending(): int
    {
        return Store::where('status', 'pending')->count();
    }

    public function setActiveForUser(int $userId, bool $isActive): int
    {
        return Store::where('user_id', $userId)->update(['is_active' => $isActive]);
    }

    public function allWithOwners(): Collection
    {
        return Store::with('user.tariff')->get()->toBase();
    }

    /**
     * Единственное определение «магазин виден публично» — не размазывать по вызовам.
     * Чисто оптовый магазин видят только те, кто видит опт (Store::isVisibleTo).
     */
    private function publicScope(Builder $query, bool $withWholesale): void
    {
        $query->where('status', 'approved')->where('is_active', true)
            ->unless($withWholesale, fn ($q) => $q->where('sells_retail', true));
    }
}
