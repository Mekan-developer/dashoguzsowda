<?php

namespace App\Repositories\Interfaces;

use App\Models\Favorite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FavoriteRepositoryInterface
{
    public function add(int $userId, int $listingId): Favorite;
    public function remove(int $userId, int $listingId): void;
    /** $withWholesale = false — оптовые предложения скрыты (владелец видит свои). */
    public function paginateForUser(int $userId, int $perPage = 20, bool $withWholesale = false): LengthAwarePaginator;

    /** Сколько объявлений у пользователя в избранном — stats.likes_count в профиле мобилки */
    public function countForUser(int $userId): int;
}
