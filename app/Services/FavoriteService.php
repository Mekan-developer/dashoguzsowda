<?php

namespace App\Services;

use App\Models\Favorite;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FavoriteService
{
    public function __construct(
        private readonly FavoriteRepositoryInterface $favoriteRepository,
    ) {}

    public function add(int $userId, int $listingId): Favorite
    {
        return $this->favoriteRepository->add($userId, $listingId);
    }

    public function remove(int $userId, int $listingId): void
    {
        $this->favoriteRepository->remove($userId, $listingId);
    }

    public function listForUser(int $userId, int $perPage = 20, bool $withWholesale = false): LengthAwarePaginator
    {
        return $this->favoriteRepository->paginateForUser($userId, $perPage, $withWholesale);
    }
}
