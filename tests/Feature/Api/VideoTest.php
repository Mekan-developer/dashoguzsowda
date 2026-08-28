<?php

use App\Jobs\ProcessVideoJob;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoLike;
use App\Services\Video\VideoProbeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/** Управляемая длительность вместо реального ffprobe (внешние сервисы в тестах — только fake) */
class FakeVideoProbe implements VideoProbeInterface
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
    Storage::fake('public');
    Queue::fake();

    FakeVideoProbe::$duration = 30.0;
    app()->instance(VideoProbeInterface::class, new FakeVideoProbe());

    // Дефолтный (бесплатный) тариф — activeTariff() падает на него при отсутствии личного
    $this->tariff = Tariff::create([
        'name_ru' => 'Free', 'name_tk' => 'Free', 'listings_limit' => 5, 'videos_limit' => 2,
        'boost_limit' => 1, 'duration_days' => 30, 'is_free' => true, 'is_active' => true,
    ]);

    $this->user = User::factory()->create();
});

function videoPayload(array $overrides = []): array
{
    return array_merge([
        'video' => UploadedFile::fake()->create('reel.mp4', 2048, 'video/mp4'),
        'title' => 'Сдаётся квартира',
        'tags'  => ['Недвижимость', 'Аренда'],
    ], $overrides);
}

function makeVideo(array $overrides = []): Video
{
    return Video::create(array_merge([
        'user_id'          => test()->user->id,
        'title'            => 'Ролик',
        'path'             => 'videos/'.uniqid().'/original.mp4',
        'duration_seconds' => 42,
        'status'           => 'approved',
    ], $overrides));
}

// ─── Загрузка (ТЗ §7.1, экран 10) ───────────────────────────────────────────

it('requires auth to upload a video', function () {
    $this->postJson('/api/v1/videos', [])->assertUnauthorized();
});

it('uploads a video with pending status and queues compression', function () {
    Sanctum::actingAs($this->user);

    $response = $this->post('/api/v1/videos', videoPayload(), ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.title', 'Сдаётся квартира')
        ->assertJsonPath('data.tags.0', 'Недвижимость')
        ->assertJsonPath('data.duration_seconds', 30)
        // Queue::fake() — сжатие ещё не выполнялось
        ->assertJsonPath('data.processing', true);

    $video = Video::first();
    expect($video->status)->toBe('pending')
        ->and($video->duration_seconds)->toBe(30);

    Queue::assertPushed(ProcessVideoJob::class, 1);
    Storage::disk('public')->assertExists($video->path);
});

it('rejects a video longer than one minute', function () {
    Sanctum::actingAs($this->user);
    FakeVideoProbe::$duration = 75.0;

    $this->post('/api/v1/videos', videoPayload(), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['video']);

    expect(Video::count())->toBe(0);
});

it('validates required fields', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/videos', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['video', 'title']);
});

it('enforces the tariff videos limit with 403', function () {
    Sanctum::actingAs($this->user);

    // videos_limit = 2: pending + approved занимают квоту
    makeVideo(['status' => 'approved']);
    makeVideo(['status' => 'pending']);

    $this->post('/api/v1/videos', videoPayload(), ['Accept' => 'application/json'])
        ->assertForbidden();
});

it('does not count rejected videos towards the tariff limit', function () {
    Sanctum::actingAs($this->user);

    makeVideo(['status' => 'approved']);
    makeVideo(['status' => 'rejected']);

    $this->post('/api/v1/videos', videoPayload(), ['Accept' => 'application/json'])
        ->assertCreated();
});

// ─── Лента (ТЗ §7, экран 9) ─────────────────────────────────────────────────

it('feed returns only approved videos', function () {
    makeVideo(['title' => 'Одобренный']);
    makeVideo(['status' => 'pending']);
    makeVideo(['status' => 'rejected']);

    $this->getJson('/api/v1/videos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Одобренный')
        ->assertJsonPath('meta.total', 1)
        // Гость — ключа is_liked нет
        ->assertJsonMissingPath('data.0.is_liked');
});

it('feed exposes is_liked for an authenticated viewer', function () {
    $video = makeVideo();
    $other = makeVideo();
    VideoLike::create(['video_id' => $video->id, 'user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/videos')->assertOk();
    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$video->id]['is_liked'])->toBeTrue()
        ->and($byId[$other->id]['is_liked'])->toBeFalse();
});

