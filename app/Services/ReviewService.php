<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepository,
    ) {}

    public function createFromApi(User $user, array $data): Review
    {
        return $this->reviewRepository->create([
            'user_id'             => $user->id,
            'listing_id'          => $data['listing_id'] ?? null,
            'target_user_id'      => $data['target_user_id'] ?? null,
            'text'                => $data['text'],
            'rating'              => $data['rating'] ?? null,
            'status'              => 'pending',
            'rejection_reason_id' => null,
        ]);
    }

    /** Публичная лента отзывов объявления — только прошедшие модерацию */
    public function listForListing(Listing $listing, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->reviewRepository->paginateApproved(
            [...$filters, 'listing_id' => $listing->id],
            $perPage,
        );
    }

    /** Публичная лента отзывов о продавце */
    public function listForUser(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->reviewRepository->paginateApproved(
            [...$filters, 'target_user_id' => $user->id],
            $perPage,
        );
    }

    /** Отзывы, оставленные пользователем: он видит статус модерации своих */
    public function myReviews(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->reviewRepository->paginateByAuthor($user->id, $filters, $perPage);
    }

    /** @return array{count: int, rated_count: int, average: float|null, breakdown: array<int, int>} */
    public function summaryForListing(Listing $listing): array
    {
        return $this->reviewRepository->summary(['listing_id' => $listing->id]);
    }

    /** @return array{count: int, rated_count: int, average: float|null, breakdown: array<int, int>} */
    public function summaryForUser(User $user): array
    {
        return $this->reviewRepository->summary(['target_user_id' => $user->id]);
    }
}
