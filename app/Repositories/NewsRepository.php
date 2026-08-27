<?php

namespace App\Repositories;

use App\Models\News;
use App\Repositories\Interfaces\NewsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NewsRepository implements NewsRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return News::with('author')
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when(
                isset($filters['published']) && $filters['published'] !== '',
                fn($q) => $q->where('is_published', filter_var($filters['published'], FILTER_VALIDATE_BOOLEAN)),
            )
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('title_ru', 'like', "%$s%"))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Публичная лента для мобильного приложения: только опубликованные и уже
     * наступившие новости. published_at может быть null (админ включил флаг
     * в форме, минуя кнопку «Опубликовать») — такие показываем сразу.
     */
    public function paginateForApi(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return News::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Та же видимость, что и в ленте — для карточки одной новости. */
    public function isVisibleToPublic(News $news): bool
    {
        return $news->is_published
            && ($news->published_at === null || ! $news->published_at->isFuture());
    }

    public function countByPublished(bool $published): int
    {
        return News::where('is_published', $published)->count();
    }

    public function find(int $id): News
    {
        return News::findOrFail($id);
    }

    public function create(array $data): News
    {
        return News::create($data);
    }

    public function update(News $news, array $data): News
    {
        $news->update($data);
        return $news->fresh();
    }

    public function delete(News $news): void
    {
        $news->delete();
    }
}
