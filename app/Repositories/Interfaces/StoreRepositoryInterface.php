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
    public function popular(int $limit = 20, bool $withWholesale = false): Collection;

    /**
     * Публичный список для мобилки: только прошедшие модерацию и не погашенные.
     * Фильтры: region_id, city_id, district_id, category_id, type (retail|wholesale),
     * has_delivery, search.
     *
     * $withWholesale = false — зритель опта не видит: чисто оптовых магазинов
     * в выдаче нет, type=wholesale пуст.
     */
    public function paginatePublic(array $filters, int $perPage = 20, bool $withWholesale = false): LengthAwarePaginator;

    public function findByUser(int $userId): ?Store;

    /** Создаёт/обновляет магазин владельца (один магазин на пользователя). */
    public function upsertForUser(User $user, array $data): Store;

    /** Список для админки: поиск, фильтр по is_popular и по статусу модерации. */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Все is_popular=true по sort_order — для перестановки (move up/down). */
    public function orderedPopular(): Collection;

    public function update(Store $store, array $data): Store;

    /**
     * Какие способы оплаты принимает магазин. Набор заменяется целиком: форма
     * всегда присылает его полностью, а «минимум один» проверяет Form Request.
     *
     * @param  array<int, int>  $paymentMethodIds
     */
    public function syncPaymentMethods(Store $store, array $paymentMethodIds): void;

    /**
     * Категории магазина. Набор заменяется целиком.
     *
     * @param  array<int, int>  $categoryIds
     */
    public function syncCategories(Store $store, array $categoryIds): void;

    /** Сколько способов оплаты уже отмечено у магазина. */
    public function countPaymentMethods(Store $store): int;

    public function delete(Store $store): void;

    public function maxPopularSortOrder(): int;

    public function createPhoto(Store $store, array $attributes): StorePhoto;

    public function deletePhoto(StorePhoto $photo): void;

    public function maxPhotoOrder(Store $store): int;

    public function countPhotos(Store $store): int;

    /** Сколько магазинов ждёт модерации — счётчик в меню админки. */
    public function countPending(): int;

    /**
     * Гасит/зажигает витрину владельца по факту наличия тарифа с can_have_store.
     * Возвращает число затронутых магазинов.
     */
    public function setActiveForUser(int $userId, bool $isActive): int;

    /** Все магазины с владельцем и его тарифом — для команды stores:sync-visibility. */
    public function allWithOwners(): Collection;
}
