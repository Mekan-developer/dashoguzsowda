<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFavoriteRequest;
use App\Http\Resources\Api\V1\FavoriteResource;
use App\Services\FavoriteService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
    ) {}

    /**
     * Список избранного текущего пользователя (хранится в профиле).
     * GET /api/v1/favorites
     *
     * @authenticated
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return $this->paginated($this->favoriteService->listForUser($user->id, withWholesale: $user->seesWholesale()));
    }

    /**
     * Добавить объявление в избранное (идемпотентно).
     * POST /api/v1/favorites
     *
     * @authenticated
     */
    public function store(StoreFavoriteRequest $request)
    {
        $favorite = $this->favoriteService->add($request->user()->id, $request->validated('listing_id'));

        return response()->json([
            'data'    => new FavoriteResource($favorite),
            'message' => __('messages.favorite_added'),
        ], 201);
    }

    /**
     * Удалить объявление из избранного (идемпотентно).
     * DELETE /api/v1/favorites/{listing}
     *
     * @authenticated
     */
    public function destroy(Request $request, int $listing)
    {
        $this->favoriteService->remove($request->user()->id, $listing);

        return response()->json([
            'message' => __('messages.favorite_removed'),
        ]);
    }

    private function paginated(LengthAwarePaginator $favorites)
    {
        return response()->json([
            'data' => FavoriteResource::collection($favorites->items()),
            'meta' => [
                'current_page' => $favorites->currentPage(),
                'last_page'    => $favorites->lastPage(),
                'per_page'     => $favorites->perPage(),
                'total'        => $favorites->total(),
            ],
        ]);
    }
}
