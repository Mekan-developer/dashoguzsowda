<?php

namespace App\Repositories;

use App\Models\AdminSectionView;
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
    /**
     * section → таблица для markSectionSeen(). Белый список: секция всегда
     * приходит из кода контроллера (не из запроса), но так watermark для
     * незнакомой секции не пытается читать max(id) из произвольной таблицы.
     */
    private const SECTION_TABLES = [
        'users' => 'users',
        'listings' => 'listings',
        'videos' => 'videos',
        'reviews' => 'reviews',
        'complaints' => 'complaints',
        'stores' => 'stores',
        'tariff_requests' => 'tariff_requests',
    ];

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
     *
     * newUsers — не «последние сутки», а «те, кто зарегистрировался после
     * ID последнего пользователя, который этот админ видел, открывая раздел
     * Пользователи» (admin_section_views, см. markSectionSeen). ID, а не
     * timestamp: created_at хранится с точностью до секунды, и запись,
     * появившаяся в ту же секунду, что и открытие раздела, дала бы ничью;
     * автоинкремент такой проблемы не имеет. Пока админ ни разу не заходил —
     * порог 0, тот же эффект, что раньше давали «последние сутки».
     *
     * hasNew* (для очередей модерации) — по тому же принципу: не заменяют
     * pending-счётчики/newComplaints (это счётчик «сколько реально ждёт обработки»,
     * трогать его нельзя — модератор потеряет очередь, просто открыв
     * раздел), а лишь показывают точку «здесь есть что-то новое с прошлого
     * визита», которая гаснет от одного открытия раздела и не требует
     * обработки самой записи. Ничего не пишет в сущности при их создании —
     * markSectionSeen дергается только из Admin/*Controller::index(), моб.
     * API их не вызывает, на процесс создания объявления/видео/отзыва и т.д.
     * это никак не влияет.
     *
     * Watermark-lookup — один запрос по уникальному индексу (user_id,
     * section) на все секции сразу; сами счётчики — как и раньше, один
     * запрос; hasNew* — второй, отдельный, чтобы не городить один SQL на
     * 20+ позиционных плейсхолдеров вслепую. Итого 3 лёгких запроса на
     * каждый admin-запрос вместо шести до рефакторинга counters().
     */
    public function counters(int $userId): array
    {
        $lastSeen = AdminSectionView::where('user_id', $userId)->pluck('last_seen_id', 'section');
        $seen = fn (string $section) => (int) ($lastSeen[$section] ?? 0);

        $row = DB::selectOne(
            'select
                (select count(*) from users where role = ? and id > ?) as new_users,
                (select count(*) from listings where status = ?) as pending_listings,
                (select count(*) from videos where status = ?) as pending_videos,
                (select count(distinct user_id) from messages where sender = ? and is_read = ?) as unread_chats,
                (select count(*) from complaints where status = ?) as new_complaints,
                (select count(*) from reviews where status = ?) as pending_reviews,
                (select count(*) from stores where status = ?) as pending_stores,
                (select count(*) from tariff_requests where status = ?) as pending_tariff_requests',
            ['user', $seen('users'), 'pending', 'pending', 'user', 0, 'new', 'pending', 'pending', 'pending'],
        );

        $hasNew = DB::selectOne(
            'select
                (select exists(select 1 from listings where status = ? and id > ?)) as has_new_listings,
                (select exists(select 1 from videos where status = ? and id > ?)) as has_new_videos,
                (select exists(select 1 from complaints where status = ? and id > ?)) as has_new_complaints,
                (select exists(select 1 from reviews where status = ? and id > ?)) as has_new_reviews,
                (select exists(select 1 from stores where status = ? and id > ?)) as has_new_stores,
                (select exists(select 1 from tariff_requests where status = ? and id > ?)) as has_new_tariff_requests',
            [
                'pending', $seen('listings'),
                'pending', $seen('videos'),
                'new', $seen('complaints'),
                'pending', $seen('reviews'),
                'pending', $seen('stores'),
                'pending', $seen('tariff_requests'),
            ],
        );

        return [
            'newUsers' => (int) $row->new_users,
            'pendingListings' => (int) $row->pending_listings,
            'hasNewListings' => (bool) $hasNew->has_new_listings,
            'pendingVideos' => (int) $row->pending_videos,
            'hasNewVideos' => (bool) $hasNew->has_new_videos,
            'unreadChats' => (int) $row->unread_chats,
            'newComplaints' => (int) $row->new_complaints,
            'hasNewComplaints' => (bool) $hasNew->has_new_complaints,
            'pendingReviews' => (int) $row->pending_reviews,
            'hasNewReviews' => (bool) $hasNew->has_new_reviews,
            'pendingStores' => (int) $row->pending_stores,
            'hasNewStores' => (bool) $hasNew->has_new_stores,
            'pendingTariffRequests' => (int) $row->pending_tariff_requests,
            'hasNewTariffRequests' => (bool) $hasNew->has_new_tariff_requests,
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

    public function markSectionSeen(int $userId, string $section): void
    {
        $table = self::SECTION_TABLES[$section] ?? null;
        // Берём максимум по всей таблице, а не по отфильтрованному status =
        // pending: watermark — это «что вообще уже существует прямо сейчас»,
        // а не только то, что сейчас в очереди (запись могла и не быть
        // pending на момент просмотра, но важен сам факт, что админ её видел).
        $lastId = $table ? (int) (DB::table($table)->max('id') ?? 0) : 0;

        AdminSectionView::updateOrCreate(
            ['user_id' => $userId, 'section' => $section],
            ['last_seen_id' => $lastId],
        );
    }
}
