<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport', 'level' => 1]);

    $this->user  = User::factory()->create();
    $this->owner = User::factory()->create();

    $this->listing = Listing::create([
        'user_id'     => $this->owner->id,
        'category_id' => $this->category->id,
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'title'       => 'Продам велосипед',
        'type'        => 'goods',
        'phone'       => $this->owner->phone,
        'status'      => 'approved',
    ]);
});

it('requires auth for favorites endpoints', function () {
    $this->getJson('/api/v1/favorites')->assertUnauthorized();
    $this->postJson('/api/v1/favorites', ['listing_id' => $this->listing->id])->assertUnauthorized();
    $this->deleteJson("/api/v1/favorites/{$this->listing->id}")->assertUnauthorized();
});

it('adds a listing to favorites', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/favorites', ['listing_id' => $this->listing->id])
        ->assertCreated()
        ->assertJsonPath('message', __('messages.favorite_added'));

    expect(Favorite::where('user_id', $this->user->id)->where('listing_id', $this->listing->id)->exists())->toBeTrue();
});

it('is idempotent when adding the same listing twice', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/favorites', ['listing_id' => $this->listing->id])->assertCreated();
    $this->postJson('/api/v1/favorites', ['listing_id' => $this->listing->id])->assertCreated();

    expect(Favorite::where('user_id', $this->user->id)->count())->toBe(1);
});

it('validates listing_id when adding to favorites', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/favorites', [])->assertUnprocessable()->assertJsonValidationErrors('listing_id');
    $this->postJson('/api/v1/favorites', ['listing_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('listing_id');
});

it('refuses to favorite someone elses unmoderated listing', function () {
    Sanctum::actingAs($this->user);

    $pending = Listing::create([
        'user_id'     => $this->owner->id,
        'category_id' => $this->category->id,
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'title'       => 'SECRET PENDING',
        'type'        => 'goods',
        'phone'       => $this->owner->phone,
        'status'      => 'pending',
    ]);

    // Само объявление скрыто — избранное не должно быть обходным путём к нему
    $this->getJson("/api/v1/listings/{$pending->id}")->assertNotFound();

    $this->postJson('/api/v1/favorites', ['listing_id' => $pending->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('listing_id');

    expect(Favorite::where('listing_id', $pending->id)->exists())->toBeFalse();
});

it('hides listings unpublished after they were favorited', function () {
    Sanctum::actingAs($this->user);
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    $this->listing->update(['status' => 'rejected']);

    $this->getJson('/api/v1/favorites')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

it('lists user favorites with listing payload and pagination meta', function () {
    Sanctum::actingAs($this->user);
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    // Чужое избранное не должно попадать в выдачу
    Favorite::create(['user_id' => $this->owner->id, 'listing_id' => $this->listing->id]);

    $this->getJson('/api/v1/favorites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.listing.id', $this->listing->id)
        ->assertJsonPath('data.0.listing.title', 'Продам велосипед')
        ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

it('removes a listing from favorites idempotently', function () {
    Sanctum::actingAs($this->user);
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    $this->deleteJson("/api/v1/favorites/{$this->listing->id}")
        ->assertOk()
        ->assertJsonPath('message', __('messages.favorite_removed'));

    expect(Favorite::where('user_id', $this->user->id)->exists())->toBeFalse();

    // Повторное удаление не падает
    $this->deleteJson("/api/v1/favorites/{$this->listing->id}")->assertOk();
});

it('exposes is_favorite on listing card for authenticated viewer', function () {
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    // Гость — ключа нет
    $this->getJson("/api/v1/listings/{$this->listing->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.is_favorite');

    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/listings/{$this->listing->id}")
        ->assertOk()
        ->assertJsonPath('data.is_favorite', true);
});

it('exposes is_favorite in public listings feed for authenticated viewer', function () {
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    $other = Listing::create([
        'user_id'     => $this->owner->id,
        'category_id' => $this->category->id,
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'title'       => 'Другое объявление',
        'type'        => 'goods',
        'phone'       => $this->owner->phone,
        'status'      => 'approved',
    ]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/listings')->assertOk();
    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$this->listing->id]['is_favorite'])->toBeTrue()
        ->and($byId[$other->id]['is_favorite'])->toBeFalse();
});
