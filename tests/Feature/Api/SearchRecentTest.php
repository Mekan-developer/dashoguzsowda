<?php

use App\Models\SearchRecent;
use App\Models\User;
use App\Services\SearchHistoryService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires auth for the search history', function () {
    $this->getJson('/api/v1/search/recent')->assertUnauthorized();
    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone'])->assertUnauthorized();
    $this->deleteJson('/api/v1/search/recent')->assertUnauthorized();
});

it('returns an empty history for a new user', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/search/recent')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('stores a query and returns the updated list', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone'])
        ->assertOk()
        ->assertExactJson(['data' => ['iPhone']]);

    $this->getJson('/api/v1/search/recent')
        ->assertOk()
        ->assertExactJson(['data' => ['iPhone']]);
});

it('puts the newest query on top', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone']);
    $this->postJson('/api/v1/search/recent', ['query' => 'Toyota']);

    $this->getJson('/api/v1/search/recent')
        ->assertOk()
        ->assertExactJson(['data' => ['Toyota', 'iPhone']]);
});

/** Повтор не плодит строки, а поднимает запрос наверх. */
it('deduplicates a repeated query and moves it to the top', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone']);
    $this->postJson('/api/v1/search/recent', ['query' => 'Toyota']);
    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone'])
        ->assertOk()
        ->assertExactJson(['data' => ['iPhone', 'Toyota']]);

    expect(SearchRecent::where('user_id', $this->user->id)->count())->toBe(2);
});

/** Дедупликация регистронезависимая и без учёта крайних пробелов. */
it('treats different casing and padding as the same query', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone']);
    $this->postJson('/api/v1/search/recent', ['query' => '  IPHONE  '])
        ->assertOk()
        // наверху остаётся последнее написание
        ->assertExactJson(['data' => ['IPHONE']]);

    expect(SearchRecent::where('user_id', $this->user->id)->count())->toBe(1);
});

it('keeps at most eight queries, dropping the oldest', function () {
    Sanctum::actingAs($this->user);

    foreach (range(1, 9) as $i) {
        $this->postJson('/api/v1/search/recent', ['query' => "запрос $i"]);
    }

    $data = $this->getJson('/api/v1/search/recent')->assertOk()->json('data');

    expect($data)->toHaveCount(SearchHistoryService::MAX_ITEMS)
        ->and($data[0])->toBe('запрос 9')
        ->and($data)->not->toContain('запрос 1');
});

it('rejects an empty query', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/search/recent', ['query' => '   '])
        ->assertStatus(422)
        ->assertJsonValidationErrors('query');

    $this->postJson('/api/v1/search/recent', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('query');
});

it('clears the history', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/search/recent', ['query' => 'iPhone']);

    $this->deleteJson('/api/v1/search/recent')
        ->assertOk()
        ->assertExactJson(['data' => ['cleared' => true]]);

    $this->getJson('/api/v1/search/recent')->assertExactJson(['data' => []]);
});

it('keeps histories of different users apart', function () {
    $other = User::factory()->create();

    Sanctum::actingAs($this->user);
    $this->postJson('/api/v1/search/recent', ['query' => 'Мой запрос']);

    Sanctum::actingAs($other);
    $this->getJson('/api/v1/search/recent')->assertExactJson(['data' => []]);
});
