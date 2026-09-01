<?php

namespace App\Repositories;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Repositories\Concerns\BuildsLikeSearch;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListingRepository implements ListingRepositoryInterface
{
    use BuildsLikeSearch;

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Listing::with('user', 'category.parent.parent', 'region', 'city', 'media')
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['category_id'] ?? null, fn($q, $c) => $q->where('category_id', $c))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('title', 'like', self::likeTerm($s)))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Listing
    {
        return Listing::with('user', 'store', 'category.parent.parent', 'region', 'city', 'district', 'media', 'rejectionReason')
            ->tap(fn ($q) => $this->withRatingAggregates($q))
            ->findOrFail($id);
    }

    public function create(array $data): Listing
    {
        return Listing::create($data);
    }

    public function update(Listing $listing, array $data): Listing
    {
        $listing->update($data);
        return $listing->fresh();
    }

    public function delete(Listing $listing): void
    {
        $listing->delete();
    }

    public function countAll(): int
    {
        return Listing::count();
    }

    public function countByStatus(string $status): int
    {
        return Listing::where('status', $status)->count();
    }

    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return Listing::whereBetween('created_at', [$from, $to])->count();
    }

    public function countByDaySince(CarbonInterface $since): \Illuminate\Support\Collection
    {
        return Listing::where('created_at', '>=', $since)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->pluck('total', 'date');
    }

    public function recent(int $limit = 6): \Illuminate\Database\Eloquent\Collection
    {
        return Listing::with('user', 'category', 'region')->latest()->limit($limit)->get();
    }

    public function countActiveByUser(int $userId): int
    {
        return Listing::where('user_id', $userId)
            ->where('status', 'approved')
            ->count();
    }

    public function paginateForApi(array $filters, int $perPage = 20, ?int $viewerId = null): LengthAwarePaginator
    {
        $query = Listing::with('user', 'store', 'category.parent.parent', 'region', 'city', 'district', 'media')
            ->where('status', 'approved')
            ->tap(fn ($q) => $this->withRatingAggregates($q))
            ->when($viewerId, fn ($q, $id) => $q->withExists([
                'favorites as is_favorite' => fn ($f) => $f->where('user_id', $id),
            ]))
            ->when($filters['category_ids'] ?? null, fn ($q, $ids) => $q->whereIn('category_id', $ids))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['store_id'] ?? null, fn ($q, $id) => $q->where('store_id', $id))
            ->when($filters['region_id'] ?? null, fn ($q, $id) => $q->where('region_id', $id))
            ->when($filters['city_id'] ?? null, fn ($q, $id) => $q->where('city_id', $id))
            ->when($filters['district_id'] ?? null, fn ($q, $id) => $q->where('district_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            // trade=wholesale — только товары с оптовой ценой, trade=retail — с розничной
            ->when(($filters['trade'] ?? null) === 'wholesale', fn ($q) => $q->whereNotNull('wholesale_price'))
            ->when(($filters['trade'] ?? null) === 'retail', fn ($q) => $q->whereNotNull('price'))
            // in_stock=1 прячет товары, которые владелец пометил как закончившиеся
            // (null = учёт не ведётся, такие остаются в выдаче)
            ->when(! empty($filters['in_stock']), fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('stock_qty')
                ->orWhere('stock_qty', '>', 0)))
            ->when(isset($filters['price_min']), fn ($q) => $q->where('price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn ($q) => $q->where('price', '<=', $filters['price_max']))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $term = self::likeTerm($s);
                $q->where(fn ($w) => $w
                    ->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term));
            });

        match ($filters['sort'] ?? 'latest') {
            'price_asc'  => $query->orderByRaw('price IS NULL')->orderBy('price'),
            'price_desc' => $query->orderByRaw('price IS NULL')->orderByDesc('price'),
            'nearest'    => $this->orderByDistance($query, (float) $filters['lat'], (float) $filters['lng']),
            default      => $query->orderByDesc('is_boosted')->latest(),
        };

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function paginateByUser(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Listing::with('store', 'category.parent.parent', 'region', 'city', 'district', 'media', 'rejectionReason')
            ->tap(fn ($q) => $this->withRatingAggregates($q))
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByUserAndStatuses(int $userId, array $statuses): int
    {
        return Listing::where('user_id', $userId)->whereIn('status', $statuses)->count();
    }

    public function countBoostedByUser(int $userId): int
    {
        return Listing::where('user_id', $userId)->where('is_boosted', true)->count();
    }

    public function sumViewsByUser(int $userId): int
    {
        return (int) Listing::where('user_id', $userId)->sum('views');
    }

    /**
     * Средняя оценка карточки (route model binding минует find) и заодно
     * рейтинг её продавца — в карточке мобилка показывает оба.
     * Вызывать ПОСЛЕ загрузки связи user: повторный load('user') сотрёт агрегаты.
     */
    public function loadRatingAggregates(Listing $listing): void
    {
        $approved = fn ($q) => $q->where('status', 'approved');

        $listing
            ->loadCount(['reviews as reviews_count' => $approved])
            ->loadAvg(['reviews as reviews_avg_rating' => $approved], 'rating');

        $listing->loadMissing('user')->user
            ?->loadCount(['receivedReviews as reviews_count' => $approved])
            ->loadAvg(['receivedReviews as reviews_avg_rating' => $approved], 'rating');
    }

    public function loadFavoriteFlag(Listing $listing, ?int $viewerId): void
    {
        if ($viewerId === null) {
            return;
        }

        $listing->loadExists([
            'favorites as is_favorite' => fn ($f) => $f->where('user_id', $viewerId),
        ]);
    }

    public function incrementViews(Listing $listing): void
    {
        $listing->increment('views');
    }

    public function createMedia(Listing $listing, array $attributes): ListingMedia
    {
        return $listing->media()->create($attributes);
    }

    public function deleteMedia(ListingMedia $media): void
    {
        $media->delete();
    }

    public function maxMediaOrder(Listing $listing): int
    {
        return (int) $listing->media()->max('order');
    }

    /**
     * Рейтинг объявления считается только по одобренным отзывам —
     * иначе в выдаче всплывали бы оценки, не прошедшие модерацию.
     */
    private function withRatingAggregates(Builder $query): void
    {
        $query
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('status', 'approved')], 'rating');
    }

    /**
     * Сортировка по близости: приближённый квадрат расстояния в градусах,
     * долготная дельта сжата на cos(широты). JSON-извлечение зависит от драйвера.
     */
    private function orderByDistance(Builder $query, float $lat, float $lng): void
    {
        [$latExpr, $lngExpr] = $query->getConnection()->getDriverName() === 'pgsql'
            ? ["((location->>'lat')::float)", "((location->>'lng')::float)"]
            : ["CAST(json_extract(location, '$.lat') AS REAL)", "CAST(json_extract(location, '$.lng') AS REAL)"];

        $cosSquared = cos(deg2rad($lat)) ** 2;

        $query->whereNotNull('location')
            ->orderByRaw(
                "($latExpr - ?) * ($latExpr - ?) + ($lngExpr - ?) * ($lngExpr - ?) * ?",
                [$lat, $lat, $lng, $lng, $cosSquared]
            );
    }
}
