<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSearchRecentRequest;
use App\Services\SearchHistoryService;
use Illuminate\Http\Request;

/**
 * История поиска: до 8 последних запросов пользователя, новые сверху.
 * Гость хранит историю только на устройстве — эти роуты под auth:sanctum.
 */
class SearchRecentController extends Controller
{
    public function __construct(
        private readonly SearchHistoryService $searchHistory,
    ) {}

    /**
     * GET /api/v1/search/recent
     *
     * @authenticated
     */
    public function index(Request $request)
    {
        return response()->json([
            'data' => $this->searchHistory->recent($request->user()),
        ]);
    }

    /**
     * POST /api/v1/search/recent
     *
     * @authenticated
     */
    public function store(StoreSearchRecentRequest $request)
    {
        return response()->json([
            'data' => $this->searchHistory->remember($request->user(), $request->validated('query')),
        ]);
    }

    /**
     * DELETE /api/v1/search/recent
     *
     * @authenticated
     */
    public function destroy(Request $request)
    {
        $this->searchHistory->clear($request->user());

        return response()->json([
            'data' => ['cleared' => true],
        ]);
    }
}
