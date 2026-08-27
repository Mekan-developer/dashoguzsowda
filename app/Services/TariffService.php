<?php

namespace App\Services;

use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TariffService
{
    public function __construct(
        private readonly TariffRepositoryInterface $tariffRepository,
        private readonly ListingRepositoryInterface $listingRepository,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function all(): Collection
    {
        return $this->tariffRepository->all();
    }

    public function getRemainingLimits(User $user): array
    {
        $tariff = $user->activeTariff();

        if (! $tariff) {
            return ['listings' => 0, 'videos' => 0, 'boosts' => 0];
        }

        // Занятая квота считается так же, как при публикации (CheckTariffLimitAction /
        // CheckVideoLimitAction): pending + approved занимают место, поднятые — квоту поднятий.
        $usedListings = $this->listingRepository->countByUserAndStatuses($user->id, ['pending', 'approved']);
        $usedVideos   = $this->videoRepository->countByUserAndStatuses($user->id, ['pending', 'approved']);
        $usedBoosts   = $this->listingRepository->countBoostedByUser($user->id);

        return [
            'listings' => max(0, $tariff->listings_limit - $usedListings),
            'videos'   => max(0, $tariff->videos_limit - $usedVideos),
            'boosts'   => max(0, $tariff->boost_limit - $usedBoosts),
        ];
    }

    /**
     * Текущий тариф пользователя + остаток лимитов — для экрана тарифа
     * в мобильном приложении (ТЗ §6).
     *
     * @return array{tariff: Tariff|null, expires_at: \Illuminate\Support\Carbon|null, remaining: array}
     */
    public function currentForUser(User $user): array
    {
        $tariff = $user->activeTariff();

        return [
            // Срок действия показываем только для платного тарифа; на бесплатном
            // (в т.ч. когда платный истёк и activeTariff вернул бесплатный) — бессрочно.
            'tariff'     => $tariff,
            'expires_at' => ($tariff && ! $tariff->is_free) ? $user->tariff_ends_at : null,
            'remaining'  => $this->getRemainingLimits($user),
        ];
    }

    public function assignToUser(User $user, Tariff $tariff): void
    {
        // tariff_* исключены из User::$fillable (это оплаченные лимиты),
        // поэтому запись идёт явным методом репозитория.
        $this->userRepository->assignTariff(
            $user,
            $tariff->id,
            now()->addDays($tariff->duration_days),
        );
    }

    public function store(array $data): Tariff
    {
        if (! empty($data['is_free'])) {
            $this->tariffRepository->clearFree();
        }
        return $this->tariffRepository->create($data);
    }

    public function update(Tariff $tariff, array $data): Tariff
    {
        if (! empty($data['is_free'])) {
            $this->tariffRepository->clearFree();
        }
        return $this->tariffRepository->update($tariff, $data);
    }

    public function delete(Tariff $tariff): void
    {
        $this->tariffRepository->delete($tariff);
    }
}
