<?php

namespace App\Services;

use App\Models\Tariff;
use App\Models\TariffRequest;
use App\Models\User;
use App\Repositories\Interfaces\TariffRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Заявки на платный тариф. Деньги передаются админу наличными, поэтому смена
 * тарифа из мобильного приложения — это не покупка, а заявка: тариф активирует
 * админ, когда получит оплату.
 */
class TariffRequestService
{
    public function __construct(
        private readonly TariffRequestRepositoryInterface $tariffRequestRepository,
    ) {}

    public function pendingForUser(User $user): ?TariffRequest
    {
        return $this->tariffRequestRepository->pendingForUser($user->id);
    }

    public function create(User $user, Tariff $tariff): TariffRequest
    {
        // amount фиксируем на момент подачи: пока человек ищет деньги, цена
        // в справочнике может измениться, а договаривался он о той, что видел.
        return $this->tariffRequestRepository->create($user, $tariff->id, (string) $tariff->price);
    }

    public function markApproved(TariffRequest $request, User $admin): TariffRequest
    {
        return $this->tariffRequestRepository->markProcessed($request, 'approved', $admin->id);
    }

    public function markRejected(TariffRequest $request, User $admin, ?string $comment = null): TariffRequest
    {
        return $this->tariffRequestRepository->markProcessed($request, 'rejected', $admin->id, $comment);
    }

    /**
     * Состояние заявки для мобильного профиля: пока она pending, приложение
     * показывает «Заявка на рассмотрении» вместо кнопки смены тарифа, а после
     * отказа — причину, чтобы человек понимал, почему тариф не включился.
     */
    public function forProfile(User $user): ?array
    {
        $request = $this->tariffRequestRepository->latestForUser($user->id);

        if (! $request) {
            return null;
        }

        return [
            'id'           => $request->id,
            'tariff_name'  => $request->tariff?->name,
            'amount'       => (float) $request->amount,
            'status'       => $request->status,
            'comment'      => $request->comment,
            'created_at'   => $request->created_at?->toIso8601String(),
            'processed_at' => $request->processed_at?->toIso8601String(),
        ];
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->tariffRequestRepository->paginate($filters);
    }

    public function countPending(): int
    {
        return $this->tariffRequestRepository->countPending();
    }
}
