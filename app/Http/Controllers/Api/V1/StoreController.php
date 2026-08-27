<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreListingsRequest;
use App\Http\Resources\Api\V1\ListingResource;
use App\Http\Resources\Api\V1\StoreResource;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    /**
     * Популярные магазины для главной страницы (курируется из админки).
     * GET /api/v1/stores/popular
     */
    public function popular()
    {
        return response()->json([
            'data' => StoreResource::collection($this->storeService->popular()),
        ]);
    }

    /**
     * Карточка магазина.
     * GET /api/v1/stores/{store}
     */
    public function show(Store $store)
    {
        return response()->json([
            'data'    => new StoreResource($store->load('photos', 'category')),
            'message' => 'Success',
        ]);
    }

    /**
     * Объявления владельца магазина — та же выдача, что GET /v1/listings.
     * GET /api/v1/stores/{store}/listings
     */
    public function listings(StoreListingsRequest $request, Store $store)
    {
        $listings = $this->storeService->listingsForStore(
            $store,
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
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
