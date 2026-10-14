<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoJob;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Досборка HLS для роликов, загруженных до перехода на HLS, и тех, где
 * нарезка не удалась. Ставит обычный ProcessVideoJob в очередь media —
 * он пересоберёт всё из оригинала, а без оригинала — из processed.mp4.
 * Запускается вручную, один раз после деплоя и по необходимости.
 */
class BuildVideoHls extends Command
{
    protected $signature = 'videos:build-hls';

    protected $description = 'Ставит в очередь нарезку HLS для обработанных роликов без него';

    public function handle(VideoRepositoryInterface $videos): int
    {
        $ids = $videos->idsWithoutHls();

        foreach ($ids as $id) {
            ProcessVideoJob::dispatch($id);
        }

        $this->info('Поставлено в очередь роликов: '.count($ids));

        return self::SUCCESS;
    }
}
