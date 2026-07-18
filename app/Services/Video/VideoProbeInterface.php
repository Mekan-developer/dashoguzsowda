<?php

namespace App\Services\Video;

interface VideoProbeInterface
{
    /**
     * Длительность видеофайла в секундах.
     * null — если файл прочитан, но не является валидным видео.
     */
    public function duration(string $absolutePath): ?float;

    /**
     * Доступен ли на сервере инструмент анализа видео (ffprobe).
     * false — серверная misconfiguration, а не ошибка пользователя:
     * вызывающий код должен отдать 503, а не 422 video_unreadable.
     */
    public function available(): bool;
}
