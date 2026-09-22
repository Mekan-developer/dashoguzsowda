<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Опт видят подписчики тарифа с can_see_wholesale.
 * Гость и Basic — только розницу и обычные объявления.
 */
beforeEach(function () {
    Queue::fake();

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->leaf   = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food', 'level' => 1]);

    Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => null, 'is_free' => true, 'is_active' => true,
        'can_have_store' => false, 'can_see_wholesale' => false,
    ]);

    $this->wholesaleTariff = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true,
        'can_have_store' => true, 'can_see_wholesale' => true,
    ]);

    $this->mixedOwner = User::factory()->create();
    $this->mixed      = wholesaleStore($this->mixedOwner, ['name' => 'Altyn Bazar']);
    $this->both       = wholesaleListing($this->mixed, ['title' => 'Рис', 'price' => 100, 'wholesale_price' => 80, 'min_order_qty' => 10]);
    $this->retail     = wholesaleListing($this->mixed, ['title' => 'Сахар', 'price' => 50]);
    $this->sack       = wholesaleListing($this->mixed, ['title' => 'Мука мешками', 'price' => null, 'wholesale_price' => 70, 'min_order_qty' => 5]);

    $this->wholesalerOwner = User::factory()->create();
    $this->wholesaler      = wholesaleStore($this->wholesalerOwner, ['name' => 'Opt Merkez', 'sells_retail' => false]);
    $this->bulk            = wholesaleListing($this->wholesaler, ['title' => 'Масло ящиками', 'price' => 30, 'wholesale_price' => 25, 'min_order_qty' => 20]);

    $this->plain = Listing::create([
        'user_id' => User::factory()->create()->id, 'category_id' => $this->leaf->id,
        'title' => 'Велосипед', 'description' => 'Б/у', 'type' => 'goods', 'price' => null,
        'region_id' => $this->region->id, 'city_id' => $this->city->id,
        'phone' => '+99361000000', 'status' => 'approved',
    ]);

    $this->client = User::factory()->create();
    $this->wholesaleViewer = User::factory()->create([
        'tariff_id' => $this->wholesaleTariff->id,
        'tariff_ends_at' => now()->addDays(30),
    ]);
});

function wholesaleStore(User $owner, array $overrides = []): Store
{
    return Store::create(array_merge([
        'user_id'         => $owner->id,
        'name'            => 'Altyn Bazar',
        'phone'           => '+99361234567',
        'region_id'       => test()->region->id,
        'city_id'         => test()->city->id,
        'sells_retail'    => true,
        'sells_wholesale' => true,
        'has_delivery'    => true,
        'status'          => 'approved',
        'is_active'       => true,
    ], $overrides));
}

function wholesaleListing(Store $store, array $overrides = []): Listing
{
    return Listing::create(array_merge([
        'user_id'     => $store->user_id,
        'store_id'    => $store->id,
        'category_id' => test()->leaf->id,
        'title'       => 'Товар',
        'description' => 'Описание',
        'type'        => 'goods',
        'price'       => 100,
        'stock_qty'   => 100,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'phone'       => '+99361234567',
        'status'      => 'approved',
    ], $overrides));
}

function feedTitles(): Illuminate\Support\Collection
{
    return collect(test()->getJson('/api/v1/listings')->assertOk()->json('data'))->pluck('title');
}

it('shows a guest only retail goods and plain listings', function () {
    expect(feedTitles())
        ->toContain('Рис', 'Сахар', 'Велосипед')
        ->not->toContain('Мука мешками', 'Масло ящиками');

    $this->getJson("/api/v1/listings/{$this->both->id}")
        ->assertOk()
        ->assertJsonPath('data.price', 100)
        ->assertJsonPath('data.wholesale_price', null)
        ->assertJsonPath('data.min_order_qty', null)
        ->assertJsonPath('data.store.sells_wholesale', false);

    $this->getJson("/api/v1/listings/{$this->sack->id}")->assertNotFound();
    $this->getJson("/api/v1/listings/{$this->bulk->id}")->assertNotFound();
});

it('hides wholesale offers from a basic client', function () {
    Sanctum::actingAs($this->client);

    expect(feedTitles())
        ->toContain('Рис', 'Сахар', 'Велосипед')
        ->not->toContain('Мука мешками', 'Масло ящиками');

    $this->getJson("/api/v1/listings/{$this->sack->id}")->assertNotFound();
    $this->getJson("/api/v1/listings/{$this->both->id}")
        ->assertOk()
        ->assertJsonPath('data.wholesale_price', null);
});

it('opens wholesale to a subscriber of a tariff with can_see_wholesale', function () {
    Sanctum::actingAs($this->wholesaleViewer);

    expect(feedTitles())->toContain('Рис', 'Сахар', 'Велосипед', 'Мука мешками', 'Масло ящиками');

    $this->getJson("/api/v1/listings/{$this->both->id}")
        ->assertOk()
        ->assertJsonPath('data.wholesale_price', 80)
        ->assertJsonPath('data.min_order_qty', 10)
        ->assertJsonPath('data.store.sells_wholesale', true);

    $this->getJson("/api/v1/listings/{$this->sack->id}")->assertOk();
});

