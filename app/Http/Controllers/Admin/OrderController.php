<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveOrderAction;
use App\Actions\CancelOrderAction;
use App\Actions\CompleteOrderAction;
use App\Actions\RejectOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectOrderRequest;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Заказы товаров магазинов.
 *
 * Онлайн-оплаты нет: заказ ведёт админ вручную — обзванивает магазины,
 * подтверждает наличие, везёт покупателю и получает деньги на месте.
 * «Подтвердить» здесь означает «товар есть, беру в работу»: только после
 * него списываются остатки, а части заказа появляются у владельцев магазинов.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ApproveOrderAction $approveOrder,
        private readonly RejectOrderAction $rejectOrder,
        private readonly CancelOrderAction $cancelOrder,
        private readonly CompleteOrderAction $completeOrder,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'orders');

        return Inertia::render('Orders/Index', [
            'orders'  => $this->orderService->list($request->only('status', 'search', 'store_id')),
            'stores'  => $this->orderService->storesWithOrders(),
            'counts'  => ['pending' => $this->orderService->countPending()],
            'filters' => $request->only('status', 'search', 'store_id'),
        ]);
    }

    /** Товар есть — заказ уходит в работу, магазины получают свои части. */
    public function approve(Request $request, Order $order)
    {
        $this->approveOrder->execute($order, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.order_approved')]);
    }

    public function reject(RejectOrderRequest $request, Order $order)
    {
        $this->rejectOrder->execute($order, $request->user(), $request->validated('comment'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.order_rejected')]);
    }

    /** Отмена админом возможна и после подтверждения — остатки возвращаются. */
    public function cancel(Request $request, Order $order)
    {
        $this->cancelOrder->execute($order, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.order_canceled')]);
    }

    /** Заказ доставлен и оплачен на месте — последний шаг, дальше он закрыт. */
    public function complete(Request $request, Order $order)
    {
        $this->completeOrder->execute($order, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.order_completed')]);
    }
}
