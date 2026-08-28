<?php

namespace App\Repositories;

use App\Models\Complaint;
use App\Models\Listing;
use App\Models\Message;
use App\Models\NotificationDismissal;
use App\Models\Review;
use App\Models\User;
use App\Models\Video;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function pendingItems(int $limitPerCategory = 20): array
    {
        $chatUserIds = Message::where('sender', 'user')
            ->where('is_read', false)
            ->select('user_id')
            ->distinct()
            ->latest('user_id')
            ->limit($limitPerCategory)
            ->pluck('user_id');

        return [
            'newUsers' => User::where('role', 'user')
                ->where('created_at', '>=', now()->subDay())
                ->latest()
                ->limit($limitPerCategory)
                ->get(['id', 'name', 'phone', 'created_at']),

            'pendingListings' => Listing::where('status', 'pending')
                ->latest()
                ->limit($limitPerCategory)
                ->get(['id', 'title', 'created_at']),

            'pendingVideos' => Video::where('status', 'pending')
                ->latest()
                ->limit($limitPerCategory)
                ->get(['id', 'title', 'created_at']),

            'unreadChats' => User::whereIn('id', $chatUserIds)
                ->get(['id', 'name', 'phone']),

            'newComplaints' => Complaint::where('status', 'new')
                ->with('listing:id,title')
                ->latest()
                ->limit($limitPerCategory)
                ->get(['id', 'listing_id', 'text', 'created_at']),

            'pendingReviews' => Review::where('status', 'pending')
                ->latest()
                ->limit($limitPerCategory)
                ->get(['id', 'text', 'created_at']),
        ];
    }

    /**
     * Бейджи меню считаются на КАЖДЫЙ запрос админки, включая XHR-переходы
     * Inertia. Раньше это были шесть отдельных COUNT-ов из middleware; здесь
     * они собраны в один запрос подзапросами — данные остаются свежими
     * (кэш дал бы модератору устаревшие числа сразу после модерации).
     *
     * 0 вместо false: sqlite биндит false как '' и не матчит 0.
     */
    public function counters(): array
    {
        $row = DB::selectOne(
            'select
                (select count(*) from users where role = ? and created_at >= ?) as new_users,
                (select count(*) from listings where status = ?) as pending_listings,
                (select count(*) from videos where status = ?) as pending_videos,
                (select count(distinct user_id) from messages where sender = ? and is_read = ?) as unread_chats,
                (select count(*) from complaints where status = ?) as new_complaints,
                (select count(*) from reviews where status = ?) as pending_reviews,
                (select count(*) from stores where status = ?) as pending_stores,
                (select count(*) from tariff_requests where status = ?) as pending_tariff_requests',
            ['user', now()->subDay(), 'pending', 'pending', 'user', 0, 'new', 'pending', 'pending', 'pending'],
        );

        return [
            'newUsers'        => (int) $row->new_users,
            'pendingListings' => (int) $row->pending_listings,
            'pendingVideos'   => (int) $row->pending_videos,
            'unreadChats'     => (int) $row->unread_chats,
            'newComplaints'   => (int) $row->new_complaints,
            'pendingReviews'  => (int) $row->pending_reviews,
            'pendingStores'   => (int) $row->pending_stores,
            'pendingTariffRequests' => (int) $row->pending_tariff_requests,
        ];
    }

    public function dismissedKeys(int $userId): array
    {
        return NotificationDismissal::where('user_id', $userId)->pluck('key')->all();
    }

    public function dismiss(int $userId, string $key): void
    {
        NotificationDismissal::firstOrCreate(['user_id' => $userId, 'key' => $key]);
    }
}
