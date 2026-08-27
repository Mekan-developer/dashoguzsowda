<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SearchPopularService;

class SearchPopularController extends Controller
{
    public function __construct(
        private readonly SearchPopularService $searchPopularService,
    ) {}

    /**
     * Популярные поисковые запросы по всему сайту.
     * GET /api/v1/search/popular
     */
    public function index()
    {
        return response()->json([
            'data' => $this->searchPopularService->popular(),
        ]);
    }
}
