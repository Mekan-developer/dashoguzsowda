<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Подчищает брошенные временные файлы chunked-загрузки видео
 * (сессии, которые начали, но не завершили). Метаданные в кэше истекают
 * сами по TTL, а вот temp-файлы на диске надо удалять этой командой.
 */
class PruneVideoUploads extends Command
{
    protected $signature = 'videos:prune-uploads';

    protected $description = 'Удаляет устаревшие временные файлы незавершённых chunked-загрузок видео';

    public function handle(): int
    {
        $disk = Storage::disk(config('video.temp_disk', 'local'));
        $dir  = trim(config('video.temp_dir', 'temp/video-uploads'), '/');
        $cutoff = now()->subHours((int) config('video.session_ttl_hours', 24))->getTimestamp();

        $deleted = 0;

        foreach ($disk->files($dir) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Удалено устаревших загрузок: {$deleted}");

        return self::SUCCESS;
    }
}
