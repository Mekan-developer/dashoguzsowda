<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchNewsRequest;
use App\Http\Resources\Api\V1\NewsResource;
use App\Models\News;
use App\Services\NewsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $newsService,
    ) {}

    /**
     * Опубликованные новости для мобильного приложения.
     * GET /api/v1/news?type=regular|ad&page=1&limit=20
     */
    public function index(SearchNewsRequest $request)
    {
        return $this->paginated($this->newsService->feedForApi(
            $request->validated(),
            (int) ($request->validated('limit') ?? 20),
        ));
    }

    /**
     * Одна новость.
     * GET /api/v1/news/{id}
     */
    public function show(News $news)
    {
        return response()->json([
            'data' => new NewsResource($this->newsService->findPublishedOrFail($news)),
        ]);
    }

    private function paginated(LengthAwarePaginator $news)
    {
        return response()->json([
            'data' => NewsResource::collection($news->items()),
            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page'    => $news->lastPage(),
                'per_page'     => $news->perPage(),
                'total'        => $news->total(),
            ],
            'links' => [
                'first' => $news->url(1),
                'last'  => $news->url($news->lastPage()),
                'prev'  => $news->previousPageUrl(),
                'next'  => $news->nextPageUrl(),
            ],
        ]);
    }
}
