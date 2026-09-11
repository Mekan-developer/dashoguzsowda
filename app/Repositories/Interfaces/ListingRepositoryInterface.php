<?php

namespace App\Repositories\Interfaces;

use App\Models\Listing;
use App\Models\ListingMedia;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ListingRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;
    public function find(int $id): Listing;
    public function create(array $data): Listing;
    public function update(Listing $listing, array $data): Listing;
    public function delete(Listing $listing): void;
    public function countAll(): int;
    public function countByStatus(string $status): int;
    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $to): int;
    /** Публикации по дням для графика на дашборде. */
    public function countByDaySince(CarbonInterface $since): \Illuminate\Support\Collection;
    /** Последние объявления для ленты на дашборде. */
    public function recent(int $limit = 6): \Illuminate\Database\Eloquent\Collection;
    public function countActiveByUser(int $userId): int;

    /**
     * Публичная выдача для мобильного приложения: только approved + фильтры/сортировки.
     * $withWholesale = false — зритель опта не видит: оптовые предложения
     * отсекаются (кроме его собственных объявлений).
     */
    public function paginateForApi(array $filters, int $perPage = 20, ?int $viewerId = null, bool $withWholesale = false): LengthAwarePaginator;

    /** Проставляет атрибут is_favorite (в избранном ли у зрителя) на модель */
    public function loadFavoriteFlag(Listing $listing, ?int $viewerId): void;
    public function loadRatingAggregates(Listing $listing): void;

    /** Объявления пользователя (любой статус) для экрана «Мои объявления» */
    public function paginateByUser(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator;

    /** Занятая квота тарифа: объявления в перечисленных статусах */
    public function countByUserAndStatuses(int $userId, array $statuses): int;

    /** Занятая квота тарифа: сколько объявлений пользователя сейчас поднято */
    public function countBoostedByUser(int $userId): int;

    /** Сумма просмотров всех объявлений пользователя — stats.views_count в профиле мобилки */
    public function sumViewsByUser(int $userId): int;

    public function incrementViews(Listing $listing): void;

    /**
     * Товары для оформления заказа — вместе с магазином: заказать можно только
     * то, что принадлежит опубликованному магазину с доставкой.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Listing>
     */
    public function findManyWithStore(array $ids): Collection;

    /**
     * Меняет остаток на $delta (списание — отрицательное). Товары с stock_qty
     * = null пропускаются: у них учёт не ведётся, там всегда «в наличии».
     */
    public function adjustStock(int $listingId, int $delta): void;

    public function createMedia(Listing $listing, array $attributes): ListingMedia;

    public function deleteMedia(ListingMedia $media): void;

    public function maxMediaOrder(Listing $listing): int;
}
