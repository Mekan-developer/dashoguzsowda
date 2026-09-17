<?php

use App\Jobs\ProcessVideoJob;
use App\Models\Category;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Video;
use App\Services\Video\VideoProbeInterface;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/** Управляемый ffprobe: длительность и доступность задаём из теста */
class ChunkVideoProbe implements VideoProbeInterface
{
    public static ?float $duration = 30.0;

    public static bool $available = true;

    public function duration(string $absolutePath): ?float
    {
        return static::$duration;
    }

    public function available(): bool
    {
        return static::$available;
    }
}

beforeEach(function () {
    Storage::fake('public');   // финальное хранилище ролика
    Storage::fake('local');    // temp-диск chunked-загрузки
    Queue::fake();             // сжатие (ffmpeg) не должно реально запускаться

    ChunkVideoProbe::$duration  = 30.0;
    ChunkVideoProbe::$available = true;
    app()->instance(VideoProbeInterface::class, new ChunkVideoProbe());

    Tariff::create([
        'name_ru' => 'Free', 'name_tk' => 'Free', 'listings_limit' => 5, 'videos_limit' => 2,
        'boost_limit' => 1, 'duration_days' => 30, 'is_free' => true, 'is_active' => true,
    ]);

    $this->user = User::factory()->create();

    $this->rootCategory = Category::create([
        'name_ru' => 'Недвижимость', 'name_tk' => 'Emlak', 'slug' => 'realty', 'level' => 1, 'is_active' => true,
    ]);
});

function initUpload(array $overrides = []): string
{
    return test()->postJson('/api/v1/videos/upload/init', array_merge([
        'title'       => 'Чанковый ролик',
        'filename'    => 'reel.mp4',
        'category_id' => test()->rootCategory->id,
    ], $overrides))->json('data.upload_id');
}

function sendChunk(string $uploadId, string $bytes, ?int $index = null)
{
    $uri = "/api/v1/videos/upload/{$uploadId}/chunk".($index !== null ? "?index={$index}" : '');

    return test()->call('POST', $uri, [], [], [], [
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT'  => 'application/json',
    ], $bytes);
}

function occupyVideo(array $overrides = []): Video
{
    return Video::create(array_merge([
        'user_id'          => test()->user->id,
        'category_id'      => test()->rootCategory->id,
        'title'            => 'Ролик',
        'path'             => 'videos/'.uniqid().'/original.mp4',
        'duration_seconds' => 30,
        'status'           => 'pending',
    ], $overrides));
}

// ─── Happy path ─────────────────────────────────────────────────────────────

it('requires auth to init a chunked upload', function () {
    $this->postJson('/api/v1/videos/upload/init', [
        'title' => 'X', 'category_id' => $this->rootCategory->id,
    ])->assertUnauthorized();
});

it('requires a root category to init a chunked upload', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/videos/upload/init', ['title' => 'Без категории', 'filename' => 'reel.mp4'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

it('uploads a video in chunks and finalizes it as pending', function () {
    Sanctum::actingAs($this->user);

    $uploadId = initUpload(['tags' => ['Тест']]);
    expect($uploadId)->toBeString();

    sendChunk($uploadId, 'AAAA', 0)->assertOk()->assertJsonPath('data.chunks_received', 1);
    sendChunk($uploadId, 'BBBB', 1)->assertOk()->assertJsonPath('data.bytes_received', 8);

    $this->postJson("/api/v1/videos/upload/{$uploadId}/complete")
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.title', 'Чанковый ролик')
        ->assertJsonPath('data.category.id', $this->rootCategory->id)
        ->assertJsonPath('data.duration_seconds', 30)
        ->assertJsonPath('data.processing', true);

    $video = Video::first();
    expect($video)->not->toBeNull()
        ->and($video->tags)->toBe(['Тест'])
        ->and($video->category_id)->toBe($this->rootCategory->id)
        ->and($video->status)->toBe('pending');

    Storage::disk('public')->assertExists($video->path);
    Queue::assertPushed(ProcessVideoJob::class, 1);
});

// ─── Порядок и владение сессией ──────────────────────────────────────────────

it('rejects a chunk sent out of order', function () {
    Sanctum::actingAs($this->user);

    $uploadId = initUpload();
    sendChunk($uploadId, 'AAAA', 0)->assertOk();
    sendChunk($uploadId, 'BBBB', 5)->assertStatus(422); // ожидался index=1
});

it('forbids appending to a foreign upload session', function () {
    Sanctum::actingAs($this->user);
    $uploadId = initUpload();

    Sanctum::actingAs(User::factory()->create());
    sendChunk($uploadId, 'AAAA', 0)->assertForbidden();
});

// ─── Валидация собранного файла ──────────────────────────────────────────────

it('rejects completion of a video longer than one minute', function () {
    Sanctum::actingAs($this->user);
    ChunkVideoProbe::$duration = 75.0;

    $uploadId = initUpload();
    sendChunk($uploadId, 'AAAA', 0)->assertOk();

    $this->postJson("/api/v1/videos/upload/{$uploadId}/complete")->assertStatus(422);

    expect(Video::count())->toBe(0);
});

it('returns 503 at complete when the video probe is unavailable', function () {
    Sanctum::actingAs($this->user);

    $uploadId = initUpload();
    sendChunk($uploadId, 'AAAA', 0)->assertOk();

    ChunkVideoProbe::$available = false;
    $this->postJson("/api/v1/videos/upload/{$uploadId}/complete")->assertStatus(503);

    expect(Video::count())->toBe(0);
});

// ─── Квота тарифа (fail-fast на init) ────────────────────────────────────────

it('refuses to init when the tariff video quota is exhausted', function () {
    Sanctum::actingAs($this->user);

    occupyVideo(['status' => 'pending']);
    occupyVideo(['status' => 'approved']);

    $this->postJson('/api/v1/videos/upload/init', ['title' => 'X', 'filename' => 'r.mp4'])
        ->assertForbidden();
});

// ─── Отмена ──────────────────────────────────────────────────────────────────

it('aborts an upload and forgets the session', function () {
    Sanctum::actingAs($this->user);

    $uploadId = initUpload();
    sendChunk($uploadId, 'AAAA', 0)->assertOk();

    $this->deleteJson("/api/v1/videos/upload/{$uploadId}")->assertOk();

    // сессии больше нет — следующая часть не находит её
    sendChunk($uploadId, 'BBBB', 1)->assertNotFound();
});
