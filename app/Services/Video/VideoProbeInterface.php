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
     * Размеры кадра и наличие звука — по ним строится лестница качеств HLS.
     * Ширина и высота — как в контейнере, без учёта поворота: лестнице нужна
     * только короткая сторона, а она от поворота не зависит.
     * null — видеопотока в файле нет.
     *
     * @return array{width:int, height:int, has_audio:bool}|null
     */
    public function streams(string $absolutePath): ?array;

    /**
     * Доступен ли на сервере инструмент анализа видео (ffprobe).
     * false — серверная misconfiguration, а не ошибка пользователя:
     * вызывающий код должен отдать 503, а не 422 video_unreadable.
     */
    public function available(): bool;
}
