<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function find(int $id): User
    {
        return User::with('region', 'city', 'tariff')->findOrFail($id);
    }

    public function findByPhone(string $phone): ?User
    {
        return User::where('phone', $phone)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
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
