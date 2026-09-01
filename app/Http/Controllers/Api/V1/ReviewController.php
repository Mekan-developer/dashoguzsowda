<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexReviewsRequest;
use App\Http\Requests\Api\V1\MyReviewsRequest;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Listing;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Оставить отзыв (об объявлении или о пользователе) — уходит на модерацию.
     * POST /api/v1/reviews
     *
     * @authenticated
     */
    public function store(StoreReviewRequest $request)
    {
        $review = $this->reviewService->createFromApi($request->user(), $request->validated());

        return response()->json([
            'data'    => new ReviewResource($review),
            'message' => __('messages.review_submitted'),
        ], 201);
    }

    /**
     * Отзывы об объявлении — только прошедшие модерацию, публично.
     * GET /api/v1/listings/{listing}/reviews?sort=latest|rating_desc|rating_asc
     *
     * Средняя оценка и разбивка по звёздам приходят в meta.rating —
     * отдельный запрос за сводкой мобилке делать не нужно.
     */
    public function forListing(IndexReviewsRequest $request, Listing $listing)
    {
        // Отзывы существуют только у публичного объявления — как и сама карточка
        abort_unless($listing->status === 'approved', 404);

        $filters = $request->validated();

        return $this->paginated(
            $this->reviewService->listForListing($listing, $filters, (int) ($filters['limit'] ?? 20)),
            $this->reviewService->summaryForListing($listing),
        );
    }

    /**
     * Отзывы о продавце — только прошедшие модерацию, публично.
     * GET /api/v1/users/{user}/reviews?sort=latest|rating_desc|rating_asc
     */
    public function forUser(IndexReviewsRequest $request, User $user)
    {
        $filters = $request->validated();

        return $this->paginated(
            $this->reviewService->listForUser($user, $filters, (int) ($filters['limit'] ?? 20)),
            $this->reviewService->summaryForUser($user),
        );
    }

    /**
     * Свои отзывы во всех статусах — пользователь видит, что ушло на модерацию,
     * что одобрено и по какой причине отклонено.
     * GET /api/v1/reviews/my?status=pending|approved|rejected
     *
     * @authenticated
     */
    public function my(MyReviewsRequest $request)
    {
        $filters = $request->validated();

        return $this->paginated(
            $this->reviewService->myReviews($request->user(), $filters, (int) ($filters['limit'] ?? 20)),
        );
    }

    private function paginated(LengthAwarePaginator $reviews, ?array $summary = null)
    {
        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
                ...$summary !== null ? ['rating' => $summary] : [],
            ],
        ]);
    }
}
