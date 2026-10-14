<?php

namespace App\Services\Video;

class FfprobeVideoProbe implements VideoProbeInterface
{
    public function duration(string $absolutePath): ?float
    {
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
            escapeshellarg($absolutePath),
        );

        exec($cmd, $output, $code);

        $duration = trim(implode("\n", $output));

        if ($code !== 0 || ! is_numeric($duration)) {
            return null;
        }

        return (float) $duration;
    }

    public function streams(string $absolutePath): ?array
    {
        $cmd = sprintf(
            'ffprobe -v error -show_entries stream=codec_type,width,height -of json %s 2>&1',
            escapeshellarg($absolutePath),
        );

        exec($cmd, $output, $code);

        $streams = json_decode(implode("\n", $output), true)['streams'] ?? null;

        if ($code !== 0 || ! is_array($streams)) {
            return null;
        }

        $video = collect($streams)->first(fn ($s) => ($s['codec_type'] ?? null) === 'video' && ! empty($s['width']));

        if ($video === null) {
            return null;
        }

        return [
            'width'     => (int) $video['width'],
            'height'    => (int) $video['height'],
            'has_audio' => collect($streams)->contains(fn ($s) => ($s['codec_type'] ?? null) === 'audio'),
        ];
    }

    /** Проверяем наличие бинарника один раз за запрос */
    public function available(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        exec('ffprobe -version 2>&1', $output, $code);

        return $available = ($code === 0);
    }
}
