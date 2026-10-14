<?php

use App\Jobs\ProcessVideoJob;
use App\Models\Category;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Video;
use App\Services\Video\HlsTranscoder;
use App\Services\Video\VideoProbeInterface;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** ffprobe без ffprobe: вертикальный 1080×1920 со звуком (внешние сервисы — только fake) */
class HlsJobProbe implements VideoProbeInterface
{
    public static ?array $streams = ['width' => 1080, 'height' => 1920, 'has_audio' => true];

    public function duration(string $absolutePath): ?float { return 30.0; }
    public function available(): bool { return true; }
    public function streams(string $absolutePath): ?array { return static::$streams; }
}

/** Команда ffmpeg — нарезка HLS (а не mp4 или кадр превью) */
function isHlsCommand(PendingProcess $process): bool
{
    return in_array('hls', (array) $process->command, true);
}

beforeEach(function () {
    Storage::fake('public');

    HlsJobProbe::$streams = ['width' => 1080, 'height' => 1920, 'has_audio' => true];
    app()->instance(VideoProbeInterface::class, new HlsJobProbe());

    Tariff::create([
        'name_ru' => 'Free', 'name_tk' => 'Free', 'listings_limit' => 5, 'videos_limit' => 2,
        'boost_limit' => 1, 'duration_days' => null, 'is_free' => true, 'is_active' => true,
    ]);

    $category = Category::create([
        'name_ru' => 'Авто', 'name_tk' => 'Awto', 'slug' => 'auto', 'level' => 1, 'is_active' => true,
    ]);

    $this->video = Video::create([
        'user_id'          => User::factory()->create()->id,
        'category_id'      => $category->id,
        'title'            => 'Ролик',
        'path'             => 'videos/abc/original.mov',
        'duration_seconds' => 30,
        'status'           => 'approved',
    ]);

    Storage::disk('public')->put('videos/abc/original.mov', 'raw');
});

function runVideoJob(int $id): void
{
    app()->call([new ProcessVideoJob($id), 'handle']);
}

it('builds mp4, preview and HLS, then removes the original', function () {
    Process::fake();

    runVideoJob($this->video->id);

    $video = $this->video->fresh();

    expect($video->is_processed)->toBeTrue()
        ->and($video->processed_path)->toBe('videos/abc/processed.mp4')
        ->and($video->hls_path)->toBe('videos/abc/hls/master.m3u8')
        ->and($video->preview_path)->toBe('videos/abc/preview.jpg');

    Storage::disk('public')->assertMissing('videos/abc/original.mov');
    Process::assertRanTimes(fn (PendingProcess $p) => isHlsCommand($p), 1);
});

it('keeps mp4 and the original when HLS fails', function () {
    Process::fake(fn (PendingProcess $p) => isHlsCommand($p)
        ? Process::result(errorOutput: 'boom', exitCode: 1)
        : Process::result());

    runVideoJob($this->video->id);

    $video = $this->video->fresh();

    expect($video->is_processed)->toBeTrue()
        ->and($video->processed_path)->toBe('videos/abc/processed.mp4')
        ->and($video->hls_path)->toBeNull();

    // Оригинал нужен videos:build-hls для повторной нарезки
    Storage::disk('public')->assertExists('videos/abc/original.mov');
});

it('leaves the video unprocessed when mp4 compression fails', function () {
    Process::fake(fn () => Process::result(exitCode: 1));

    runVideoJob($this->video->id);

    $video = $this->video->fresh();

    expect($video->is_processed)->toBeFalse()
        ->and($video->hls_path)->toBeNull();

    Storage::disk('public')->assertExists('videos/abc/original.mov');
    Process::assertDidntRun(fn (PendingProcess $p) => isHlsCommand($p));
});

it('rebuilds HLS from processed mp4 when the original is gone', function () {
    Process::fake();

    Storage::disk('public')->delete('videos/abc/original.mov');
    Storage::disk('public')->put('videos/abc/processed.mp4', 'mp4');
    $this->video->update(['processed_path' => 'videos/abc/processed.mp4', 'is_processed' => true]);

    runVideoJob($this->video->id);

    expect($this->video->fresh()->hls_path)->toBe('videos/abc/hls/master.m3u8');

    // mp4 заново не кодируется — он сам и есть вход
    Process::assertRanTimes(fn (PendingProcess $p) => in_array('+faststart', (array) $p->command, true), 0);
    Process::assertRan(fn (PendingProcess $p) => isHlsCommand($p)
        && in_array(Storage::disk('public')->path('videos/abc/processed.mp4'), (array) $p->command, true));
});

it('queues HLS rebuild only for processed videos without it', function () {
    Queue::fake();

    $this->video->update(['is_processed' => true]);
    Video::create($this->video->only('user_id', 'category_id', 'title', 'status', 'duration_seconds') + [
        'path' => 'videos/def/original.mp4', 'is_processed' => true, 'hls_path' => 'videos/def/hls/master.m3u8',
    ]);
    Video::create($this->video->only('user_id', 'category_id', 'title', 'status', 'duration_seconds') + [
        'path' => 'videos/ghi/original.mp4',
    ]);

    $this->artisan('videos:build-hls')->assertSuccessful();

    Queue::assertPushed(ProcessVideoJob::class, 1);
});

// ─── Лестница качеств ───────────────────────────────────────────────────────

it('picks renditions up to the source short side without upscaling', function (int $w, int $h, array $names) {
    $renditions = app(HlsTranscoder::class)->renditions($w, $h);

    expect(array_column($renditions, 'name'))->toBe($names);
})->with([
    'вертикальный 1080p'    => [1080, 1920, ['360p', '540p', '720p']],
    'горизонтальный 1080p'  => [1920, 1080, ['360p', '540p', '720p']],
    '480p — 540p ужмётся'   => [640, 480, ['360p', '540p']],
    'ровно 540'             => [540, 960, ['360p', '540p']],
    'меньше нижней ступени' => [240, 320, ['360p']],
]);

it('maps audio into variants only when the source has it', function () {
    $hls        = app(HlsTranscoder::class);
    $renditions = $hls->renditions(1080, 1920);

    $withAudio = $hls->command('/in.mp4', '/out', $renditions, true);
    $silent    = $hls->command('/in.mp4', '/out', $renditions, false);

    $map = fn (array $cmd) => $cmd[array_search('-var_stream_map', $cmd, true) + 1];

    expect($map($withAudio))->toBe('v:0,a:0,name:360p v:1,a:1,name:540p v:2,a:2,name:720p')
        ->and($map($silent))->toBe('v:0,name:360p v:1,name:540p v:2,name:720p')
        ->and($silent)->not->toContain('0:a:0')
        ->and(end($withAudio))->toBe('/out/%v.m3u8');
});
