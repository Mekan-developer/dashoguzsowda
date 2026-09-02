<?php

namespace App\Repositories\Interfaces;

interface NotificationRepositoryInterface
{
    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    public function pendingItems(int $limitPerCategory = 20): array;

    /**
     * Счётчики-бейджи бокового меню.
     *
     * pending-счётчики/newComplaints — «сколько сейчас реально ждёт обработки»,
     * не зависит от того, кто и когда открывал раздел. hasNew* — «появилось
     * ли что-то с последнего визита этого админа» (admin_section_views),
     * отдельный сигнал поверх счётчика, см. markSectionSeen.
     *
     * @return array{
     *     newUsers:int,
     *     pendingListings:int, hasNewListings:bool,
     *     pendingVideos:int, hasNewVideos:bool,
     *     unreadChats:int,
     *     newComplaints:int, hasNewComplaints:bool,
     *     pendingReviews:int, hasNewReviews:bool,
     *     pendingStores:int, hasNewStores:bool,
     *     pendingTariffRequests:int, hasNewTariffRequests:bool,
     * }
     */
    public function counters(int $userId): array;

    /** @return array<int, string> */
    public function dismissedKeys(int $userId): array;

    public function dismiss(int $userId, string $key): void;

    /**
     * Отмечает раздел просмотренным этим админом — точка «новое» в нём
     * гаснет до следующей записи, появившейся после этого момента. Не
     * трогает сам счётчик pending-статусов/newComplaints — это отдельная очередь,
     * которая гаснет только от фактической обработки записи.
     */
    public function markSectionSeen(int $userId, string $section): void;
}