it('feed filters by tag', function () {
    makeVideo(['tags' => ['Недвижимость', 'Аренда']]);
    makeVideo(['tags' => ['Авто']]);

    $this->getJson('/api/v1/videos?tag=Авто')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tags.0', 'Авто');
});

it('hides foreign pending videos in show but lets the owner see their own', function () {
    $video = makeVideo(['status' => 'pending']);

    $this->getJson("/api/v1/videos/{$video->id}")->assertNotFound();

    Sanctum::actingAs($this->user);
    $this->getJson("/api/v1/videos/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('returns own videos of all statuses in /videos/my', function () {
    makeVideo(['status' => 'pending']);
    makeVideo(['status' => 'approved']);
    makeVideo(['status' => 'rejected']);
    makeVideo(['status' => 'approved', 'user_id' => User::factory()->create()->id]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/videos/my')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->getJson('/api/v1/videos/my?status=rejected')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ─── Лайки (ТЗ §7.3) ────────────────────────────────────────────────────────

it('requires auth to like a video', function () {
    $video = makeVideo();

    $this->postJson("/api/v1/videos/{$video->id}/like")->assertUnauthorized();
});

it('toggles a like and keeps likes_count in sync', function () {
    $video = makeVideo();
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/videos/{$video->id}/like")
        ->assertOk()
        ->assertJsonPath('data.is_liked', true)
        ->assertJsonPath('data.likes_count', 1);

    expect(VideoLike::count())->toBe(1)
        ->and($video->fresh()->likes_count)->toBe(1);

    // Повторный запрос снимает лайк
    $this->postJson("/api/v1/videos/{$video->id}/like")
        ->assertOk()
        ->assertJsonPath('data.is_liked', false)
        ->assertJsonPath('data.likes_count', 0);

    expect(VideoLike::count())->toBe(0)
        ->and($video->fresh()->likes_count)->toBe(0);
});

it('does not allow liking an unapproved video', function () {
    $video = makeVideo(['status' => 'pending', 'user_id' => User::factory()->create()->id]);
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/videos/{$video->id}/like")->assertNotFound();
});

it('forbids a blocked user from liking a video', function () {
    // Публикация закрыта через not_blocked — накрутка лайков тоже (ТЗ 13.3)
    $video = makeVideo();
    Sanctum::actingAs(User::factory()->blocked()->create());

    $this->postJson("/api/v1/videos/{$video->id}/like")->assertForbidden();

    expect(VideoLike::count())->toBe(0)
        ->and($video->fresh()->likes_count)->toBe(0);
});

// ─── Просмотры (ТЗ §7.2) ────────────────────────────────────────────────────

it('increments views atomically, skipping the author', function () {
    $video = makeVideo();

    // Гость из публичной ленты
    $this->postJson("/api/v1/videos/{$video->id}/view")->assertOk();
    expect($video->fresh()->views)->toBe(1);

    // Автор себе просмотры не накручивает
    Sanctum::actingAs($this->user);
    $this->postJson("/api/v1/videos/{$video->id}/view")->assertOk();
    expect($video->fresh()->views)->toBe(1);
});

// ─── Удаление ───────────────────────────────────────────────────────────────

it('lets the owner delete a video together with its files', function () {
    $video = makeVideo([
        'path'           => 'videos/abc/original.mp4',
        'processed_path' => 'videos/abc/processed.mp4',
        'preview_path'   => 'videos/abc/preview.jpg',
    ]);
    VideoLike::create(['video_id' => $video->id, 'user_id' => User::factory()->create()->id]);

    Storage::disk('public')->put('videos/abc/original.mp4', 'x');
    Storage::disk('public')->put('videos/abc/processed.mp4', 'x');
    Storage::disk('public')->put('videos/abc/preview.jpg', 'x');

    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/v1/videos/{$video->id}")->assertOk();

    expect(Video::count())->toBe(0)
        ->and(VideoLike::count())->toBe(0); // FK cascade

    Storage::disk('public')->assertMissing('videos/abc/original.mp4');
    Storage::disk('public')->assertMissing('videos/abc/processed.mp4');
    Storage::disk('public')->assertMissing('videos/abc/preview.jpg');
});

it('forbids deleting a foreign video', function () {
    $video = makeVideo();

    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson("/api/v1/videos/{$video->id}")->assertForbidden();
    expect(Video::count())->toBe(1);
});
