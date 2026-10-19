<?php

use App\Models\News;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function adminNewsPayload(array $overrides = []): array
{
    return [
        'title_ru'     => 'Обновление приложения',
        'title_tk'     => 'Programmanyň täzelenmesi',
        'content_ru'   => '<p>Что нового</p>',
        'type'         => 'regular',
        'is_published' => false,
        ...$overrides,
    ];
}

function makeAdminNews(array $overrides = []): News
{
    return News::create([
        'author_id'    => test()->admin->id,
        'title_ru'     => 'Черновик',
        'type'         => 'regular',
        'is_published' => false,
        ...$overrides,
    ]);
}

it('creates a news item with a 16:9 webp cover and the author', function () {
    $this->post(route('news.store'), adminNewsPayload([
        'image' => UploadedFile::fake()->image('cover.jpg', 1600, 1200),
    ]))->assertRedirect();

    $news = News::sole();
    expect($news->title_ru)->toBe('Обновление приложения')
        ->and($news->author_id)->toBe($this->admin->id)
        ->and($news->image)->toEndWith('.webp');

    Storage::disk('public')->assertExists($news->image);
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($news->image));
    expect($width)->toBeLessThanOrEqual(800)
        ->and(round($width / $height, 2))->toBe(round(16 / 9, 2));
});

it('drops ad link fields from a regular news item', function () {
    $news = makeAdminNews(['type' => 'ad', 'ad_link_type' => 'store', 'ad_link_id' => 1]);

    $this->put(route('news.update', $news), adminNewsPayload(['title_ru' => 'Уже не реклама']))
        ->assertRedirect();

    $news->refresh();
    expect($news->title_ru)->toBe('Уже не реклама')
        ->and($news->type)->toBe('regular')
        ->and($news->ad_link_type)->toBeNull()
        ->and($news->ad_link_id)->toBeNull();
});

it('replaces the cover and deletes the old file', function () {
    Storage::disk('public')->put('news/old.webp', 'old');
    $news = makeAdminNews(['image' => 'news/old.webp']);

    $this->put(route('news.update', $news), adminNewsPayload([
        'image' => UploadedFile::fake()->image('new.png', 1280, 720),
    ]))->assertRedirect();

    $news->refresh();
    expect($news->image)->not->toBe('news/old.webp');
    Storage::disk('public')->assertMissing('news/old.webp');
    Storage::disk('public')->assertExists($news->image);
});

it('removes the cover on request', function () {
    Storage::disk('public')->put('news/old.webp', 'old');
    $news = makeAdminNews(['image' => 'news/old.webp']);

    $this->put(route('news.update', $news), adminNewsPayload(['remove_image' => true]))->assertRedirect();

    expect($news->fresh()->image)->toBeNull();
    Storage::disk('public')->assertMissing('news/old.webp');
});

it('keeps the cover when the form is saved without a new one', function () {
    Storage::disk('public')->put('news/keep.webp', 'keep');
    $news = makeAdminNews(['image' => 'news/keep.webp']);

    $this->put(route('news.update', $news), adminNewsPayload())->assertRedirect();

    expect($news->fresh()->image)->toBe('news/keep.webp');
    Storage::disk('public')->assertExists('news/keep.webp');
});

it('publishes and unpublishes a news item', function () {
    $news = makeAdminNews();

    $this->patch(route('news.publish', $news))->assertRedirect();
    $news->refresh();
    expect($news->is_published)->toBeTrue()
        ->and($news->published_at)->not->toBeNull();

    $this->patch(route('news.unpublish', $news))->assertRedirect();
    $news->refresh();
    expect($news->is_published)->toBeFalse()
        ->and($news->published_at)->toBeNull();
});

it('deletes a news item together with its cover', function () {
    Storage::disk('public')->put('news/gone.webp', 'gone');
    $news = makeAdminNews(['image' => 'news/gone.webp']);

    $this->delete(route('news.destroy', $news))->assertRedirect();

    expect(News::find($news->id))->toBeNull();
    Storage::disk('public')->assertMissing('news/gone.webp');
});

it('validates the news form', function () {
    $this->post(route('news.store'), ['type' => 'promo'])
        ->assertSessionHasErrors(['title_ru', 'type']);

    expect(News::count())->toBe(0);
});

it('lets a manager with the news permission edit but not delete', function () {
    // Право выдаётся всем менеджерам сразу — флагом в Настройках → Роли и права
    Setting::set('manager_can_manage_news', '1');
    $news = makeAdminNews();

    $this->actingAs(User::factory()->manager()->create());

    $this->patch(route('news.publish', $news))->assertRedirect();
    $this->delete(route('news.destroy', $news))->assertForbidden();

    expect(News::find($news->id))->not->toBeNull();
});
