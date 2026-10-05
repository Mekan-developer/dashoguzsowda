<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /**
     * Лёгкий поиск владельца для селектов в админке (create магазина/объявления/видео).
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string|null, phone:string}>
     */
    public function searchForSelect(string $query, int $limit = 20): \Illuminate\Support\Collection;

    public function countUsers(): int;
    public function countBlocked(): int;
    public function countRegisteredBetween(CarbonInterface $from, CarbonInterface $to): int;
    /** Регистрации по дням для графика на дашборде: ['2026-08-25' => 3, ...] */
    public function countByDaySince(CarbonInterface $since): \Illuminate\Support\Collection;
    public function find(int $id): User;
    public function findByPhone(string $phone): ?User;

    /**
     * Имена владельцев номеров одним запросом (мониторинг OTP).
     *
     * @param  array<int, string>  $phones
     * @return array<string, string>  номер => имя; незарегистрированных номеров в ответе нет
     */
    public function namesByPhones(array $phones): array;
    public function update(User $user, array $data): User;
    public function delete(User $user): void;
    public function updateLocale(User $user, ?string $locale): void;

    /*
    | Привилегированные поля (role, status, blocked_*, tariff_*, phone_verified_at)
    | исключены из User::$fillable, поэтому меняются только этими методами.
    */
    public function createWithRole(array $attributes, string $role, bool $phoneVerified): User;
    public function markPhoneVerified(User $user): User;
    public function block(User $user, ?string $reason): User;
    public function unblock(User $user): User;
    /** @param CarbonInterface|null $endsAt null — бессрочно (бесплатный тариф) */
    public function assignTariff(User $user, int $tariffId, ?CarbonInterface $endsAt): User;
    public function setOnboardingCompleted(User $user, bool $completed): User;

    /**
     * Получатели push-рассылки — порциями, чтобы не поднимать всю базу в память.
     *
     * @param  array{user_ids?: array<int>, region_id?: int|null, tariff_id?: int|null}  $criteria
     * @param  callable(\Illuminate\Support\Collection<int, User>): void  $callback
     */
    public function chunkPushTargets(array $criteria, callable $callback, int $chunkSize = 500): void;

    /**
     * Пользователи, у которых срок платного тарифа уже прошёл, — порциями.
     *
     * @param  callable(\Illuminate\Support\Collection<int, User>): void  $callback
     */
    public function chunkWithExpiredTariff(callable $callback, int $chunkSize = 200): void;

    /**
     * Карточка пользователя в админке: последние объявления и счётчики.
     *
     * @return array{listings: \Illuminate\Database\Eloquent\Collection, stats: array{listings:int, videos:int, complaints:int}}
     */
    public function profileOverview(User $user, int $recentListings = 10): array;
}
