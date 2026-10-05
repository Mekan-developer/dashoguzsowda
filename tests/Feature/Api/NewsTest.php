<?php

use App\Models\News;

function makeNews(array $attributes = []): News
{
    return News::create([
        'title_ru'     => 'Заголовок',
        'title_tk'     => 'Sözbaşy',
        'type'         => 'regular',
        'is_published' => true,
        'published_at' => now()->subHour(),
        ...$attributes,
    ]);
}

it('returns only published news', function () {
    actingAsClient();

    makeNews(['title_ru' => 'Опубликованная']);
    makeNews(['title_ru' => 'Черновик', 'is_published' => false, 'published_at' => null]);

    $this->getJson('/api/v1/news')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title_ru', 'Опубликованная');
});

it('hides news scheduled for the future', function () {
    actingAsClient();

    makeNews(['title_ru' => 'Завтрашняя', 'published_at' => now()->addDay()]);

    $this->getJson('/api/v1/news')->assertOk()->assertJsonCount(0, 'data');
});

it('shows published news without a publication date', function () {
    actingAsClient();

    makeNews(['title_ru' => 'Без даты', 'published_at' => null]);

    $this->getJson('/api/v1/news')->assertOk()->assertJsonCount(1, 'data');
});

it('filters by type', function () {
    actingAsClient();

    makeNews(['title_ru' => 'Обычная', 'type' => 'regular']);
    makeNews(['title_ru' => 'Реклама', 'type' => 'ad']);

    $this->getJson('/api/v1/news?type=ad')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'ad');
});

it('rejects an unknown type', function () {
    actingAsClient();

    $this->getJson('/api/v1/news?type=spam')
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

/** Публичный роут без авторизации не должен отдавать всю таблицу по ?limit. */
it('caps the page size', function () {
    actingAsClient();

    $this->getJson('/api/v1/news?limit=1000000')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');

    foreach (range(1, 5) as $i) {
        makeNews(['title_ru' => "Новость $i"]);
    }

    $this->getJson('/api/v1/news?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);
});

it('returns newest news first', function () {
    actingAsClient();

    makeNews(['title_ru' => 'Старая', 'published_at' => now()->subDays(3)]);
    makeNews(['title_ru' => 'Новая',  'published_at' => now()->subMinute()]);

    $this->getJson('/api/v1/news')
        ->assertOk()
        ->assertJsonPath('data.0.title_ru', 'Новая');
});

it('returns 404 for a draft news item', function () {
    actingAsClient();

    $draft = makeNews(['is_published' => false, 'published_at' => null]);

    $this->getJson("/api/v1/news/{$draft->id}")->assertNotFound();
});

it('returns 404 for a news item scheduled for the future', function () {
    actingAsClient();

    $scheduled = makeNews(['published_at' => now()->addDay()]);

    $this->getJson("/api/v1/news/{$scheduled->id}")->assertNotFound();
});

it('returns a published news item', function () {
    actingAsClient();

    $news = makeNews(['title_ru' => 'Видимая']);

    $this->getJson("/api/v1/news/{$news->id}")
        ->assertOk()
        ->assertJsonPath('data.title_ru', 'Видимая');
});
