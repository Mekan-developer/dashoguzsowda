<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Region;
use App\Models\Listing;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->category = Category::create(['name_ru' => 'Одежда', 'name_tk' => 'Egin-eşik', 'slug' => 'clothes', 'level' => 1]);
    $this->owner    = User::factory()->create();
});

function makeStore(array $overrides = []): Store
{
    return Store::create(array_merge([
        'user_id'     => test()->owner->id,
        'category_id' => test()->category->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'name'        => 'Altyn Bazar',
        'phone'       => '+99361234567',
        // Витрина отдаёт только прошедшие модерацию и не погашенные магазины
        'status'      => 'approved',
        'is_active'   => true,
        'is_popular'  => false,
    ], $overrides));
}

it('lists only popular stores, ordered by sort_order, without auth', function () {
    $second = makeStore(['name' => 'B', 'is_popular' => true, 'sort_order' => 2]);
    $first  = makeStore(['name' => 'A', 'is_popular' => true, 'sort_order' => 1, 'user_id' => User::factory()->create()->id]);
    makeStore(['name' => 'Hidden', 'is_popular' => false, 'user_id' => User::factory()->create()->id]);

    $this->getJson('/api/v1/stores/popular')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'A')
        ->assertJsonPath('data.1.name', 'B')
        ->assertJsonCount(2, 'data');
});

it('returns a store card without auth', function () {
    $store = makeStore();

    $this->getJson("/api/v1/stores/{$store->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Altyn Bazar')
        ->assertJsonPath('data.subtitle_ru', 'Одежда');
});

it('returns 404 for a missing store', function () {
    $this->getJson('/api/v1/stores/999999')->assertNotFound();
});

it('lists only approved listings attached to the store, paginated', function () {
    $store = makeStore();

    Listing::create([
        'user_id' => $this->owner->id, 'store_id' => $store->id, 'category_id' => $this->category->id,
        'region_id' => $this->region->id, 'city_id' => $this->city->id,
        'title' => 'Одобренное', 'type' => 'goods', 'phone' => $this->owner->phone, 'status' => 'approved',
    ]);
    Listing::create([
        'user_id' => $this->owner->id, 'store_id' => $store->id, 'category_id' => $this->category->id,
        'region_id' => $this->region->id, 'city_id' => $this->city->id,
        'title' => 'На модерации', 'type' => 'goods', 'phone' => $this->owner->phone, 'status' => 'pending',
    ]);
    $other = User::factory()->create();
    Listing::create([
        'user_id' => $other->id, 'category_id' => $this->category->id,
        'region_id' => $this->region->id, 'city_id' => $this->city->id,
        'title' => 'Чужое', 'type' => 'goods', 'phone' => $other->phone, 'status' => 'approved',
    ]);

    $this->getJson("/api/v1/stores/{$store->id}/listings")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Одобренное')
        ->assertJsonPath('meta.total', 1);
});

it('hides a store that has not passed moderation', function () {
    $store = makeStore(['status' => 'pending']);

    $this->getJson("/api/v1/stores/{$store->id}")->assertNotFound();
    $this->getJson("/api/v1/stores/{$store->id}/listings")->assertNotFound();
});

it('hides a store whose owner tariff expired', function () {
    $store = makeStore(['is_active' => false]);

    $this->getJson("/api/v1/stores/{$store->id}")->assertNotFound();
});

it('filters the public store list by trade type and delivery', function () {
    makeStore(['name' => 'Розница', 'sells_retail' => true, 'sells_wholesale' => false, 'has_delivery' => false]);
    makeStore([
        'name' => 'Опт', 'sells_retail' => false, 'sells_wholesale' => true, 'has_delivery' => true,
        'user_id' => User::factory()->create()->id,
    ]);

    // Гость опта не видит: оптовика нет ни в каталоге, ни во вкладке «Опт»
    $this->getJson('/api/v1/stores?type=wholesale')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/stores')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Розница');

    // Владелец одобренного розничного магазина видит опт
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/stores?type=wholesale')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Опт');

    $this->getJson('/api/v1/stores?has_delivery=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Розница');

    $this->getJson('/api/v1/stores')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});
