<?php

namespace App\Services;

use App\Actions\CheckVideoLimitAction;
use App\Jobs\ProcessVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use App\Services\Video\VideoProbeInterface;
use App\Services\Video\VideoUploadManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoService
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly CheckVideoLimitAction $checkVideoLimitAction,
        private readonly VideoUploadManager $uploads,
        private readonly VideoProbeInterface $probe,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->videoRepository->paginate($filters);
    }

    public function counts(): array
    {
        return [
            'pending'  => $this->videoRepository->countByStatus('pending'),
            'approved' => $this->videoRepository->countByStatus('approved'),
            'rejected' => $this->videoRepository->countByStatus('rejected'),
        ];
    }

    public function approve(Video $video): void
    {
        $this->videoRepository->update($video, [
            'status'              => 'approved',
            'rejection_reason_id' => null,
        ]);
    }

    public function reject(Video $video, int $rejectionReasonId): void
    {
        $this->videoRepository->update($video, [
            'status'              => 'rejected',
            'rejection_reason_id' => $rejectionReasonId,
        ]);
    }

    /** Удаляет ролик вместе с файлами (оригинал + сжатая версия + превью-кадр) */
    public function delete(Video $video): void
    {
        // Файлы ролика лежат в собственной папке videos/{uuid} (см. createFromApi)
        $dir = dirname($video->path);
        if (! in_array($dir, ['.', '', 'videos'], true)) {
            Storage::disk('public')->deleteDirectory($dir);
        }

        // Старые записи могли хранить файлы плоско — подчищаем и их
        Storage::disk('public')->delete(array_filter([
            $video->path, $video->processed_path, $video->preview_path,
        ]));

        $this->videoRepository->delete($video);
    }

    // ─── Mobile API (ТЗ §7) ─────────────────────────────────────────────────

    /** Публичная вертикальная лента: только одобренные ролики */
    public function feedForApi(array $filters, int $perPage = 20, ?User $viewer = null): LengthAwarePaginator
    {
        return $this->videoRepository->paginateForApi($filters, $perPage, $viewer?->id);
    }

    public function myVideos(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->videoRepository->paginateByUser($user->id, $filters, $perPage);
    }

    /**
     * Загрузка ролика из приложения: квота тарифа уже проверена экшеном,
     * длительность (≤60 сек) — валидацией. Файл уходит в очередь на сжатие.
     */
    public function createFromApi(User $user, array $data, int $durationSeconds): Video
    {
        $this->checkVideoLimitAction->execute($user);

        /** @var UploadedFile $file */
        $file = $data['video'];

        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $path      = $file->storeAs('videos/'.Str::uuid(), 'original.'.$extension, 'public');

        return $this->persistVideo($user, $path, $data, $durationSeconds);
    }

    // ─── Chunked / streaming загрузка (файл любого размера) ──────────────────

    /**
     * Открывает сессию загрузки. Квота тарифа проверяется здесь (fail-fast),
     * чтобы не гнать мегабайты частей ради заведомого 403.
     *
     * @return array{upload_id:string, chunk_size:int, max_bytes:int|null}
     */
    public function initChunkedUpload(User $user, array $data): array
    {
        $this->checkVideoLimitAction->execute($user);

        $extension = $this->resolveExtension($data['filename'] ?? null, $data['extension'] ?? null);

        $uploadId = $this->uploads->create($user->id, $data['title'], $data['tags'] ?? [], $extension);

        return [
            'upload_id'  => $uploadId,
            'chunk_size' => (int) config('video.recommended_chunk_bytes', 5 * 1024 * 1024),
            'max_bytes'  => config('video.max_total_bytes'),
        ];
    }

    /**
     * Дописывает очередную часть в собираемый файл (потоком).
     *
     * @param  resource  $input  поток тела запроса
     * @return array{upload_id:string, bytes_received:int, chunks_received:int}
     */
    public function appendChunk(User $user, string $uploadId, $input, ?int $index): array
    {
        $meta = $this->requireOwnedSession($user, $uploadId);

        // index (если прислан) должен совпадать с числом уже принятых частей
        if ($index !== null && $index !== $meta['chunks']) {
            abort(422, __('messages.video_chunk_out_of_order'));
        }

        $result = $this->uploads->append($uploadId, $input);

        $max = config('video.max_total_bytes');
        if ($max !== null && $result['bytes'] > (int) $max) {
            $this->uploads->discard($uploadId);
            abort(422, __('messages.video_too_large'));
        }

        return [
            'upload_id'       => $uploadId,
            'bytes_received'  => $result['bytes'],
            'chunks_received' => $result['chunks'],
        ];
    }

    /**
     * Финализирует загрузку: проверяет собранный файл (ffprobe: валидность + ≤60 сек),
     * переносит его в публичное хранилище, создаёт ролик и ставит сжатие в очередь.
     */
    public function completeChunkedUpload(User $user, string $uploadId): Video
    {
        $meta = $this->requireOwnedSession($user, $uploadId);

        if (($meta['bytes'] ?? 0) <= 0) {
            $this->uploads->discard($uploadId);
            abort(422, __('messages.video_upload_incomplete'));
        }

        // Нет ffprobe — misconfiguration сервера, а не ошибка пользователя
        abort_unless($this->probe->available(), 503, __('messages.video_service_unavailable'));

        $absolute = $this->uploads->absolutePath($uploadId);
        $duration = $this->probe->duration($absolute);

        if ($duration === null) {
            $this->uploads->discard($uploadId);
            abort(422, __('messages.video_unreadable'));
        }

        if ($duration > (int) config('video.max_duration_seconds', 60)) {
            $this->uploads->discard($uploadId);
            abort(422, __('messages.video_too_long'));
        }

        // Квота могла измениться за время заливки — перепроверяем перед созданием
        $this->checkVideoLimitAction->execute($user);

        $dir     = 'videos/'.Str::uuid();
        $relPath = $dir.'/original.'.$meta['extension'];

        $public = Storage::disk('public');
        $public->makeDirectory($dir);
        // Перенос (rename) в пределах той же ФС storage/app — без загрузки в память
        File::move($absolute, $public->path($relPath));

        $this->uploads->discard($uploadId);

        return $this->persistVideo($user, $relPath, [
            'title' => $meta['title'],
            'tags'  => $meta['tags'] ?? [],
        ], (int) round($duration));
    }

    /** Отмена незавершённой загрузки — освобождает temp-файл и сессию. */
    public function abortChunkedUpload(User $user, string $uploadId): void
    {
        $this->requireOwnedSession($user, $uploadId);
        $this->uploads->discard($uploadId);
    }

    /** Общая финализация записи ролика (для single-shot и chunked путей) */
    private function persistVideo(User $user, string $path, array $data, int $durationSeconds): Video
    {
        $video = $this->videoRepository->create([
            'user_id'          => $user->id,
            'title'            => $data['title'],
            'tags'             => $data['tags'] ?? [],
            'path'             => $path,
            'duration_seconds' => $durationSeconds,
            'status'           => 'pending',
        ]);

        ProcessVideoJob::dispatch($video->id);

        return $this->videoRepository->find($video->id);
    }

    /** Сессия существует и принадлежит текущему пользователю */
    private function requireOwnedSession(User $user, string $uploadId): array
    {
        $meta = $this->uploads->meta($uploadId);

        abort_if($meta === null, 404, __('messages.video_upload_session_not_found'));
        abort_if($meta['user_id'] !== $user->id, 403);

        return $meta;
    }

    /** Расширение из имени файла/явного поля, приведённое к белому списку */
    private function resolveExtension(?string $filename, ?string $extension): string
    {
        $ext = strtolower($extension ?: pathinfo((string) $filename, PATHINFO_EXTENSION) ?: 'mp4');

        return in_array($ext, config('video.allowed_extensions', ['mp4']), true) ? $ext : 'mp4';
    }

    /** @return array{is_liked: bool, likes_count: int} */
    public function toggleLike(Video $video, User $user): array
    {
        return $this->videoRepository->toggleLike($video, $user->id);
    }

    public function loadLikeFlag(Video $video, ?User $viewer): void
    {
        $this->videoRepository->loadLikeFlag($video, $viewer?->id);
    }

    /** Счётчик просмотров: атомарный инкремент, свои просмотры не считаются */
    public function registerView(Video $video, ?User $viewer): void
    {
        if ($viewer && $viewer->id === $video->user_id) {
            return;
        }

        $this->videoRepository->incrementViews($video);
    }
}
