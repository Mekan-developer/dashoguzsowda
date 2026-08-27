<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Region;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->free = Tariff::create([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt',
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => 30, 'is_free' => true, 'is_active' => true,
    ]);

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->leaf   = Category::create(['name_ru' => 'Велосипеды', 'name_tk' => 'Tigirler', 'slug' => 'bikes', 'level' => 1]);

    $this->user = User::factory()->create();
});

function makeUserListing(array $overrides = []): Listing
{
    return Listing::create(array_merge([
        'user_id'     => test()->user->id,
        'category_id' => test()->leaf->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'title'       => 'Объявление',
        'type'        => 'goods',
        'phone'       => test()->user->phone,
        'status'      => 'approved',
    ], $overrides));
}

it('requires auth to read the tariff', function () {
    $this->getJson('/api/v1/profile/tariff')->assertUnauthorized();
});

it('returns the free tariff with full remaining limits for a fresh user', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/profile/tariff')
        ->assertOk()
        ->assertJsonPath('data.tariff.name_ru', 'Бесплатный')
        ->assertJsonPath('data.tariff.is_free', true)
        ->assertJsonPath('data.tariff.boost_limit', 3)
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('data.remaining.listings', 5)
        ->assertJsonPath('data.remaining.videos', 2)
        ->assertJsonPath('data.remaining.boosts', 3);
});

it('reflects an assigned paid tariff with an expiry date', function () {
    $paid = Tariff::create([
        'name_ru' => 'Премиум', 'name_tk' => 'Premium',
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true,
    ]);
    app(UserRepositoryInterface::class)->assignTariff($this->user, $paid->id, now()->addDays(30));

    Sanctum::actingAs($this->user->refresh());

    $this->getJson('/api/v1/profile/tariff')
        ->assertOk()
        ->assertJsonPath('data.tariff.name_ru', 'Премиум')
        ->assertJsonPath('data.tariff.is_free', false)
        ->assertJsonPath('data.remaining.listings', 100)
        ->assertJsonPath('data.remaining.boosts', 50);

    expect($this->getJson('/api/v1/profile/tariff')->json('data.expires_at'))->not->toBeNull();
});

it('subtracts used quota: pending+approved listings and active boosts', function () {
    makeUserListing(['status' => 'pending']);
    makeUserListing(['status' => 'approved']);
    makeUserListing(['status' => 'approved', 'is_boosted' => true]);
    makeUserListing(['status' => 'rejected']); // отклонённые место не занимают

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/profile/tariff')
        ->assertOk()
        ->assertJsonPath('data.remaining.listings', 2) // 5 - 3 (pending + 2 approved)
        ->assertJsonPath('data.remaining.boosts', 2);  // 3 - 1 поднятое
});
