<?php

namespace App\Repositories\Interfaces;

interface NotificationRepositoryInterface
{
    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    public function pendingItems(int $limitPerCategory = 20): array;

    /**
     * Счётчики-бейджи бокового меню — одним запросом.
     *
     * @return array{newUsers:int, pendingListings:int, pendingVideos:int, unreadChats:int, newComplaints:int, pendingReviews:int}
     */
    public function counters(): array;

    /** @return array<int, string> */
    public function dismissedKeys(int $userId): array;

    public function dismiss(int $userId, string $key): void;
}
