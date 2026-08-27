<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\PushNotificationRepositoryInterface;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ручная push-рассылка из админки (/admin push).
 *
 * Раньше выбор получателей и запись в журнал жили прямо в контроллере,
 * а при target=all вся активная база поднималась в память одним get().
 */
class PushBroadcastService
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
        private readonly PushNotificationRepositoryInterface $pushNotifications,
        private readonly UserRepositoryInterface $users,
        private readonly RegionRepositoryInterface $regions,
        private readonly TariffRepositoryInterface $tariffs,
    ) {}

    public function history(int $perPage = 20): LengthAwarePaginator
    {
        return $this->pushNotifications->paginate($perPage);
    }

    public function regionOptions(): Collection
    {
        return $this->regions->all();
    }

    public function tariffOptions(): Collection
    {
        return $this->tariffs->active();
    }

    /**
     * Рассылает уведомление и записывает факт отправки.
     *
     * @return int сколько получателей имели хотя бы один живой токен устройства
     */
    public function send(array $data, User $author): int
    {
        $payload = [
            'type' => $data['link_type'] ?? 'system',
            'id'   => $data['link_id'] ?? null,
        ];

        $reached = 0;

        $this->users->chunkPushTargets(
            $this->criteriaFor($data),
            function ($users) use (&$reached, $data, $payload) {
                $reached += $this->pushNotificationService->sendToUsers(
                    $users,
                    $data['title'],
                    $data['body'],
                    $payload,
                );
            },
        );

        $this->pushNotifications->create([
            'title'      => $data['title'],
            'body'       => $data['body'],
            'target'     => $data['target'],
            'user_ids'   => $data['user_ids'] ?? null,
            'filters'    => $data['filters'] ?? null,
            'link_type'  => $data['link_type'] ?? null,
            'link_id'    => $data['link_id'] ?? null,
            'sent_count' => $reached,
            'sent_at'    => now(),
            'created_by' => $author->id,
        ]);

        return $reached;
    }

    /**
     * target=all — без ограничений; selected — по списку id; filtered — по региону
     * и тарифу. Пустой список/фильтр трактуется как «ограничения нет», как и раньше.
     */
    private function criteriaFor(array $data): array
    {
        if ($data['target'] === 'selected' && ! empty($data['user_ids'])) {
            return ['user_ids' => $data['user_ids']];
        }

        if ($data['target'] === 'filtered' && ! empty($data['filters'])) {
            return [
                'region_id' => $data['filters']['region_id'] ?? null,
                'tariff_id' => $data['filters']['tariff_id'] ?? null,
            ];
        }

        return [];
    }
}
