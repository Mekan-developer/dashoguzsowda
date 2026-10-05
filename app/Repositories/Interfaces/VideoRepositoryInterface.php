<?php

namespace App\Repositories\Interfaces;

use App\Models\Video;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VideoRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;
    public function paginateForApi(array $filters, int $perPage = 20, ?int $viewerId = null): LengthAwarePaginator;
    public function paginateByUser(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator;
    public function find(int $id): Video;

    /** Ролик для карточки модерации: связи + preview_url/video_url/tariff_usage. */
    public function findForAdmin(Video $video): Video;
    public function create(array $data): Video;
    public function update(Video $video, array $data): Video;
    public function delete(Video $video): void;
    public function countAll(): int;
    public function countByStatus(string $status): int;
    public function countByUserAndStatuses(int $userId, array $statuses): int;

    /** Скрывает самые старые одобренные ролики пользователя (истёк тариф) */
    public function suspendOldestApproved(int $userId, int $count): int;

    /** Возвращает в ленту самые свежие скрытые по тарифу ролики */
    public function restoreNewestSuspended(int $userId, int $count): int;
    public function sumLikes(): int;

    /** @return array{is_liked: bool, likes_count: int} */
    public function toggleLike(Video $video, int $userId): array;
    public function loadLikeFlag(Video $video, ?int $viewerId): void;
    public function incrementViews(Video $video): void;
}
