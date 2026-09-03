<?php

namespace App\Http\Controllers\Api\V1;

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
 * Владелец видит только свою часть заказа и только после того, как админ
 * подтвердил заказ: до этого никто не знает, соберётся ли он вообще.
 */
class StoreOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly RespondToSuborderAction $respondToSuborder,
    ) {}

    /**
     * Заказы магазина.
     * GET /api/v1/my/store/orders?status=pending&limit=20
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
     * «Товар есть, отдаю».
     * POST /api/v1/my/store/orders/{id}/accept
     *
     * @authenticated
     */
    public function accept(RespondToSuborderRequest $request, int $suborder)
    {
        return $this->respond($request, $suborder, 'accepted', __('messages.suborder_accepted'));
    }

    /**
     * Отказ владельца: позиции возвращаются в остатки, дальше решает админ.
     * POST /api/v1/my/store/orders/{id}/decline
     *
     * @authenticated
     */
    public function decline(RespondToSuborderRequest $request, int $suborder)
    {
        return $this->respond($request, $suborder, 'declined', __('messages.suborder_declined'));
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
        return response()->json([
            'data' => StoreOrderResource::collection($suborders->items()),
            'meta' => [
                'current_page' => $suborders->currentPage(),
                'last_page'    => $suborders->lastPage(),
                'per_page'     => $suborders->perPage(),
                'total'        => $suborders->total(),
                // Сколько заказов ждёт ответа — бейдж на вкладке магазина
                'pending'      => $this->orderService->countPendingForOwner($request->user()),
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
