<?php

namespace App\Repositories;

use App\Models\TariffRequest;
use App\Models\User;
use App\Repositories\Interfaces\TariffRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TariffRequestRepository implements TariffRequestRepositoryInterface
{
    public function pendingForUser(int $userId): ?TariffRequest
    {
        return TariffRequest::with('tariff')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    public function latestForUser(int $userId): ?TariffRequest
    {
        return TariffRequest::with('tariff')
            ->where('user_id', $userId)
            ->latest('id')
            ->first();
    }

    public function create(User $user, int $tariffId, string $amount): TariffRequest
    {
        return TariffRequest::create([
            'user_id'   => $user->id,
            'tariff_id' => $tariffId,
            'amount'    => $amount,
            'status'    => 'pending',
        ]);
    }

    public function markProcessed(TariffRequest $request, string $status, int $adminId, ?string $comment = null): TariffRequest
    {
        $request->update([
            'status'       => $status,
            'comment'      => $comment,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ]);

        return $request->fresh(['user', 'tariff', 'processor']);
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return TariffRequest::with('user', 'tariff', 'processor')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            // Ждущие подтверждения — наверх: админ работает именно с ними
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countPending(): int
    {
        return TariffRequest::where('status', 'pending')->count();
    }
}
