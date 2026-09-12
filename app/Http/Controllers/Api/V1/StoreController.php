<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexStoresRequest;
use App\Http\Requests\Api\V1\StoreListingsRequest;
use App\Http\Resources\Api\V1\ListingResource;
use App\Http\Resources\Api\V1\StoreResource;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    /**
     * Список магазинов с фильтрами: регион/город/район, категория,
     * тип торговли (retail|wholesale), наличие доставки, поиск по названию.
     * GET /api/v1/stores
     */
    public function index(IndexStoresRequest $request)
    {
        $filters = $request->validated();

        $stores = $this->storeService->publicList($filters, (int) ($filters['limit'] ?? 20), $request->user('sanctum'));

        return response()->json([
            'data' => StoreResource::collection($stores->items()),
            'meta' => [
                'current_page' => $stores->currentPage(),
                'last_page'    => $stores->lastPage(),
                'per_page'     => $stores->perPage(),
                'total'        => $stores->total(),
            ],
        ]);
    }

    /**
     * Популярные магазины для главной страницы (курируется из админки).
     * GET /api/v1/stores/popular
     */
    public function popular(Request $request)
    {
        return response()->json([
            'data' => StoreResource::collection($this->storeService->popular(viewer: $request->user('sanctum'))),
        ]);
    }

    /**
     * Карточка магазина. Не прошедший модерацию или погашенный (истёк тариф
     * владельца) магазин публично не существует — 404, как и удалённый.
     * Чисто оптовый — тоже 404 для клиента: опт видит только розничный продавец.
     * GET /api/v1/stores/{store}
     */
    public function show(Request $request, Store $store)
    {
        abort_unless($store->isVisibleTo($request->user('sanctum')), 404);

        return response()->json([
            'data'    => new StoreResource($store->load('photos', 'category', 'region', 'city', 'district', 'paymentMethods')),
            'message' => 'Success',
        ]);
    }

    /**
     * Объявления владельца магазина — та же выдача, что GET /v1/listings.
     * GET /api/v1/stores/{store}/listings
     */
    public function listings(StoreListingsRequest $request, Store $store)
    {
        $viewer = $request->user('sanctum');

        abort_unless($store->isVisibleTo($viewer), 404);

        $listings = $this->storeService->listingsForStore(
            $store,
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
            $viewer,
        );

        return $this->paginated($listings);
    }

    private function paginated(LengthAwarePaginator $listings)
    {
        return response()->json([
            'data' => ListingResource::collection($listings->items()),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page'    => $listings->lastPage(),
                'per_page'     => $listings->perPage(),
                'total'        => $listings->total(),
            ],
        ]);
    }
}
