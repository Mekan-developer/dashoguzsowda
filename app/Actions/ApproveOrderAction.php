<?php

namespace App\Actions;

use App\Events\OrderApproved;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Магазины подтвердили наличие — админ берёт заказ в работу и везёт.
 *
 * Владельцы отвечают до этого шага (свои части они видят сразу после
 * оформления), поэтому здесь админ опирается на их ответы. Ровно с этого
 * момента списываются остатки, а покупатель получает «заказ подтверждён».
 */
class ApproveOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Order $order, User $admin, ?string $comment = null): Order
    {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('messages.order_already_processed'),
            ]);
        }

        $this->orderService->takeStock($order);

        // Магазины, не ответившие в приложении, считаются подтвердившими: админ
        // подтверждает заказ как раз после разговора с ними. Отказавшиеся
        // (declined) остаются как есть — их товар не едет
        $this->orderService->closeSuborders($order, 'accepted', ['pending']);

        $approved = $this->orderService->changeStatus($order, 'approved', $admin, $comment);

        event(new OrderApproved($approved));

        return $approved;
    }
}
