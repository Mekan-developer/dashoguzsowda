<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Нарезка ролика в адаптивный HLS: несколько качеств одним проходом ffmpeg
 * (декодирование исходника один раз), сегменты MPEG-TS, master-плейлист.
 *
 * Раскладка в $outDir — плоская, без подпапок на качество:
 *   master.m3u8                 — список качеств, его и отдаём клиенту
 *   360p.m3u8, 360p_000.ts …    — плейлист и сегменты одного качества
 */
class HlsTranscoder
{
    public const MASTER = 'master.m3u8';

    public function __construct(
        private readonly VideoProbeInterface $probe,
    ) {}

    /**
     * Режет $input в $outDir. Прежнее содержимое $outDir удаляется —
     * повторный запуск (ретрай job, досборка старых роликов) не смешивает
     * сегменты разных прогонов.
     *
     * @throws RuntimeException  ffmpeg не справился или во входе нет видео
     */
    public function transcode(string $input, string $outDir): void
    {
        $streams = $this->probe->streams($input);

        if ($streams === null) {
            throw new RuntimeException('No video stream in '.$input);
        }

        File::deleteDirectory($outDir);
        File::ensureDirectoryExists($outDir);

        $result = Process::timeout((int) config('video.hls.process_timeout', 600))->run($this->command(
            $input,
            $outDir,
            $this->renditions($streams['width'], $streams['height']),
            $streams['has_audio'],
        ));

        if ($result->failed()) {
            File::deleteDirectory($outDir);

            // Хвоста вывода хватает, чтобы понять причину; целиком он огромный
            throw new RuntimeException('ffmpeg HLS failed: '.mb_substr($result->errorOutput() ?: $result->output(), -2000));
        }
    }

    /**
     * Ступени лестницы для исходника: все, что ниже его короткой стороны,
     * плюс одна ступень, которая её достигает (её кадр ffmpeg ужмёт до
     * размеров исходника, а не растянет). Ролик 480p → 360p + «540p» в 480.
     *
     * @return list<array{name:string, short_side:int, video_kbps:int, audio_kbps:int}>
     */
    public function renditions(int $width, int $height): array
    {
        $source = min($width, $height);
        $ladder = collect(config('video.hls.renditions'))->sortBy('short_side')->values();

        $picked = [];

        foreach ($ladder as $rung) {
            $picked[] = ['name' => $rung['short_side'].'p'] + $rung;

            if ($rung['short_side'] >= $source) {
                break;
            }
        }

        return $picked;
    }

    /**
     * @param  list<array{name:string, short_side:int, video_kbps:int, audio_kbps:int}>  $renditions
     * @return list<string>  аргументы без shell — экранирование не нужно
     */
    public function command(string $input, string $outDir, array $renditions, bool $hasAudio): array
    {
        $segment = (int) config('video.hls.segment_seconds', 2);
        $count   = count($renditions);

        // [0:v]split=N[s0][s1];[s0]scale=…[v0];[s1]scale=…[v1]
        // Масштаб по короткой стороне без апскейла; размеры чётные (yuv420p).
        // iw/ih здесь уже после автоповорота — вертикальный ролик с телефона
        // приходит вертикальным.
        $graph = "[0:v]split={$count}".implode('', array_map(fn ($i) => "[s{$i}]", range(0, $count - 1)));

        foreach ($renditions as $i => $r) {
            $side   = "min({$r['short_side']},min(iw,ih))/min(iw,ih)";
            $graph .= ";[s{$i}]scale=w='trunc(iw*{$side}/2)*2':h='trunc(ih*{$side}/2)*2'[v{$i}]";
        }

        $cmd = ['ffmpeg', '-y', '-hide_banner', '-i', $input, '-filter_complex', $graph];

        $map = [];

        foreach ($renditions as $i => $r) {
            $kbps = $r['video_kbps'];

            array_push($cmd,
                '-map', "[v{$i}]",
                "-c:v:{$i}", 'libx264',
                "-b:v:{$i}", "{$kbps}k",
                "-maxrate:v:{$i}", ((int) round($kbps * 1.1)).'k',
                "-bufsize:v:{$i}", ($kbps * 2).'k',
            );

            if ($hasAudio) {
                array_push($cmd, '-map', '0:a:0', "-c:a:{$i}", 'aac', "-b:a:{$i}", "{$r['audio_kbps']}k");
            }

            $map[] = $hasAudio ? "v:{$i},a:{$i},name:{$r['name']}" : "v:{$i},name:{$r['name']}";
        }

        array_push($cmd,
            // main + 8 бит: играется любым аппаратным декодером. HDR/10-бит
            // с iPhone без yuv420p дал бы High 10, который многие Android не тянут
            '-preset', 'fast', '-profile:v', 'main', '-pix_fmt', 'yuv420p',
            '-ac', '2',
            // Ключевой кадр ровно на границе каждого сегмента во всех качествах —
            // иначе переключение качества даёт рывок. -sc_threshold 0 не даёт
            // x264 ставить лишние ключевые кадры на сменах сцены.
            '-force_key_frames', "expr:gte(t,n_forced*{$segment})",
            '-sc_threshold', '0',
            '-f', 'hls',
            '-hls_time', (string) $segment,
            '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', $outDir.'/%v_%03d.ts',
            '-master_pl_name', self::MASTER,
            '-var_stream_map', implode(' ', $map),
            $outDir.'/%v.m3u8',
        );

        return $cmd;
    }
}
