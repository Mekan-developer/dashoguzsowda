<?php

use App\Models\Category;
use App\Models\RejectionReason;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tariff = Tariff::create([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'listings_limit' => 5, 'videos_limit' => 2,
        'boost_limit' => 1, 'duration_days' => 30, 'is_free' => true, 'is_active' => true,
    ]);

    $this->owner = User::factory()->create();

    $this->rootCategory = Category::create([
        'name_ru' => 'Недвижимость', 'name_tk' => 'Emlak', 'slug' => 'realty', 'level' => 1, 'is_active' => true,
    ]);
});

function makeAdminVideo(array $overrides = []): Video
{
    return Video::create(array_merge([
        'user_id'          => test()->owner->id,
        'category_id'      => test()->rootCategory->id,
        'title'            => 'Ролик',
        'path'             => 'videos/'.uniqid().'/original.mp4',
        'duration_seconds' => 48,
        'status'           => 'pending',
    ], $overrides));
}

it('renders the videos index with counts and tariff usage per author', function () {
    makeAdminVideo(['title' => 'Первый']);
    makeAdminVideo(['status' => 'approved']);

    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('videos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Videos/Index')
            ->has('videos.data', 2)
            // «Тариф · использовано/лимит» для колонки Автор (занято = pending+approved)
            ->where('videos.data.0.tariff_usage.used', 2)
            ->where('videos.data.0.tariff_usage.limit', 2)
            ->where('counts.pending', 1)
            ->where('counts.approved', 1)
            ->has('rejectionReasons')
            ->has('categories', 1));
});

it('filters the index by category on the server', function () {
    $other = Category::create([
        'name_ru' => 'Авто', 'name_tk' => 'Awto', 'slug' => 'auto', 'level' => 1, 'is_active' => true,
    ]);

    makeAdminVideo(['title' => 'В нужной']);
    makeAdminVideo(['category_id' => $other->id, 'title' => 'В другой']);

    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('videos.index', ['category_id' => $this->rootCategory->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'В нужной'));
});

it('filters the index by status on the server', function () {
    makeAdminVideo(['title' => 'В очереди']);
    makeAdminVideo(['status' => 'approved', 'title' => 'Готовый']);

    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('videos.index', ['status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('videos.data', 1)
            ->where('videos.data.0.title', 'В очереди'));
});

it('renders the moderation card with a playable url and the author tariff', function () {
    $video = makeAdminVideo([
        'path'           => 'videos/abc/original.mp4',
        'processed_path' => 'videos/abc/processed.mp4',
        'preview_path'   => 'videos/abc/preview.jpg',
    ]);

    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('videos.show', $video))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Videos/Show')
            ->where('video.id', $video->id)
            // Плееру нужна сжатая версия, а не сырой оригинал
            ->where('video.video_url', Storage::disk('public')->url('videos/abc/processed.mp4'))
            ->where('video.preview_url', Storage::disk('public')->url('videos/abc/preview.jpg'))
            ->where('video.tariff_usage.limit', 2)
            ->has('video.user')
            ->has('rejectionReasons'));
});

it('lets a manager rename a video from the moderation card', function () {
    $video = makeAdminVideo(['title' => 'Старое имя']);

    $this->actingAs(User::factory()->manager()->create());

    $this->put(route('videos.update', $video), ['title' => 'Новое имя'])->assertRedirect();
    expect($video->fresh()->title)->toBe('Новое имя');

    // Пустой заголовок упирался бы в NOT NULL-колонку
    $this->put(route('videos.update', $video), ['title' => ''])->assertSessionHasErrors('title');
});

it('lets a manager change the root category of a video', function () {
    $other = Category::create([
        'name_ru' => 'Авто', 'name_tk' => 'Awto', 'slug' => 'auto', 'level' => 1, 'is_active' => true,
    ]);
    $child = Category::create([
        'parent_id' => $other->id,
        'name_ru' => 'Легковые', 'name_tk' => 'Ýeňil', 'slug' => 'cars', 'level' => 2, 'is_active' => true,
    ]);
    $video = makeAdminVideo();

    $this->actingAs(User::factory()->manager()->create());

    $this->put(route('videos.update', $video), [
        'title' => $video->title, 'category_id' => $other->id,
    ])->assertRedirect();
    expect($video->fresh()->category_id)->toBe($other->id);

    $this->put(route('videos.update', $video), [
        'title' => $video->title, 'category_id' => $child->id,
    ])->assertSessionHasErrors('category_id');
});

it('lets a manager approve a pending video', function () {
    $video = makeAdminVideo();

    $this->actingAs(User::factory()->manager()->create());

    $this->patch(route('videos.approve', $video))->assertRedirect();

    expect($video->fresh()->status)->toBe('approved');
});

it('rejects a video with a reason from the dictionary', function () {
    $video  = makeAdminVideo();
    $reason = RejectionReason::create([
        'name_ru' => 'Признаки мошенничества', 'name_tk' => 'Aldawçylyk alamatlary',
        'type' => 'video', 'is_active' => true,
    ]);

    $this->actingAs(User::factory()->manager()->create());

    $this->patch(route('videos.reject', $video), ['rejection_reason_id' => $reason->id])
        ->assertRedirect();

    $video->refresh();
    expect($video->status)->toBe('rejected')
        ->and($video->rejection_reason_id)->toBe($reason->id);
});

it('forbids a manager from deleting a video', function () {
    $video = makeAdminVideo();

    $this->actingAs(User::factory()->manager()->create());

    $this->delete(route('videos.destroy', $video))->assertForbidden();
    expect(Video::count())->toBe(1);
});

it('lets an admin delete a video and cleans up its files', function () {
    $video = makeAdminVideo([
        'path'           => 'videos/xyz/original.mp4',
        'processed_path' => 'videos/xyz/processed.mp4',
        'preview_path'   => 'videos/xyz/preview.jpg',
        'status'         => 'rejected',
    ]);

    Storage::disk('public')->put('videos/xyz/original.mp4', 'x');
    Storage::disk('public')->put('videos/xyz/processed.mp4', 'x');
    Storage::disk('public')->put('videos/xyz/preview.jpg', 'x');

    $this->actingAs(User::factory()->admin()->create());

    $this->delete(route('videos.destroy', $video))->assertRedirect();

    expect(Video::count())->toBe(0);
    Storage::disk('public')->assertMissing('videos/xyz/original.mp4');
    Storage::disk('public')->assertMissing('videos/xyz/processed.mp4');
    Storage::disk('public')->assertMissing('videos/xyz/preview.jpg');
});
