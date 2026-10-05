<?php

namespace App\Actions;

use App\Models\User;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;

/**
 * Подгоняет видимый контент пользователя под лимиты его текущего тарифа.
 *
 * Тариф стал меньше (истёк платный, админ понизил) — самые старые одобренные
 * объявления и ролики сверх квоты уходят в `suspended` и пропадают из выдачи.
 * Тариф стал больше — самые свежие скрытые возвращаются в `approved` на
 * освободившиеся места. Возврат без повторной модерации: скрываются только
 * уже одобренные.
 *
 * Квота считается как при публикации (CheckTariffLimitAction): pending +
 * approved. На проверке ничего не скрывается — если места не хватает даже
 * им, их судьбу решит модератор.
 */
class SyncTariffContentAction
{
    public function __construct(
        private readonly ListingRepositoryInterface $listingRepository,
        private readonly VideoRepositoryInterface $videoRepository,
    ) {}

    /**
     * @return array{suspended: array{listings: int, videos: int}, restored: array{listings: int, videos: int}}
     */
    public function execute(User $user): array
    {
        $tariff = $user->activeTariff();

        [$suspendedListings, $restoredListings] = $this->sync(
            $this->listingRepository,
            $user->id,
            (int) ($tariff?->listings_limit ?? 0),
        );

        [$suspendedVideos, $restoredVideos] = $this->sync(
            $this->videoRepository,
            $user->id,
            (int) ($tariff?->videos_limit ?? 0),
        );

        return [
            'suspended' => ['listings' => $suspendedListings, 'videos' => $suspendedVideos],
            'restored'  => ['listings' => $restoredListings, 'videos' => $restoredVideos],
        ];
    }

    /** @return array{0: int, 1: int} [скрыто, возвращено] */
    private function sync(
        ListingRepositoryInterface|VideoRepositoryInterface $repository,
        int $userId,
        int $limit,
    ): array {
        $used = $repository->countByUserAndStatuses($userId, ['pending', 'approved']);

        if ($used > $limit) {
            return [$repository->suspendOldestApproved($userId, $used - $limit), 0];
        }

        return [0, $repository->restoreNewestSuspended($userId, $limit - $used)];
    }
}
