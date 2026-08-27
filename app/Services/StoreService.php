<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StorePhoto;
use App\Models\User;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class StoreService
{
    /** Логотип: квадрат, как аватар */
    private const LOGO_ASPECT    = 1.0;
    private const LOGO_MAX_WIDTH = 400;
    private const LOGO_MAX_BYTES = 80 * 1024;

    /** Галерея: без кропа, шире — под карточки мобильного приложения */
    private const PHOTO_MAX_WIDTH = 1080;
    private const PHOTO_MAX_BYTES = 150 * 1024;

    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly ListingRepositoryInterface $listingRepository,
        private readonly ImageConversionService $imageConversion,
    ) {}

    /** GET /v1/stores/popular */
    public function popular(int $limit = 20): Collection
    {
        return $this->storeRepository->popular($limit);
    }

    /**
     * GET /v1/stores/{id}/listings — переиспользует общую выдачу объявлений
     * (те же фильтры/сортировка/approved-only, что и /v1/listings).
     */
    public function listingsForStore(Store $store, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->listingRepository->paginateForApi([...$filters, 'user_id' => $store->user_id], $perPage);
    }

    /** PUT /v1/profile — только текстовые поля, вызывается из UpdateUserStoreAction. */
    public function updateOwnStore(User $user, array $data): Store
    {
        return $this->storeRepository->upsertForUser(
            $user,
            Arr::only($data, ['name', 'description', 'phone', 'address', 'category_id']),
        );
    }

    /** Плоский массив для UserResource — null, если тариф не даёт право или магазина ещё нет. */
    public function forProfile(User $user, bool $canHaveStore): ?array
    {
        if (! $canHaveStore) {
            return null;
        }

        $store = $this->storeRepository->findByUser($user->id);

        if (! $store) {
            return null;
        }

        return [
            'name'             => $store->name,
            'description'      => $store->description,
            'phone'            => $store->phone,
            'address'          => $store->address,
            'category_id'      => $store->category_id,
            'category_name_tk' => $store->category?->name_tk,
            'category_name_ru' => $store->category?->name_ru,
        ];
    }

    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->storeRepository->paginate($filters);
    }

    /**
     * Правка из админки: текстовые поля + опционально новый логотип и/или
     * добавляемые фото галереи (существующие фото не трогает — для удаления
     * одного фото есть отдельный removePhoto()).
     *
     * @param UploadedFile[] $newPhotos
     */
    public function update(Store $store, array $data, ?UploadedFile $logo = null, array $crop = [], array $newPhotos = []): Store
    {
        if ($logo) {
            $data['logo'] = $this->storeLogo($store, $logo, $crop);
        }

        $store = $this->storeRepository->update($store, $data);

        if ($newPhotos !== []) {
            $order = $this->storeRepository->maxPhotoOrder($store);

            foreach ($newPhotos as $photo) {
                $order++;
                $path = $this->imageConversion->toWebp(
                    $photo,
                    "stores/{$store->id}/photos",
                    maxWidth: self::PHOTO_MAX_WIDTH,
                    maxBytes: self::PHOTO_MAX_BYTES,
                );
                $this->storeRepository->createPhoto($store, ['path' => $path, 'order' => $order]);
            }
        }

        return $store->fresh(['photos', 'category', 'user']);
    }

    public function togglePopular(Store $store): Store
    {
        if ($store->is_popular) {
            return $this->storeRepository->update($store, ['is_popular' => false, 'sort_order' => null]);
        }

        return $this->storeRepository->update($store, [
            'is_popular' => true,
            'sort_order' => $this->storeRepository->maxPopularSortOrder() + 1,
        ]);
    }

    /** Перестановка внутри «популярных» — тот же паттерн, что BannerService::move(). */
    public function move(Store $store, string $direction): void
    {
        $ordered = $this->storeRepository->orderedPopular()->values();
        $index = $ordered->search(fn ($s) => $s->id === $store->id);
        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $ordered->has($targetIndex)) {
            return;
        }

        $target = $ordered[$targetIndex];
        $order = $store->sort_order;

        $this->storeRepository->update($store, ['sort_order' => $target->sort_order]);
        $this->storeRepository->update($target, ['sort_order' => $order]);
    }

    public function delete(Store $store): void
    {
        Storage::disk('public')->deleteDirectory("stores/{$store->id}");
        $this->storeRepository->delete($store);
    }

    public function removePhoto(StorePhoto $photo): void
    {
        Storage::disk('public')->delete($photo->path);
        $this->storeRepository->deletePhoto($photo);
    }

    private function storeLogo(Store $store, UploadedFile $logo, array $crop): string
    {
        if ($store->logo) {
            Storage::disk('public')->delete($store->logo);
        }

        return $this->imageConversion->toWebp(
            $logo,
            "stores/{$store->id}",
            aspect: self::LOGO_ASPECT,
            cropX: (float) ($crop['crop_x'] ?? 50),
            cropY: (float) ($crop['crop_y'] ?? 50),
            maxWidth: self::LOGO_MAX_WIDTH,
            maxBytes: self::LOGO_MAX_BYTES,
        );
    }
}
