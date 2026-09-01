<?php

namespace App\Repositories\Interfaces;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Публичная лента: только approved, фильтры listing_id / target_user_id */
    public function paginateApproved(array $filters, int $perPage = 20): LengthAwarePaginator;

    /** Отзывы, написанные пользователем, — все статусы модерации */
    public function paginateByAuthor(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Сводка по одобренным отзывам объекта.
     *
     * @return array{count: int, rated_count: int, average: float|null, breakdown: array<int, int>}
     */
    public function summary(array $filters): array;

    public function find(int $id): Review;
    public function create(array $data): Review;
    public function update(Review $review, array $data): Review;
    public function delete(Review $review): void;
    public function countPending(): int;
    public function countByStatus(string $status): int;
}
