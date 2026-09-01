<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BoostListingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MyListingsRequest;
use App\Http\Requests\Api\V1\SearchListingsRequest;
use App\Http\Requests\Api\V1\StoreListingRequest;
use App\Http\Requests\Api\V1\UpdateListingRequest;
use App\Http\Resources\Api\V1\ListingResource;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listingService,
        private readonly BoostListingAction $boostListingAction,
    ) {}

    /**
     * Публичная выдача одобренных объявлений.
     * GET /api/v1/listings
     *
     * Фильтры: search, category_id (включая подкатегории), region_id, city_id,
     * type (goods|services), price_min, price_max,
     * sort (latest|price_asc|price_desc|nearest + lat/lng), page, limit.
     */
    public function index(SearchListingsRequest $request)
    {
        $listings = $this->listingService->searchForApi(
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
            $request->user('sanctum'),
        );

        return $this->paginated($listings);
    }

    /**
     * Объявления текущего пользователя (все статусы модерации).
     * GET /api/v1/listings/my?status=pending|approved|rejected
     *
     * @authenticated
     */
    public function my(MyListingsRequest $request)
    {
        $listings = $this->listingService->myListings(
            $request->user(),
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
        );

        return $this->paginated($listings);
    }

    /**
     * Карточка объявления. Каждый просмотр (кроме владельца) увеличивает счётчик.
     * GET /api/v1/listings/{id}
     */
    public function show(Request $request, Listing $listing)
    {
        $viewer = $request->user('sanctum');

        // Чужие непромодерированные объявления недоступны
        abort_unless($listing->status === 'approved' || $viewer?->id === $listing->user_id, 404);

        $this->listingService->registerView($listing, $viewer);
        $this->listingService->loadFavoriteFlag($listing, $viewer);

        $listing->load('user', 'category', 'region', 'city', 'media', 'rejectionReason');
        // Только после load('user') — иначе агрегаты продавца затрутся
        $this->listingService->loadRating($listing);

        return response()->json([
            'data'    => new ListingResource($listing),
            'message' => 'Success',
        ]);
    }

    /**
     * Создание объявления (multipart: photos[] до 8 шт). Статус — pending.
     * POST /api/v1/listings
     *
     * @authenticated
     */
    public function store(StoreListingRequest $request)
    {
        $listing = $this->listingService->createFromApi($request->user(), $request->validated());

        return response()->json([
            'data'    => new ListingResource($listing),
            'message' => __('messages.listing_created'),
        ], 201);
    }

    /**
     * Редактирование своего объявления — возвращает его на модерацию.
     * POST|PUT /api/v1/listings/{id} (multipart — слать POST)
     *
     * @authenticated
     */
    public function update(UpdateListingRequest $request, Listing $listing)
    {
        $listing = $this->listingService->updateFromApi($listing, $request->validated());

        return response()->json([
            'data'    => new ListingResource($listing),
            'message' => __('messages.listing_updated'),
        ]);
    }

    /**
     * Удаление своего объявления вместе с медиафайлами.
     * DELETE /api/v1/listings/{id}
     *
     * @authenticated
     */
    public function destroy(Listing $listing)
    {
        $this->listingService->deleteFromApi($listing);

        return response()->json([
            'data'    => null,
            'message' => __('messages.listing_deleted'),
        ]);
    }

    /**
     * Поднятие объявления в выдаче (интервал и лимиты — во внутренних правилах).
     * POST /api/v1/listings/{id}/boost
     *
     * @authenticated
     */
    public function boost(Listing $listing)
    {
        $this->boostListingAction->execute($listing);

        return response()->json([
            'data'    => new ListingResource($listing->fresh()->load('media')),
            'message' => __('messages.listing_boosted'),
        ]);
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
            'links' => [
                'first' => $listings->url(1),
                'last'  => $listings->url($listings->lastPage()),
                'prev'  => $listings->previousPageUrl(),
                'next'  => $listings->nextPageUrl(),
            ],
        ]);
    }
}
