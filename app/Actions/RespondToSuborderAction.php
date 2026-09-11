<?php

namespace App\Actions;

use App\Events\OrderApproved;
use App\Events\OrderRejected;
use App\Models\Suborder;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Ответ владельца магазина на пришедший заказ — и решение по заказу целиком.
 *
 * Заказ передаётся прямо продавцу, поэтому его «принимаю» и есть подтверждение
 * заказа: ровно здесь списываются остатки и покупатель получает уведомление.
 * Отказ закрывает заказ — товар был только у этого магазина (в заказе всегда
 * один продавец), везти больше нечего.
 *
 * Админ в решении не участвует: он видит в админке, что и кому продано
 * (см. CLAUDE.md → «Заказы и корзина»).
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

        $order = $suborder->order;

        if ($status === 'accepted') {
            // До ответа продавца заказ ничего не резервирует — остаток
            // списывается ровно в момент, когда наличие подтверждено
            $this->orderService->takeStock($order);

            event(new OrderApproved(
                $this->orderService->changeStatus($order, 'approved', $owner),
            ));
        } else {
            // Причина отказа уходит покупателю в push и остаётся в заказе
            event(new OrderRejected(
                $this->orderService->changeStatus($order, 'rejected', $owner, $comment),
            ));
        }

        // Ответ проставляем последним: так вернувшийся подзаказ везёт с собой
        // уже новый статус заказа, а не тот, что был до решения
        return $this->orderService->respondToSuborder($suborder, $status, $comment);
    }
}
