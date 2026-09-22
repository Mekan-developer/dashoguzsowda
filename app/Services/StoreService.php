<?php

namespace App\Services;

use App\Actions\CheckStoreTariffAction;
use App\Models\Store;
use App\Models\StorePhoto;
use App\Models\User;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethods,
        private readonly ImageConversionService $imageConversion,
        private readonly CheckStoreTariffAction $checkStoreTariff,
    ) {}

    /**
     * Поля, правка которых возвращает магазин на модерацию: это всё, что видит
     * покупатель в витрине. Телефон, доставка и вид торговли меняются свободно —
     * иначе владелец боялся бы трогать рабочие настройки.
     */
    private const MODERATED_FIELDS = ['name', 'description', 'address'];

    /** GET /v1/stores/popular — чисто оптовые только для тех, кто видит опт. */
    public function popular(int $limit = 20, ?User $viewer = null): Collection
    {
        return $this->storeRepository->popular($limit, $viewer?->seesWholesale() ?? false);
    }

    /** GET /v1/stores — публичный список с фильтрами (регион/город/тип/доставка/поиск). */
    public function publicList(array $filters, int $perPage = 20, ?User $viewer = null): LengthAwarePaginator
    {
        return $this->storeRepository->paginatePublic($filters, $perPage, $viewer?->seesWholesale() ?? false);
    }

    /**
     * GET /v1/stores/{id}/listings — переиспользует общую выдачу объявлений
     * (те же фильтры/сортировка/approved-only, что и /v1/listings).
     * Фильтр category_id включает поддерево, как в ListingService::searchForApi.
     * Оптовые позиции — владельцу витрины и тем, кто видит опт.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listingsForStore(Store $store, array $filters, int $perPage = 20, ?User $viewer = null): LengthAwarePaginator
    {
        if (! empty($filters['category_id'])) {
            $category = $this->categoryRepository->find((int) $filters['category_id']);
            $filters['category_ids'] = [
                $category->id,
                ...$this->categoryRepository->descendants($category)->pluck('id')->all(),
            ];
        }

        return $this->listingRepository->paginateForApi(
            [...$filters, 'store_id' => $store->id],
            $perPage,
            $viewer?->id,
            $store->showsWholesaleTo($viewer),
        );
    }

    public function findByUser(User $user): ?Store
    {
        return $this->storeRepository->findByUser($user->id);
    }

    /**
     * Создание/правка своего магазина из мобильного приложения.
     *
     * Новый магазин и правка витринных полей уходят в статус pending: логотип,
     * название и адрес видны всем покупателям, значит проходят модерацию, как
     * остальной UGC. При отправке на перемодерацию причина прошлого отказа
     * сбрасывается, чтобы в мобилке не висел устаревший текст.
     *
     * @param UploadedFile[] $photos
     */
    public function saveOwnStore(User $user, array $data, ?UploadedFile $logo = null, array $crop = [], array $photos = []): Store
    {
        $existing = $this->storeRepository->findByUser($user->id);

        $attributes = Arr::only($data, [
            'name', 'description', 'phone', 'address', 'category_id',
            'region_id', 'city_id', 'district_id',
            'sells_retail', 'sells_wholesale', 'has_delivery',
        ]);

        if (array_key_exists('category_ids', $data)) {
            $categoryIds = array_values(array_unique(array_map('intval', $data['category_ids'] ?? [])));
            $attributes['category_id'] = $categoryIds[0] ?? null;
        }

        if ($this->needsModeration($existing, $attributes, $logo)) {
            $attributes['status'] = 'pending';
            $attributes['rejection_reason_id'] = null;
        }

        // Тариф уже проверен CheckStoreTariffAction — витрина зажигается сразу,
        // видимой она станет только после одобрения модератором.
        $attributes['is_active'] = true;

        $store = $this->storeRepository->upsertForUser($user, $attributes);

        if (array_key_exists('category_ids', $data)) {
            $this->storeRepository->syncCategories(
                $store,
                array_values(array_unique(array_map('intval', $data['category_ids'] ?? []))),
            );
        }

        if ($logo) {
            $store = $this->storeRepository->update($store, ['logo' => $this->storeLogo($store, $logo, $crop)]);
        }

        $this->syncPaymentMethods($store, $data, isNew: $existing === null);

        if ($photos !== []) {
            $this->addPhotos($store, $photos);
        }

        return $store->fresh(['photos', 'category', 'categories', 'region', 'city', 'district', 'rejectionReason', 'paymentMethods']);
    }

    /**
     * Набор способов оплаты магазина. Поля нет в запросе — набор не трогаем:
     * для правки это «не менял», для нового магазина (и для того, у кого набор
     * пуст) подставляем способ по умолчанию, иначе покупателю при оформлении
     * не из чего выбрать, а форма правки не даст сохранить магазин.
     */
    private function syncPaymentMethods(Store $store, array $data, bool $isNew): void
    {
        if (array_key_exists('payment_method_ids', $data)) {
            $this->storeRepository->syncPaymentMethods($store, array_map('intval', $data['payment_method_ids']));

            return;
        }

        if (! $isNew && $this->storeRepository->countPaymentMethods($store) > 0) {
            return;
        }

        $default = $this->paymentMethods->defaultId();

        $this->storeRepository->syncPaymentMethods($store, $default ? [$default] : []);
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
            'id'               => $store->id,
            'name'             => $store->name,
            'description'      => $store->description,
            'phone'            => $store->phone,
            'address'          => $store->address,
            'region_id'        => $store->region_id,
            'city_id'          => $store->city_id,
            'district_id'      => $store->district_id,
            'sells_retail'     => $store->sells_retail,
            'sells_wholesale'  => $store->sells_wholesale,
            'has_delivery'     => $store->has_delivery,
            // Ставка комиссии платформы — владелец её видит, но не меняет
            'commission_percent' => (float) $store->commission_percent,
            // Чем у него можно расплатиться: набор он собирает сам
            'payment_methods'  => $store->paymentMethods->map(fn ($method) => [
                'id'      => $method->id,
                'name_tk' => $method->name_tk,
                'name_ru' => $method->name_ru,
            ])->values(),
            'status'           => $store->status,
            'is_active'        => $store->is_active,
            'rejection_reason' => $store->status === 'rejected' && $store->rejectionReason ? [
                'id'      => $store->rejectionReason->id,
                'name_tk' => $store->rejectionReason->name_tk,
                'name_ru' => $store->rejectionReason->name_ru,
            ] : null,
            'logo_url'         => $store->logo ? Storage::disk('public')->url($store->logo) : null,
            'category_id'      => $store->category_id,
            'category_ids'     => $store->categories->pluck('id')->values()->all(),
            'categories'       => $store->categories->map(fn ($category) => [
                'id'      => $category->id,
                'name_tk' => $category->name_tk,
                'name_ru' => $category->name_ru,
            ])->values()->all(),
            'category_name_tk' => $store->category?->name_tk,
            'category_name_ru' => $store->category?->name_ru,
        ];
    }

    // ---------------------------------------------------------------
    // Модерация
    // ---------------------------------------------------------------

    public function approve(Store $store): Store
    {
        return $this->storeRepository->update($store, [
            'status'              => 'approved',
            'rejection_reason_id' => null,
        ]);
    }

    public function reject(Store $store, int $rejectionReasonId): Store
    {
        return $this->storeRepository->update($store, [
            'status'              => 'rejected',
            'rejection_reason_id' => $rejectionReasonId,
        ]);
    }

    /**
     * Приводит видимость витрины в соответствие с тарифом владельца: тариф с
     * can_have_store кончился — магазин гаснет, но не удаляется, а его товары
     * остаются обычными объявлениями. Вернулся тариф — витрина зажигается
     * с тем же статусом модерации, что был.
     */
    public function syncVisibility(User $user): void
    {
        $canHaveStore = (bool) $user->activeTariff()?->canHaveStore();

        $this->storeRepository->setActiveForUser($user->id, $canHaveStore);
    }

    /**
     * То же самое для всех магазинов сразу — тариф истекает по времени, без
     * запроса от пользователя, поэтому раз в сутки прогоняется по расписанию
     * (stores:sync-visibility).
     *
     * @return int сколько магазинов сменили видимость
     */
    public function syncAllVisibility(): int
    {
        $changed = 0;

        foreach ($this->storeRepository->allWithOwners() as $store) {
            $canHaveStore = (bool) $store->user?->activeTariff()?->canHaveStore();

            if ((bool) $store->is_active === $canHaveStore) {
                continue;
            }

            $this->storeRepository->setActiveForUser($store->user_id, $canHaveStore);
            $changed++;
        }

        return $changed;
    }

    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->storeRepository->paginate($filters);
    }

    /** Счётчик очереди модерации для вкладок админки. */
    public function moderationCounts(): array
    {
        return ['pending' => $this->storeRepository->countPending()];
    }

    /**
     * Создание магазина из админки от имени выбранного пользователя.
     * Сразу approved — модерация не нужна. Тариф с can_have_store обязателен.
     *
     * @param  UploadedFile[]  $photos
     */
    public function createFromAdmin(User $user, array $data, ?UploadedFile $logo = null, array $crop = [], array $photos = []): Store
    {
        $this->checkStoreTariff->execute($user);

        if ($this->storeRepository->findByUser($user->id)) {
            throw ValidationException::withMessages([
                'user_id' => __('messages.store_already_exists'),
            ]);
        }

        $attributes = Arr::only($data, [
            'name', 'description', 'phone', 'address', 'category_id',
            'region_id', 'city_id', 'district_id',
            'sells_retail', 'sells_wholesale', 'has_delivery', 'commission_percent',
        ]);

        $attributes['status'] = 'approved';
        $attributes['is_active'] = true;

        $store = $this->storeRepository->upsertForUser($user, $attributes);

        if (! empty($attributes['category_id'])) {
            $this->storeRepository->syncCategories($store, [(int) $attributes['category_id']]);
        }

        if ($logo) {
            $store = $this->storeRepository->update($store, ['logo' => $this->storeLogo($store, $logo, $crop)]);
        }

        $this->syncPaymentMethods($store, $data, isNew: true);

        if ($photos !== []) {
            $this->addPhotos($store, $photos);
        }

        return $store->fresh(['photos', 'category', 'categories', 'user', 'region', 'city', 'district', 'paymentMethods']);
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

        $paymentMethodIds = $data['payment_method_ids'] ?? null;
        unset($data['payment_method_ids']);

        $store = $this->storeRepository->update($store, $data);

        if (array_key_exists('category_id', $data)) {
            $this->storeRepository->syncCategories(
                $store,
                $data['category_id'] !== null ? [(int) $data['category_id']] : [],
            );
        }

        if ($paymentMethodIds !== null) {
            $this->storeRepository->syncPaymentMethods($store, array_map('intval', $paymentMethodIds));
        }

        if ($newPhotos !== []) {
            $this->addPhotos($store, $newPhotos);
        }

        return $store->fresh(['photos', 'category', 'categories', 'user', 'region', 'city', 'district', 'paymentMethods']);
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

    public function countPhotos(Store $store): int
    {
        return $this->storeRepository->countPhotos($store);
    }

    /** @param UploadedFile[] $photos */
    private function addPhotos(Store $store, array $photos): void
    {
        $order = $this->storeRepository->maxPhotoOrder($store);

        foreach ($photos as $photo) {
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

    /**
     * Новый магазин — всегда на модерацию. Существующий — только если реально
     * изменилось витринное поле или логотип: перекладывать магазин в pending
     * из-за сохранения формы без правок было бы наказанием ни за что.
     */
    private function needsModeration(?Store $existing, array $attributes, ?UploadedFile $logo): bool
    {
        if (! $existing) {
            return true;
        }

        if ($logo) {
            return true;
        }

        foreach (self::MODERATED_FIELDS as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== $existing->{$field}) {
                return true;
            }
        }

        return false;
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
