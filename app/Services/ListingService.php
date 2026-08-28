<?php

namespace App\Services;

use App\Actions\AttachListingPhotosAction;
use App\Actions\CheckTariffLimitAction;
use App\Events\ListingSearched;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ListingService
{
    public function __construct(
        private readonly ListingRepositoryInterface $listingRepository,
        private readonly TariffService $tariffService,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly CheckTariffLimitAction $checkTariffLimitAction,
        private readonly AttachListingPhotosAction $attachListingPhotosAction,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->listingRepository->paginate($filters);
    }

    public function counts(): array
    {
        return [
            'pending'  => $this->listingRepository->countByStatus('pending'),
            'approved' => $this->listingRepository->countByStatus('approved'),
            'rejected' => $this->listingRepository->countByStatus('rejected'),
        ];
    }

    public function approve(Listing $listing): void
    {
        $this->listingRepository->update($listing, [
            'status'              => 'approved',
            'rejection_reason_id' => null,
        ]);
    }

    public function reject(Listing $listing, int $rejectionReasonId): void
    {
        $this->listingRepository->update($listing, [
            'status'              => 'rejected',
            'rejection_reason_id' => $rejectionReasonId,
        ]);
    }

    public function canBoost(Listing $listing): bool
    {
        if (! $listing->boosted_at) {
            return true;
        }

        // Интервал задаётся админом на странице «Настройки» (ключ в таблице settings).
        $intervalHours = (int) Setting::get('boost_interval_hours', 24);

        return $listing->boosted_at->addHours($intervalHours)->isPast();
    }

    public function boost(Listing $listing): void
    {
        $this->listingRepository->update($listing, [
            'is_boosted' => true,
            'boosted_at' => now(),
        ]);
    }

    /**
     * Правка модератором из админки: меняет только текст и цену и, в отличие
     * от правки автором, НЕ возвращает объявление на повторную модерацию —
     * модератор его как раз и смотрит.
     */
    public function updateFromAdmin(Listing $listing, array $data): Listing
    {
        return $this->listingRepository->update($listing, $data);
    }

    public function delete(Listing $listing): void
    {
        $this->listingRepository->delete($listing);
    }

    /**
     * Публичная выдача для мобильного приложения.
     * Фильтр по категории включает всё её поддерево (ТЗ 5.8).
     */
    public function searchForApi(array $filters, int $perPage = 20, ?User $viewer = null): LengthAwarePaginator
    {
        if (! empty($filters['search'])) {
            ListingSearched::dispatch(trim($filters['search']));
        }

        if (! empty($filters['category_id'])) {
            $category = $this->categoryRepository->find((int) $filters['category_id']);
            $filters['category_ids'] = [
                $category->id,
                ...$this->categoryRepository->descendants($category)->pluck('id')->all(),
            ];
        }

        return $this->listingRepository->paginateForApi($filters, $perPage, $viewer?->id);
    }

    /** Проставляет is_favorite для авторизованного зрителя карточки */
    public function loadFavoriteFlag(Listing $listing, ?User $viewer): void
    {
        $this->listingRepository->loadFavoriteFlag($listing, $viewer?->id);
    }

    public function myListings(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->listingRepository->paginateByUser($user->id, $filters, $perPage);
    }

    public function createFromApi(User $user, array $data): Listing
    {
        $this->checkTariffLimitAction->execute($user);

        $photos = $data['photos'];
        unset($data['photos']);

        $listing = $this->listingRepository->create([
            ...$data,
            ...$this->storeAttributes($user),
            'user_id' => $user->id,
            'phone'   => $data['phone'] ?? $user->phone,
            'status'  => 'pending',
        ]);

        $this->attachListingPhotosAction->execute($listing, $photos);

        return $this->listingRepository->find($listing->id);
    }

    /**
     * Редактирование владельцем: любое изменение возвращает объявление
     * на повторную модерацию (rejected → pending по ТЗ 5.6).
     */
    public function updateFromApi(Listing $listing, array $data): Listing
    {
        $newPhotos      = $data['photos'] ?? [];
        $removeMediaIds = $data['remove_media_ids'] ?? [];
        unset($data['photos'], $data['remove_media_ids']);

        foreach ($listing->media as $media) {
            if (in_array($media->id, $removeMediaIds)) {
                $this->deleteMediaFiles($media);
                $this->listingRepository->deleteMedia($media);
            }
        }

        $this->listingRepository->update($listing, [
            ...$data,
            ...$this->storeAttributes($listing->user),
            'status'              => 'pending',
            'rejection_reason_id' => null,
        ]);

        if ($newPhotos !== []) {
            $this->attachListingPhotosAction->execute($listing, $newPhotos);
        }

        return $this->listingRepository->find($listing->id);
    }

    /**
     * Объявление автора, у которого есть магазин, — это товар магазина: оно
     * привязывается к нему автоматически и берёт его адрес (решение команды:
     * адрес указывает вручную только тот, у кого магазина нет).
     *
     * Магазин на модерации или погашенный тоже проставляется: товар всё равно
     * его, а из витрины он не покажется, пока не одобрят.
     */
    private function storeAttributes(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $store = $this->storeRepository->findByUser($user->id);

        if (! $store) {
            return [];
        }

        return [
            'store_id'    => $store->id,
            'region_id'   => $store->region_id ?? $user->region_id,
            'city_id'     => $store->city_id ?? $user->city_id,
            'district_id' => $store->district_id,
        ];
    }

    public function deleteFromApi(Listing $listing): void
    {
        $this->listingRepository->delete($listing);
        Storage::disk('public')->deleteDirectory("listings/{$listing->id}");
    }

    /** Счётчик просмотров: каждый заход, кроме владельца (ТЗ 5.4) */
    public function registerView(Listing $listing, ?User $viewer): void
    {
        if ($viewer && $viewer->id === $listing->user_id) {
            return;
        }

        $this->listingRepository->incrementViews($listing);
    }

    private function deleteMediaFiles(ListingMedia $media): void
    {
        Storage::disk('public')->delete(array_filter([
            $media->path,
            $media->medium_path,
            $media->thumb_path,
        ]));
    }
}
