<?php

namespace App\Repositories;

use App\Models\Favorite;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FavoriteRepository implements FavoriteRepositoryInterface
{
    public function add(int $userId, int $listingId): Favorite
    {
        return Favorite::firstOrCreate([
            'user_id'    => $userId,
            'listing_id' => $listingId,
        ]);
    }

    public function remove(int $userId, int $listingId): void
    {
        Favorite::where('user_id', $userId)->where('listing_id', $listingId)->delete();
    }

    public function countForUser(int $userId): int
    {
        return Favorite::where('user_id', $userId)->count();
    }

    public function paginateForUser(int $userId, int $perPage = 20, bool $withWholesale = false): LengthAwarePaginator
    {
        return Favorite::with('listing.media', 'listing.category', 'listing.store')
            ->where('user_id', $userId)
            // Объявление могло быть снято с публикации уже после добавления
            // в избранное — тогда его нельзя отдавать: FavoriteResource
            // разворачивает listing целиком, вместе с телефоном продавца.
            // Оптовое предложение клиенту не отдаём по той же причине, что и в
            // выдаче: опт видит только розничный продавец
            ->whereHas('listing', fn ($q) => $q
                ->where('status', 'approved')
                ->unless($withWholesale, fn ($l) => $l->where(fn ($w) => $w
                    ->where(fn ($r) => $r->retailOffers())
                    ->orWhere('user_id', $userId))))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
