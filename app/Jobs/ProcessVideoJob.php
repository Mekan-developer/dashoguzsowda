<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\Video\HlsTranscoder;
use App\Services\Video\VideoProbeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // Держать меньше REDIS_QUEUE_RETRY_AFTER (config/queue.php) и вровень
    // с timeout супервизора media в config/horizon.php
    public int $timeout = 900;

    public function __construct(
        private readonly int $videoId,
    ) {
        $this->onQueue('media');
    }

    /**
     * Из оригинала собирает всё, что отдаётся клиентам, в папке ролика
     * videos/{uuid}/ (см. VideoService::createFromApi):
     *   processed.mp4 — H.264, ширина ≤720; поле `video` для старых клиентов;
     *   hls/master.m3u8 — адаптивный HLS (HlsTranscoder);
     *   preview.jpg — кадр для постера.
     * Когда всё готово, оригинал удаляется: он бывает до гигабайта, а нужен
     * только как вход для обработки.
     */
    public function handle(VideoProbeInterface $probe, HlsTranscoder $hls): void
    {
        $video = Video::findOrFail($this->videoId);
        $disk  = Storage::disk('public');
        $dir   = dirname($video->path);

        $processedRel = $dir.'/processed.mp4';
        $previewRel   = $dir.'/preview.jpg';
        $hlsRel       = $dir.'/hls/'.HlsTranscoder::MASTER;

        // Оригинала нет — ролик уже обрабатывался; пересобрать можно из mp4
        $input = $disk->exists($video->path)
            ? $disk->path($video->path)
            : ($video->processed_path && $disk->exists($video->processed_path) ? $disk->path($video->processed_path) : null);

        if ($input === null) {
            Log::error('Video source missing', ['video_id' => $video->id]);
            $this->fail('Video source missing');
            return;
        }

        $hasOriginal = $input === $disk->path($video->path);

        if ($hasOriginal) {
            $result = Process::timeout($this->timeout)->run([
                'ffmpeg', '-y', '-i', $input,
                '-vf', 'scale=trunc(min(iw\,720)/2)*2:-2',
                '-c:v', 'libx264', '-preset', 'fast', '-crf', '23', '-pix_fmt', 'yuv420p',
                '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart',
                $disk->path($processedRel),
            ]);

            if ($result->failed()) {
                Log::error('FFmpeg failed', ['video_id' => $video->id, 'code' => $result->exitCode(), 'output' => $result->errorOutput()]);
                $this->fail('FFmpeg processing failed');
                return;
            }
        }

        // Превью-кадр не критичен: в админке есть заглушка с иконкой play
        $preview = Process::timeout(60)->run([
            'ffmpeg', '-y', '-ss', '0.5', '-i', $input, '-frames:v', '1',
            '-vf', 'scale=trunc(min(iw\,360)/2)*2:-2',
            $disk->path($previewRel),
        ]);

        if ($preview->failed()) {
            Log::warning('FFmpeg preview frame failed', ['video_id' => $video->id, 'output' => $preview->errorOutput()]);
        }

        // HLS тоже не критичен: без него клиент играет processed.mp4.
        // Оригинал при неудаче остаётся — videos:build-hls пересоберёт.
        $hlsReady = true;

        try {
            $hls->transcode($input, $disk->path(dirname($hlsRel)));
        } catch (RuntimeException $e) {
            $hlsReady = false;
            Log::error('HLS transcode failed', ['video_id' => $video->id, 'error' => $e->getMessage()]);
        }

        $video->update([
            'processed_path'   => $hasOriginal ? $processedRel : $video->processed_path,
            'hls_path'         => $hlsReady ? $hlsRel : null,
            'preview_path'     => $preview->successful() ? $previewRel : $video->preview_path,
            'is_processed'     => true,
            // Подстраховка: если при загрузке длительность не определилась
            'duration_seconds' => $video->duration_seconds ?: (int) round($probe->duration($input) ?? 0),
        ]);

        // `path` остаётся в записи: по нему VideoService::delete находит папку ролика
        if ($hasOriginal && $hlsReady && $video->path !== $processedRel) {
            $disk->delete($video->path);
        }
    }
}
