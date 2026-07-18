<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Сессия chunked-загрузки видео: метаданные — в кэше (Redis, TTL),
 * недособранный файл — на диске `temp_disk`. Части дописываются потоком,
 * ни файл, ни часть целиком в память не грузятся.
 */
class VideoUploadManager
{
    private const CACHE_PREFIX = 'video-upload:';

    /** Заводит новую сессию и пустой temp-файл. Возвращает upload_id. */
    public function create(int $userId, string $title, array $tags, string $extension): string
    {
        $uploadId = (string) Str::uuid();

        Cache::put($this->key($uploadId), [
            'user_id'   => $userId,
            'title'     => $title,
            'tags'      => array_values($tags),
            'extension' => $extension,
            'bytes'     => 0,
            'chunks'    => 0,
        ], $this->ttl());

        // Пустой файл — чтобы append был предсказуем даже для первого чанка
        $this->disk()->put($this->relPath($uploadId), '');

        return $uploadId;
    }

    /** Метаданные сессии или null, если её нет/истекла. */
    public function meta(string $uploadId): ?array
    {
        return Cache::get($this->key($uploadId));
    }

    /**
     * Стриминг-дозапись части в конец temp-файла.
     *
     * @param  resource  $input  поток тела запроса (php://input)
     * @return array{bytes:int, chunks:int}
     */
    public function append(string $uploadId, $input): array
    {
        $meta = $this->meta($uploadId);

        $target = fopen($this->absolutePath($uploadId), 'ab');
        if ($target === false) {
            throw new \RuntimeException("Cannot open temp upload file for {$uploadId}");
        }

        try {
            $written = stream_copy_to_stream($input, $target);
        } finally {
            fclose($target);
        }

        $meta['bytes']  += (int) $written;
        $meta['chunks'] += 1;

        Cache::put($this->key($uploadId), $meta, $this->ttl());

        return ['bytes' => $meta['bytes'], 'chunks' => $meta['chunks']];
    }

    /** Абсолютный путь temp-файла (для ffprobe и переноса). */
    public function absolutePath(string $uploadId): string
    {
        return $this->disk()->path($this->relPath($uploadId));
    }

    /** Удаляет temp-файл и метаданные сессии. */
    public function discard(string $uploadId): void
    {
        $this->disk()->delete($this->relPath($uploadId));
        Cache::forget($this->key($uploadId));
    }

    private function disk()
    {
        return Storage::disk(config('video.temp_disk', 'local'));
    }

    private function relPath(string $uploadId): string
    {
        return trim(config('video.temp_dir', 'temp/video-uploads'), '/').'/'.$uploadId.'.part';
    }

    private function key(string $uploadId): string
    {
        return self::CACHE_PREFIX.$uploadId;
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addHours((int) config('video.session_ttl_hours', 24));
    }
}
