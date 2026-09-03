<?php

namespace App\Actions;

use App\Models\Suborder;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Ответ владельца магазина на свою часть заказа: подтверждаю, что товар есть
 * и я его отдаю, — или отказываюсь.
 *
 * Отказ возвращает остатки по позициям этого магазина обратно: заказ ведёт
 * админ, он решает, отменять его целиком или везти остальное.
 */
class RespondToSuborderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Suborder $suborder, User $owner, string $status, ?string $comment = null): Suborder
    {
        if ($suborder->user_id !== $owner->id) {
            throw ValidationException::withMessages([
                'status' => __('messages.forbidden'),
            ]);
        }

        if (! $suborder->isAwaitingOwner()) {
            throw ValidationException::withMessages([
                'status' => __('messages.suborder_already_answered'),
            ]);
        }

        if ($status === 'declined') {
            $this->orderService->releaseStock($suborder);
        }

        return $this->orderService->respondToSuborder($suborder, $status, $comment);
    }
}
