<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompleteOrderAction;
use App\Actions\RespondToSuborderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RespondToSuborderRequest;
use App\Http\Requests\Api\V1\StoreOrdersRequest;
use App\Http\Resources\Api\V1\StoreOrderResource;
use App\Services\OrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Заказы, пришедшие в магазин владельца.
 *
 * Заказ идёт прямо продавцу и ведёт его он: принимает, собирает, везёт сам и
 * закрывает доставленным. Контакты покупателя и адрес приходят вместе с
 * заказом — без них доставку не сделать. Админ в решении не участвует
 * (см. CLAUDE.md → «Заказы и корзина»).
 */
class StoreOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly RespondToSuborderAction $respondToSuborder,
        private readonly CompleteOrderAction $completeOrder,
    ) {}

    /**
     * Заказы магазина.
     * GET /api/v1/my/store/orders?status=to_deliver&q=65000&limit=20
     *
     * @authenticated
     */
    public function index(StoreOrdersRequest $request)
    {
        $suborders = $this->orderService->listForOwner(
            $request->user(),
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
        );

        return $this->paginated($suborders, $request);
    }

    /**
     * Карточка заказа магазина.
     * GET /api/v1/my/store/orders/{id}
     *
     * @authenticated
     */
    public function show(Request $request, int $suborder)
    {
        $model = $this->orderService->findSuborderForOwner($suborder, $request->user());

        abort_unless((bool) $model, 404, __('messages.order_not_found'));

        return response()->json([
            'data'    => new StoreOrderResource($model),
            'message' => 'Success',
        ]);
    }

    /**
     * «Товар есть, беру заказ» — заказ подтверждён, остатки списываются,
     * покупатель получает уведомление.
     * POST /api/v1/my/store/orders/{id}/accept
     *
     * @authenticated
     */
    public function accept(RespondToSuborderRequest $request, int $suborder)
    {
        return $this->respond($request, $suborder, 'accepted', __('messages.suborder_accepted'));
    }

    /**
     * Отказ продавца: заказ закрывается, остатки не трогаются, причина уходит
     * покупателю.
     * POST /api/v1/my/store/orders/{id}/decline
     *
     * @authenticated
     */
    public function decline(RespondToSuborderRequest $request, int $suborder)
    {
        return $this->respond($request, $suborder, 'declined', __('messages.suborder_declined'));
    }

    /**
     * «Отвёз и получил деньги» — последний шаг заказа.
     * POST /api/v1/my/store/orders/{id}/complete
     *
     * @authenticated
     */
    public function complete(Request $request, int $suborder)
    {
        $model = $this->orderService->findSuborderForOwner($suborder, $request->user());

        abort_unless((bool) $model, 404, __('messages.order_not_found'));

        $completed = $this->completeOrder->execute($model, $request->user());

        return response()->json([
            'data'    => new StoreOrderResource($completed),
            'message' => __('messages.order_completed'),
        ]);
    }

    private function respond(RespondToSuborderRequest $request, int $suborderId, string $status, string $message)
    {
        $model = $this->orderService->findSuborderForOwner($suborderId, $request->user());

        abort_unless((bool) $model, 404, __('messages.order_not_found'));

        $updated = $this->respondToSuborder->execute(
            $model,
            $request->user(),
            $status,
            $request->validated('comment'),
        );

        return response()->json([
            'data'    => new StoreOrderResource($updated),
            'message' => $message,
        ]);
    }

    private function paginated(LengthAwarePaginator $suborders, Request $request)
    {
        $tabs = $this->orderService->tabCountsForOwner($request->user());

        return response()->json([
            'data' => StoreOrderResource::collection($suborders->items()),
            'meta' => [
                'current_page' => $suborders->currentPage(),
                'last_page'    => $suborders->lastPage(),
                'per_page'     => $suborders->perPage(),
                'total'        => $suborders->total(),
                // Бейджи вкладок: ждут ответа и приняты, но ещё не доставлены
                'pending'      => $tabs['pending'],
                'to_deliver'   => $tabs['to_deliver'],
            ],
            'links' => [
                'first' => $suborders->url(1),
                'last'  => $suborders->url($suborders->lastPage()),
                'prev'  => $suborders->previousPageUrl(),
                'next'  => $suborders->nextPageUrl(),
            ],
        ]);
    }
}
