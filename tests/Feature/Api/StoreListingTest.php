<?php

use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Товар магазина — это обычное объявление с store_id: оптовая цена, минимальная
 * партия и остаток живут в listings, отдельной сущности `products` нет.
 */
beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->district = District::create(['city_id' => $this->city->id, 'name_ru' => 'Центр', 'name_tk' => 'Merkez']);

    // Второй адрес — чтобы проверить, что адрес магазина перебивает присланный
    $this->otherRegion = Region::create(['name_ru' => 'Мары', 'name_tk' => 'Mary']);
    $this->otherCity   = City::create(['region_id' => $this->otherRegion->id, 'name_ru' => 'Мары', 'name_tk' => 'Mary']);

    $root = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food', 'level' => 1]);
    $this->leaf = Category::create([
        'parent_id' => $root->id, 'name_ru' => 'Крупы', 'name_tk' => 'Ýarma',
        'slug' => 'grains', 'level' => 2,
    ]);

    // Бесплатный тариф нужен пользователям без магазина: activeTariff() падает на него
    Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => 30, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);

    $this->tariff = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);

    $this->owner = User::factory()->create([
        'tariff_id' => $this->tariff->id, 'tariff_ends_at' => now()->addDays(30),
    ]);

    $this->store = Store::create([
        'user_id'     => $this->owner->id,
        'name'        => 'Altyn Bazar',
        'phone'       => '+99361234567',
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'district_id' => $this->district->id,
        'sells_retail'    => true,
        'sells_wholesale' => true,
        'has_delivery'    => true,
        'status'      => 'approved',
        'is_active'   => true,
    ]);
});

function storeListingPayload(array $overrides = []): array
{
    return array_merge([
        'title'       => 'Рис длиннозёрный',
        'description' => 'Мешок 25 кг, урожай этого года',
        'type'        => 'goods',
        'category_id' => test()->leaf->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'price'       => 120,
        'photos'      => [UploadedFile::fake()->image('rice.jpg', 900, 700)],
    ], $overrides);
}

it('attaches the listing to the store and copies its address', function () {
    Sanctum::actingAs($this->owner);

    // Прислан чужой адрес — у объявления магазина он берётся из магазина
    $this->postJson('/api/v1/listings', storeListingPayload([
        'region_id' => $this->otherRegion->id,
        'city_id'   => $this->otherCity->id,
    ]))->assertCreated();

    $this->assertDatabaseHas('listings', [
        'user_id'     => $this->owner->id,
        'store_id'    => $this->store->id,
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'district_id' => $this->district->id,
    ]);
});

it('saves wholesale price with a minimum order and returns both prices', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/v1/listings', storeListingPayload([
        'wholesale_price' => 95,
        'min_order_qty'   => 10,
        'stock_qty'       => 40,
    ]))->assertCreated();

    expect((float) $response->json('data.price'))->toBe(120.0)
        ->and((float) $response->json('data.wholesale_price'))->toBe(95.0)
        ->and($response->json('data.min_order_qty'))->toBe(10)
        ->and($response->json('data.stock_qty'))->toBe(40);
});

it('requires a minimum order alongside the wholesale price', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/listings', storeListingPayload(['wholesale_price' => 95]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('min_order_qty');
});

it('forbids a wholesale price for a retail-only store', function () {
    $this->store->update(['sells_wholesale' => false]);
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/listings', storeListingPayload([
        'wholesale_price' => 95, 'min_order_qty' => 10,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('wholesale_price');
});

it('forbids a wholesale price for a user without a store', function () {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    $this->postJson('/api/v1/listings', storeListingPayload([
        'wholesale_price' => 95, 'min_order_qty' => 10,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('wholesale_price');
});

it('leaves the listing address alone when the user has no store', function () {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    $this->postJson('/api/v1/listings', storeListingPayload([
        'region_id' => $this->otherRegion->id,
        'city_id'   => $this->otherCity->id,
    ]))->assertCreated();

    $this->assertDatabaseHas('listings', [
        'user_id'   => $stranger->id,
        'store_id'  => null,
        'region_id' => $this->otherRegion->id,
        'city_id'   => $this->otherCity->id,
    ]);
});

it('filters the store showcase by trade type and stock', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/listings', storeListingPayload([
        'title' => 'Оптовый', 'wholesale_price' => 95, 'min_order_qty' => 10,
    ]))->assertCreated();
    $this->postJson('/api/v1/listings', storeListingPayload([
        'title' => 'Закончился', 'stock_qty' => 0,
    ]))->assertCreated();
    $this->postJson('/api/v1/listings', storeListingPayload(['title' => 'Розничный']))->assertCreated();

    App\Models\Listing::query()->update(['status' => 'approved']);

    $this->getJson("/api/v1/stores/{$this->store->id}/listings?trade=wholesale")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Оптовый');

    // in_stock прячет только явный ноль: null означает «учёт не ведётся»
    $titles = collect(
        $this->getJson("/api/v1/stores/{$this->store->id}/listings?in_stock=1")
            ->assertOk()
            ->json('data')
    )->pluck('title');

    expect($titles)->toContain('Розничный')->not->toContain('Закончился');
});
