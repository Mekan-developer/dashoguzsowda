<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Обучающие материалы админки — готовый статический сайт в storage/app/lessons.
 *
 * В git и в образ он не попадает (17 МБ озвучки), на сервере лежит в томе
 * storage_app. Из public/ убран намеренно: материалы внутренние, а public/
 * nginx отдаёт любому, поэтому файлы идут через роут с проверкой роли.
 */
class LessonService
{
    private const DISK = 'lessons';

    /**
     * Он же белый список: всё, чего здесь нет, не отдаётся. Тип задан явно —
     * finfo определяет css и js как text/plain, и браузер их не применит.
     */
    private const MIME_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'mp3'  => 'audio/mpeg',
    ];

    public function isInstalled(): bool
    {
        return Storage::disk(self::DISK)->exists('index.html');
    }

    /**
     * Абсолютный путь и тип файла. null — файла нет, тип не из списка или путь
     * ведёт за пределы папки с материалами.
     *
     * @return array{path: string, mime: string}|null
     */
    public function resolve(string $relativePath): ?array
    {
        $mime = self::MIME_TYPES[strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))] ?? null;
        if ($mime === null) {
            return null;
        }

        $root = realpath(Storage::disk(self::DISK)->path(''));
        $path = realpath(Storage::disk(self::DISK)->path($relativePath));

        if ($root === false || $path === false || ! is_file($path) || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime];
    }
}
