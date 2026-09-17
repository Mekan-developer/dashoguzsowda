<?php

namespace App\Repositories;

use App\Models\Tariff;
use App\Models\Video;
use App\Models\VideoLike;
use App\Repositories\Concerns\BuildsLikeSearch;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VideoRepository implements VideoRepositoryInterface
{
    use BuildsLikeSearch;

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $page = Video::with('user.tariff', 'rejectionReason', 'category')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $term = self::likeTerm($s);
                $q->where(fn ($w) => $w
                    ->where('title', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u
                        ->where(fn ($n) => $n
                            ->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term))));
            })
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $this->attachAdminMeta($page->items());

        return $page;
    }

    /** Публичная лента для мобильного приложения — только одобренные ролики */
    public function paginateForApi(array $filters, int $perPage = 20, ?int $viewerId = null): LengthAwarePaginator
    {
        return Video::with('user', 'category')
            ->where('status', 'approved')
            ->when($viewerId, fn ($q, $id) => $q->withExists([
                'likes as is_liked' => fn ($l) => $l->where('user_id', $id),
            ]))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', self::likeTerm($s)))
            ->when($filters['tag'] ?? null, fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateByUser(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Video::with('rejectionReason', 'category')
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Video
    {
        return Video::with('user', 'rejectionReason', 'category')->findOrFail($id);
    }

    /**
     * Карточка модерации: те же вычисляемые поля, что и в списке
     * (preview_url / video_url / tariff_usage) — иначе странице нечего играть.
     */
    public function findForAdmin(Video $video): Video
    {
        $video->load('user.tariff', 'rejectionReason', 'category');

        $this->attachAdminMeta([$video]);

        return $video;
    }

    public function create(array $data): Video
    {
        return Video::create($data);
    }

    public function update(Video $video, array $data): Video
    {
        $video->update($data);
        return $video->fresh();
    }

    public function delete(Video $video): void
    {
        $video->delete();
    }

    public function countAll(): int
    {
        return Video::count();
    }

    public function countByStatus(string $status): int
    {
        return Video::where('status', $status)->count();
    }

    public function countByUserAndStatuses(int $userId, array $statuses): int
    {
        return Video::where('user_id', $userId)->whereIn('status', $statuses)->count();
    }

    public function sumLikes(): int
    {
        return (int) Video::sum('likes_count');
    }

    /**
     * Лайк-переключатель: один лайк на пользователя (unique user_id+video_id),
     * денормализованный likes_count меняется атомарно вместе со строкой лайка.
     *
     * @return array{is_liked: bool, likes_count: int}
     */
    public function toggleLike(Video $video, int $userId): array
    {
        return DB::transaction(function () use ($video, $userId) {
            $existing = VideoLike::where('video_id', $video->id)->where('user_id', $userId)->first();

            if ($existing) {
                $existing->delete();
                Video::whereKey($video->id)->where('likes_count', '>', 0)->decrement('likes_count');
                $isLiked = false;
            } else {
                try {
                    VideoLike::create(['video_id' => $video->id, 'user_id' => $userId]);
                    Video::whereKey($video->id)->increment('likes_count');
                } catch (UniqueConstraintViolationException) {
                    // Параллельный повторный лайк — состояние уже учтено
                }
                $isLiked = true;
            }

            return [
                'is_liked'    => $isLiked,
                'likes_count' => (int) Video::whereKey($video->id)->value('likes_count'),
            ];
        });
    }

    public function loadLikeFlag(Video $video, ?int $viewerId): void
    {
        if ($viewerId === null) {
            return;
        }

        $video->loadExists([
            'likes as is_liked' => fn ($l) => $l->where('user_id', $viewerId),
        ]);
    }

    public function incrementViews(Video $video): void
    {
        $video->increment('views');
    }

    /**
     * Для таблицы админки: URL превью/видео и «Тариф Про · 12/30» по автору.
     * Занято считается как pending+approved — так же, как в CheckVideoLimitAction.
     *
     * @param Video[] $videos
     */
    private function attachAdminMeta(array $videos): void
    {
        $userIds = collect($videos)->pluck('user_id')->unique()->values();

        $used = $userIds->isEmpty() ? collect() : Video::whereIn('user_id', $userIds)
            ->whereIn('status', ['pending', 'approved'])
            ->selectRaw('user_id, COUNT(*) AS used')
            ->groupBy('user_id')
            ->pluck('used', 'user_id');

        // activeTariff() дёргал бы запрос бесплатного тарифа на каждую строку
        $freeTariff = Tariff::where('is_free', true)->first();

        foreach ($videos as $video) {
            $video->setAttribute('preview_url', $video->preview_path ? Storage::disk('public')->url($video->preview_path) : null);
            $video->setAttribute('video_url', Storage::disk('public')->url($video->processed_path ?? $video->path));

            if (! $video->user) {
                continue;
            }

            // Бесплатный тариф бессрочен (tariff_ends_at = null) — его отдаёт та же ветка $freeTariff
            $tariff = ($video->user->tariff_id && $video->user->tariff_ends_at?->isFuture())
                ? $video->user->tariff
                : $freeTariff;

            $video->setAttribute('tariff_usage', [
                'name_ru' => $tariff?->name_ru,
                'name_tk' => $tariff?->name_tk,
                'used'    => (int) ($used[$video->user_id] ?? 0),
                'limit'   => $tariff?->videos_limit,
            ]);
        }
    }
}
