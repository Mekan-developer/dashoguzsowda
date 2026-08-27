<?php

namespace App\Services;

use App\Models\News;
use App\Models\User;
use App\Repositories\Interfaces\NewsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class NewsService
{
    /** Обложка новости: 16:9, ширина под мобильные, вес ≤ 100 КБ */
    private const IMAGE_ASPECT    = 16 / 9;
    private const IMAGE_MAX_WIDTH = 800;
    private const IMAGE_MAX_BYTES = 100 * 1024;

    public function __construct(
        private readonly NewsRepositoryInterface $newsRepository,
        private readonly ImageConversionService $imageConversion,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->newsRepository->paginate($filters);
    }

    /** Лента новостей для мобильного приложения (только опубликованные). */
    public function feedForApi(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->newsRepository->paginateForApi($filters, $perPage);
    }

    /** Карточка новости: неопубликованная и отложенная для приложения не существует. */
    public function findPublishedOrFail(News $news): News
    {
        abort_unless($this->newsRepository->isVisibleToPublic($news), 404);

        return $news;
    }

    public function counts(): array
    {
        $published = $this->newsRepository->countByPublished(true);
        $draft     = $this->newsRepository->countByPublished(false);

        return [
            'all'       => $published + $draft,
            'published' => $published,
            'draft'     => $draft,
        ];
    }

    public function store(array $data, User $author, ?UploadedFile $image = null, array $crop = []): News
    {
        if ($image) {
            $data['image'] = $this->storeImage($image, $crop);
        }
        $data['author_id'] = $author->id;

        return $this->newsRepository->create($data);
    }

    public function update(News $news, array $data, ?UploadedFile $image = null, array $crop = [], bool $removeImage = false): News
    {
        if ($image || $removeImage) {
            if ($news->image) {
                Storage::disk('public')->delete($news->image);
            }
            $data['image'] = $image ? $this->storeImage($image, $crop) : null;
        }

        return $this->newsRepository->update($news, $data);
    }

    private function storeImage(UploadedFile $image, array $crop): string
    {
        return $this->imageConversion->toWebp(
            $image,
            'news',
            aspect: self::IMAGE_ASPECT,
            cropX: (float) ($crop['crop_x'] ?? 50),
            cropY: (float) ($crop['crop_y'] ?? 50),
            maxWidth: self::IMAGE_MAX_WIDTH,
            maxBytes: self::IMAGE_MAX_BYTES,
        );
    }

    public function publish(News $news): void
    {
        $this->newsRepository->update($news, [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function unpublish(News $news): void
    {
        $this->newsRepository->update($news, [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function delete(News $news): void
    {
        if ($news->image) {
            Storage::disk('public')->delete($news->image);
        }
        $this->newsRepository->delete($news);
    }
}
