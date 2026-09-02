<?php

namespace App\Services;

use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;

/**
 * Главная страница админки.
 *
 * Раньше все запросы (включая два groupBy(DB::raw(...)) и withCount) жили
 * прямо в DashboardController::__invoke.
 */
class DashboardService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ListingRepositoryInterface $listings,
        private readonly VideoRepositoryInterface $videos,
        private readonly ComplaintRepositoryInterface $complaints,
        private readonly CategoryRepositoryInterface $categories,
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function overview(int $userId): array
    {
        $counters = $this->notifications->counters($userId);

        return [
            'stats' => [
                'users'            => $this->users->countUsers(),
                'listings'         => $this->listings->countAll(),
                'listings_pending' => $counters['pendingListings'],
                'videos'           => $this->videos->countAll(),
                'videos_pending'   => $counters['pendingVideos'],
                'complaints_new'   => $counters['newComplaints'],
            ],
            'charts' => [
                'users_7d'    => $this->users->countByDaySince(now()->subDays(7)),
                'listings_7d' => $this->listings->countByDaySince(now()->subDays(7)),
            ],
            'topCategories'    => $this->categories->topByListings(5),
            'recentListings'   => $this->listings->recent(6),
            'recentComplaints' => $this->complaints->recentNew(5),
        ];
    }
}
