<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return User::with('region', 'city', 'tariff')
            ->where('role', 'user')
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('phone', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['region_id'] ?? null, fn($q, $r) => $q->where('region_id', $r))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countUsers(): int
    {
        return User::where('role', 'user')->count();
    }

    public function countBlocked(): int
    {
        return User::where('role', 'user')->where('status', 'blocked')->count();
    }

    public function countRegisteredBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return User::where('role', 'user')->whereBetween('created_at', [$from, $to])->count();
    }

    public function countByDaySince(CarbonInterface $since): Collection
    {
        return User::where('role', 'user')
            ->where('created_at', '>=', $since)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->pluck('total', 'date');
    }

    public function find(int $id): User
    {
        return User::with('region', 'city', 'tariff')->findOrFail($id);
    }

    public function findByPhone(string $phone): ?User
    {
        return User::where('phone', $phone)->first();
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    /**
     * Создание пользователя: профильные поля проходят через $fillable,
     * роль и подтверждение номера выставляются явно — из аргументов, а не
     * из массива, чтобы их нельзя было подсунуть вместе с данными формы.
     */
    public function createWithRole(array $attributes, string $role, bool $phoneVerified): User
    {
        $user = new User();
        $user->fill($attributes);
        $user->forceFill([
            'role'              => $role,
            'status'            => 'active',
            'phone_verified_at' => $phoneVerified ? now() : null,
        ]);
        $user->save();

        return $user;
    }

    public function markPhoneVerified(User $user): User
    {
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function block(User $user, ?string $reason): User
    {
        $user->forceFill([
            'status'         => 'blocked',
            'blocked_reason' => $reason,
            'blocked_at'     => now(),
        ])->save();

        return $user->refresh();
    }

    public function unblock(User $user): User
    {
        $user->forceFill([
            'status'         => 'active',
            'blocked_reason' => null,
            'blocked_at'     => null,
        ])->save();

        return $user->refresh();
    }

    public function assignTariff(User $user, int $tariffId, CarbonInterface $endsAt): User
    {
        $user->forceFill([
            'tariff_id'      => $tariffId,
            'tariff_ends_at' => $endsAt,
        ])->save();

        return $user->refresh();
    }

    public function setOnboardingCompleted(User $user, bool $completed): User
    {
        $user->forceFill(['onboarding_completed' => $completed])->save();

        return $user->refresh();
    }

    /**
     * Получатели push-рассылки порциями.
     *
     * chunkById, а не get(): при target=all выборка — это вся активная база,
     * и одним массивом моделей она клала процесс по памяти прямо в HTTP-запросе.
     * Выбираем только id — остальные атрибуты рассылке не нужны.
     */
    public function chunkPushTargets(array $criteria, callable $callback, int $chunkSize = 500): void
    {
        User::query()
            ->select('id')
            ->where('role', 'user')
            ->where('status', 'active')
            ->when($criteria['user_ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when($criteria['region_id'] ?? null, fn ($q, $id) => $q->where('region_id', $id))
            ->when($criteria['tariff_id'] ?? null, fn ($q, $id) => $q->where('tariff_id', $id))
            ->chunkById($chunkSize, $callback);
    }

    /**
     * Карточка пользователя: последние объявления + счётчики.
     * Считается в репозитории, чтобы контроллер не дёргал Eloquent напрямую.
     */
    public function profileOverview(User $user, int $recentListings = 10): array
    {
        $counts = DB::selectOne(
            'select
                (select count(*) from listings   where user_id = ?) as listings,
                (select count(*) from videos     where user_id = ?) as videos,
                (select count(*) from complaints where user_id = ?) as complaints',
            [$user->id, $user->id, $user->id],
        );

        return [
            'listings' => $user->listings()
                ->with('category', 'region')
                ->latest()
                ->limit($recentListings)
                ->get(),
            'stats' => [
                'listings'   => (int) $counts->listings,
                'videos'     => (int) $counts->videos,
                'complaints' => (int) $counts->complaints,
            ],
        ];
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function updateLocale(User $user, ?string $locale): void
    {
        $user->update(['locale' => $locale]);
    }
}
