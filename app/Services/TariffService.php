<?php

namespace App\Services;

use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

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

        $used = $this->usedCounts($user);

        return [
            'listings' => max(0, $tariff->listings_limit - $used['listings']),
            'videos'   => max(0, $tariff->videos_limit - $used['videos']),
            'boosts'   => max(0, $tariff->boost_limit - $used['boosts']),
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

    /**
     * Тариф + лимиты/использовано/дней — плоский массив для мобильного профиля
     * (mobile_docs/BACKEND_API.md §2, поле `tariff`/`subscription`).
     *
     * @return array{tariff: Tariff|null, ads_limit: int, ads_used: int, videos_limit: int, videos_used: int, boosts_limit: int, days_left: int}
     */
    public function usageSummary(User $user): array
    {
        $tariff = $user->activeTariff();

        if (! $tariff) {
            return [
                'tariff'       => null,
                'ads_limit'    => 0, 'ads_used'    => 0,
                'videos_limit' => 0, 'videos_used' => 0,
                'boosts_limit' => 0,
                'days_left'    => 0,
            ];
        }

        $used = $this->usedCounts($user);

        $daysLeft = 0;
        if (! $tariff->is_free && $user->tariff_ends_at && $user->tariff_ends_at->isFuture()) {
            $daysLeft = (int) now()->diffInDays($user->tariff_ends_at);
        }

        return [
            'tariff'       => $tariff,
            'ads_limit'    => $tariff->listings_limit,
            'ads_used'     => $used['listings'],
            'videos_limit' => $tariff->videos_limit,
            'videos_used'  => $used['videos'],
            'boosts_limit' => $tariff->boost_limit,
            'days_left'    => $daysLeft,
        ];
    }

    /**
     * Каталог планов для GET /v1/tariffs — used-поля всегда 0 (доке это прямо
     * разрешено для каталога, реальное использование только в usageSummary()).
     * Только активные тарифы со slug (name) — без него мобилка не сможет
     * запросить смену тарифа через PUT /v1/profile/subscription.
     *
     * @return SupportCollection<int, array>
     */
    public function catalogEntries(): SupportCollection
    {
        return $this->tariffRepository->catalogActive()->map(fn (Tariff $tariff) => [
            'name'         => $tariff->name,
            'ads_limit'    => $tariff->listings_limit,
            'ads_used'     => 0,
            'videos_limit' => $tariff->videos_limit,
            'videos_used'  => 0,
            'boosts_limit' => $tariff->boost_limit,
            'days_left'    => $tariff->duration_days,
        ]);
    }

    public function findBySlug(string $slug): ?Tariff
    {
        return $this->tariffRepository->findByName($slug);
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

    /**
     * Занятая квота считается так же, как при публикации (CheckTariffLimitAction /
     * CheckVideoLimitAction): pending + approved занимают место, поднятые — квоту поднятий.
     */
    private function usedCounts(User $user): array
    {
        return [
            'listings' => $this->listingRepository->countByUserAndStatuses($user->id, ['pending', 'approved']),
            'videos'   => $this->videoRepository->countByUserAndStatuses($user->id, ['pending', 'approved']),
            'boosts'   => $this->listingRepository->countBoostedByUser($user->id),
        ];
    }
}
