<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelOrderAction;
use App\Actions\PlaceOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MyOrdersRequest;
use App\Http\Requests\Api\V1\PlaceOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Services\OrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Заказы покупателя.
 *
 * Корзина остаётся на устройстве — сюда приходит уже собранный заказ, и всегда
 * на один магазин. Дальше его ведёт сам продавец: принимает, собирает и везёт
 * (см. CLAUDE.md → «Заказы и корзина»).
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PlaceOrderAction $placeOrder,
        private readonly CancelOrderAction $cancelOrder,
    ) {}

    /**
     * Оформление заказа из корзины.
     * POST /api/v1/orders
     *
     * @authenticated
     */
    public function store(PlaceOrderRequest $request)
    {
        $order = $this->placeOrder->execute($request->user(), $request->validated());

        return response()->json([
            'data'    => new OrderResource($order),
            'message' => __('messages.order_created'),
        ], 201);
    }

    /**
     * Мои заказы.
     * GET /api/v1/orders?status=pending&limit=20
     *
     * @authenticated
     */
    public function index(MyOrdersRequest $request)
    {
        $orders = $this->orderService->listForUser(
            $request->user(),
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
        );

        return $this->paginated($orders);
    }

    /**
     * Карточка своего заказа.
     * GET /api/v1/orders/{id}
     *
     * @authenticated
     */
    public function show(Request $request, int $order)
    {
        $model = $this->orderService->findForUser($order, $request->user());

        abort_unless((bool) $model, 404, __('messages.order_not_found'));

        return response()->json([
            'data'    => new OrderResource($model),
            'message' => 'Success',
        ]);
    }

    /**
     * Отмена заказа покупателем — пока продавец не ответил.
     * POST /api/v1/orders/{id}/cancel
     *
     * @authenticated
     */
    public function cancel(Request $request, int $order)
    {
        $model = $this->orderService->findForUser($order, $request->user());

        abort_unless((bool) $model, 404, __('messages.order_not_found'));

        $canceled = $this->cancelOrder->execute($model);

        return response()->json([
            'data'    => new OrderResource($canceled),
            'message' => __('messages.order_canceled'),
        ]);
    }

    private function paginated(LengthAwarePaginator $orders)
    {
        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
            'links' => [
                'first' => $orders->url(1),
                'last'  => $orders->url($orders->lastPage()),
                'prev'  => $orders->previousPageUrl(),
                'next'  => $orders->nextPageUrl(),
            ],
        ]);
    }
}
