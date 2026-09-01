<?php

namespace App\Repositories;

use App\Models\Review;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Review::with('user', 'listing', 'targetUser', 'rejectionReason')
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('text', 'like', "%$s%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                  ->orWhereHas('listing', fn($l) => $l->where('title', 'like', "%$s%"))
                  ->orWhereHas('targetUser', fn($u) => $u->where('name', 'like', "%$s%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Публичная лента отзывов объекта. Автор нужен всегда — мобилка рисует
     * имя и аватар рядом с текстом, поэтому грузим его сразу.
     */
    public function paginateApproved(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->approvedQuery($filters)->with('user');

        match ($filters['sort'] ?? 'latest') {
            // Отзывы без оценки уезжают в конец, чтобы не занимать верх выдачи
            'rating_desc' => $query->orderByRaw('rating IS NULL')->orderByDesc('rating'),
            'rating_asc'  => $query->orderByRaw('rating IS NULL')->orderBy('rating'),
            default       => $query,
        };

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function paginateByAuthor(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Review::with('listing', 'targetUser', 'rejectionReason')
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Средняя оценка и разбивка по звёздам — одним запросом с группировкой.
     * Отзыв без rating считается в count, но не влияет на среднее.
     */
    public function summary(array $filters): array
    {
        $rows = $this->approvedQuery($filters)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $breakdown = array_fill_keys(range(1, 5), 0);
        $count = 0;
        $ratedCount = 0;
        $sum = 0;

        foreach ($rows as $rating => $total) {
            $total = (int) $total;
            $count += $total;

            // Ключ отзывов без оценки приходит пустой строкой (NULL в SQL)
            if ($rating === '' || $rating === null) {
                continue;
            }

            $breakdown[(int) $rating] = $total;
            $ratedCount += $total;
            $sum += (int) $rating * $total;
        }

        return [
            'count'       => $count,
            'rated_count' => $ratedCount,
            'average'     => $ratedCount > 0 ? round($sum / $ratedCount, 2) : null,
            'breakdown'   => $breakdown,
        ];
    }

    public function find(int $id): Review
    {
        return Review::with('user', 'listing', 'targetUser', 'rejectionReason')->findOrFail($id);
    }

    public function create(array $data): Review
    {
        return Review::create($data);
    }

    public function update(Review $review, array $data): Review
    {
        $review->update($data);
        return $review->fresh();
    }

    public function countPending(): int
    {
        return Review::where('status', 'pending')->count();
    }

    public function countByStatus(string $status): int
    {
        return Review::where('status', $status)->count();
    }

    /** Одобренные отзывы одного объекта: объявления либо пользователя */
    private function approvedQuery(array $filters): Builder
    {
        return Review::query()
            ->where('status', 'approved')
            ->when($filters['listing_id'] ?? null, fn ($q, $id) => $q->where('listing_id', $id))
            ->when($filters['target_user_id'] ?? null, fn ($q, $id) => $q->where('target_user_id', $id));
    }
}