it('keeps wholesale closed when the tariff flag is off', function () {
    $this->wholesaleTariff->update(['can_see_wholesale' => false]);
    Sanctum::actingAs($this->wholesaleViewer->fresh());

    expect(feedTitles())->not->toContain('Мука мешками', 'Масло ящиками');
});

it('keeps wholesale closed to a pure wholesaler without the flag', function () {
    Sanctum::actingAs($this->wholesalerOwner);

    expect(feedTitles())->not->toContain('Мука мешками');

    $this->getJson("/api/v1/listings/{$this->bulk->id}")
        ->assertOk()
        ->assertJsonPath('data.wholesale_price', 25);
});

it('hides a pure wholesaler from the stores catalog of a basic client', function () {
    Sanctum::actingAs($this->client);

    $ids = collect($this->getJson('/api/v1/stores')->assertOk()->json('data'))->pluck('id');
    expect($ids)->toContain($this->mixed->id)->not->toContain($this->wholesaler->id);

    $this->getJson('/api/v1/stores?type=wholesale')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/stores/{$this->wholesaler->id}")->assertNotFound();
    $this->getJson("/api/v1/stores/{$this->wholesaler->id}/listings")->assertNotFound();

    $this->getJson("/api/v1/stores/{$this->mixed->id}")
        ->assertOk()
        ->assertJsonPath('data.sells_wholesale', false);
});

it('shows the wholesaler to a can_see_wholesale subscriber', function () {
    Sanctum::actingAs($this->wholesaleViewer);

    $ids = collect($this->getJson('/api/v1/stores?type=wholesale')->assertOk()->json('data'))->pluck('id');
    expect($ids)->toContain($this->mixed->id, $this->wholesaler->id);

    $this->getJson("/api/v1/stores/{$this->wholesaler->id}")
        ->assertOk()
        ->assertJsonPath('data.sells_wholesale', true);
});

it('shows the owner his own wholesale goods in the store showcase', function () {
    Sanctum::actingAs($this->mixedOwner);

    $titles = collect($this->getJson("/api/v1/stores/{$this->mixed->id}/listings?trade=wholesale")->assertOk()->json('data'))
        ->pluck('title');
    expect($titles)->toContain('Рис', 'Мука мешками');

    Sanctum::actingAs($this->wholesalerOwner);
    $this->getJson("/api/v1/stores/{$this->wholesaler->id}/listings")->assertOk()->assertJsonCount(1, 'data');
});

it('gives a basic client no wholesale in a store showcase', function () {
    Sanctum::actingAs($this->client);

    $this->getJson("/api/v1/stores/{$this->mixed->id}/listings?trade=wholesale")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $titles = collect($this->getJson("/api/v1/stores/{$this->mixed->id}/listings")->assertOk()->json('data'))->pluck('title');
    expect($titles)->toContain('Рис', 'Сахар')->not->toContain('Мука мешками');
});

it('charges a basic client the retail price even for a wholesale batch', function () {
    Sanctum::actingAs($this->client);

    $this->postJson('/api/v1/orders', [
        'items' => [['listing_id' => $this->both->id, 'qty' => 10]], 'address' => 'ул. Магтымгулы, 12',
    ])
        ->assertCreated()
        ->assertJsonPath('data.total', 1000)
        ->assertJsonPath('data.stores.0.items.0.is_wholesale', false);
});

it('gives a can_see_wholesale subscriber the wholesale price from the minimum batch', function () {
    Sanctum::actingAs($this->wholesaleViewer);

    $this->postJson('/api/v1/orders', [
        'items' => [['listing_id' => $this->both->id, 'qty' => 10]], 'address' => 'ул. Магтымгулы, 12',
    ])
        ->assertCreated()
        ->assertJsonPath('data.total', 800)
        ->assertJsonPath('data.stores.0.items.0.is_wholesale', true);
});

it('refuses a basic client an order for a wholesale offer', function () {
    Sanctum::actingAs($this->client);

    foreach ([$this->sack, $this->bulk] as $listing) {
        $this->postJson('/api/v1/orders', [
            'items' => [['listing_id' => $listing->id, 'qty' => 20]], 'address' => 'ул. Магтымгулы, 12',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    $this->assertDatabaseCount('orders', 0);
});

it('hides wholesale offers in the favorites of a basic client', function () {
    Favorite::create(['user_id' => $this->client->id, 'listing_id' => $this->sack->id]);
    Favorite::create(['user_id' => $this->client->id, 'listing_id' => $this->retail->id]);

    Sanctum::actingAs($this->client);
    $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(1, 'data');

    Favorite::create(['user_id' => $this->wholesaleViewer->id, 'listing_id' => $this->sack->id]);

    Sanctum::actingAs($this->wholesaleViewer);
    $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(1, 'data');
});
