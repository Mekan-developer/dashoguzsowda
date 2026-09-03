<?php

use App\Actions\ApproveOrderAction;
use App\Actions\RejectOrderAction;
use App\Jobs\SendPushNotificationJob;
use App\Models\Category;
use App\Models\City;
use App\Models\FcmToken;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Заказы: корзина собирается на устройстве, сюда приходит готовый заказ.
 * Заказать можно только товар магазина с доставкой; остатки списываются не при
 * оформлении, а когда админ подтвердил заказ.
 */
beforeEach(function () {
    Queue::fake();

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);

    $root = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food', 'level' => 1]);
    $this->leaf = Category::create([
        'parent_id' => $root->id, 'name_ru' => 'Крупы', 'name_tk' => 'Ýarma', 'slug' => 'grains', 'level' => 2,
    ]);

    $basic = Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => null, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);
    $premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);

    $this->buyer = User::factory()->create(['tariff_id' => $basic->id]);
    $this->admin = User::factory()->create(['role' => 'admin', 'tariff_id' => $basic->id]);

    $this->owner = User::factory()->create([
        'tariff_id' => $premium->id, 'tariff_ends_at' => now()->addDays(30),
    ]);
    $this->store = orderStore($this->owner, ['has_delivery' => true]);
    $this->listing = orderListing($this->store, ['price' => 100, 'stock_qty' => 10]);

    FcmToken::create(['user_id' => $this->buyer->id, 'token' => 'buyer-token']);
    FcmToken::create(['user_id' => $this->owner->id, 'token' => 'owner-token']);
});

function orderStore(User $owner, array $overrides = []): Store
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

function orderListing(Store $store, array $overrides = []): Listing
{
    return Listing::create(array_merge([
        'user_id'     => $store->user_id,
        'store_id'    => $store->id,
        'category_id' => test()->leaf->id,
        'title'       => 'Рис длиннозёрный',
        'description' => 'Мешок 25 кг',
        'type'        => 'goods',
        'price'       => 100,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'phone'       => '+99361234567',
        'status'      => 'approved',
    ], $overrides));
}

function orderPayload(array $items, array $overrides = []): array
{
    return array_merge([
        'items'   => $items,
        'address' => 'ул. Магтымгулы, 12',
    ], $overrides);
}

it('places an order and splits it into suborders by store', function () {
    $otherOwner   = User::factory()->create();
    $otherStore   = orderStore($otherOwner, ['name' => 'Ikinji dükan']);
    $otherListing = orderListing($otherStore, ['price' => 50, 'stock_qty' => null]);

    Sanctum::actingAs($this->buyer);

    $response = $this->postJson('/api/v1/orders', orderPayload([
        ['listing_id' => $this->listing->id, 'qty' => 2],
        ['listing_id' => $otherListing->id,  'qty' => 3],
    ]))->assertCreated();

    // 2 × 100 + 3 × 50 = 350, две части заказа — по одной на магазин
    expect((float) $response->json('data.total'))->toBe(350.0)
        ->and($response->json('data.status'))->toBe('pending')
        ->and($response->json('data.stores'))->toHaveCount(2)
        ->and($response->json('data.can_cancel'))->toBeTrue();

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('suborders', 2);
    $this->assertDatabaseCount('order_items', 2);

    // Пока заказ не подтверждён, остаток не трогаем: это ещё не резерв
    expect($this->listing->fresh()->stock_qty)->toBe(10);
});

it('merges duplicate rows of the same listing', function () {
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', orderPayload([
        ['listing_id' => $this->listing->id, 'qty' => 2],
        ['listing_id' => $this->listing->id, 'qty' => 3],
    ]))->assertCreated();

    $this->assertDatabaseCount('order_items', 1);
    $this->assertDatabaseHas('order_items', ['listing_id' => $this->listing->id, 'qty' => 5]);
});

it('refuses a store without delivery', function () {
    $store   = orderStore(User::factory()->create(), ['has_delivery' => false, 'name' => 'Bazar-2']);
    $listing = orderListing($store);

    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $listing->id, 'qty' => 1]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('refuses to order more than the stock', function () {
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 11]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('refuses your own goods', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('applies the wholesale price from the minimum order quantity', function () {
    $listing = orderListing($this->store, [
        'price' => 100, 'wholesale_price' => 80, 'min_order_qty' => 5, 'stock_qty' => 50,
    ]);

    Sanctum::actingAs($this->buyer);

    // 5 × 80 — оптовая цена применилась сама, как только набралась партия
    $wholesale = $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $listing->id, 'qty' => 5]]))
        ->assertCreated()
        ->assertJsonPath('data.stores.0.items.0.is_wholesale', true);

    expect((float) $wholesale->json('data.total'))->toBe(400.0);

    // Меньше минимальной партии — цена розничная
    $retail = $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $listing->id, 'qty' => 2]]))
        ->assertCreated()
        ->assertJsonPath('data.stores.0.items.0.is_wholesale', false);

    expect((float) $retail->json('data.total'))->toBe(200.0);
});

it('refuses a wholesale-only listing below its minimum order', function () {
    $listing = orderListing($this->store, [
        'price' => null, 'wholesale_price' => 80, 'min_order_qty' => 10, 'stock_qty' => 50,
    ]);

    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $listing->id, 'qty' => 3]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('sends the order to the store owner right after it is placed', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    // Владелец отвечает первым, поэтому push уходит ему сразу
    Queue::assertPushed(SendPushNotificationJob::class, 1);

    Sanctum::actingAs($this->owner);
    $this->getJson('/api/v1/my/store/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_status', 'pending')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.can_respond', true)
        ->assertJsonPath('meta.pending', 1);
});

it('takes the stock and tells the buyer when the admin approves after the store', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 3]]))
        ->assertCreated();

    $order    = Order::first();
    $suborder = $order->suborders()->first();

    // Сначала магазин подтверждает наличие
    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    // Остаток держится до решения админа — заказ пока ничего не резервирует
    expect($this->listing->fresh()->stock_qty)->toBe(10);

    app(ApproveOrderAction::class)->execute($order, $this->admin);

    expect($order->fresh()->status)->toBe('approved')
        ->and($this->listing->fresh()->stock_qty)->toBe(7);

    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'stock_taken' => true]);

    // Владельцу — при оформлении, покупателю — сейчас
    Queue::assertPushed(SendPushNotificationJob::class, 2);
});

it('closes the answer to the owner once the admin has decided', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $order = Order::first();
    app(ApproveOrderAction::class)->execute($order, $this->admin);

    $suborder = $order->suborders()->first();

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/my/store/orders/{$suborder->id}")
        ->assertOk()
        ->assertJsonPath('data.can_respond', false);

    // Заказ уже в работе — дальше вопрос решается с админом по телефону
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/decline")->assertStatus(422);
});

it('keeps the stock of a store that declined before approval', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 4]]))
        ->assertCreated();

    $order    = Order::first();
    $suborder = $order->suborders()->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/decline", ['comment' => 'Товар закончился'])
        ->assertOk()
        ->assertJsonPath('data.status', 'declined');

    // Ответ даётся один раз
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")->assertStatus(422);

    // Даже если админ всё равно подтвердит заказ, товар отказавшегося
    // магазина никуда не едет — списывать его нельзя
    app(ApproveOrderAction::class)->execute($order->fresh(), $this->admin);

    expect($this->listing->fresh()->stock_qty)->toBe(10);
    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'stock_taken' => false]);
});

it('hides suborders of other stores', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $order = Order::first();
    app(ApproveOrderAction::class)->execute($order, $this->admin);
    $suborder = $order->suborders()->first();

    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/my/store/orders/{$suborder->id}")->assertNotFound();
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")->assertNotFound();
});

it('lets the buyer cancel only while the order is pending', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 2]]))
        ->assertCreated();

    $order = Order::first();

    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    // Второй заказ доводим до подтверждения — его отменяет уже только админ
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 2]]))
        ->assertCreated();
    $approved = Order::latest('id')->first();
    app(ApproveOrderAction::class)->execute($approved, $this->admin);

    $this->postJson("/api/v1/orders/{$approved->id}/cancel")->assertStatus(422);
});

it('keeps orders of other buyers hidden', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $order = Order::first();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertNotFound();
});

it('rejects an order with a reason and leaves the stock alone', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 2]]))
        ->assertCreated();

    $order = Order::first();
    app(RejectOrderAction::class)->execute($order, $this->admin, 'Товара нет на складе');

    Sanctum::actingAs($this->buyer);
    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.admin_comment', 'Товара нет на складе')
        ->assertJsonPath('data.can_cancel', false);

    expect($this->listing->fresh()->stock_qty)->toBe(10);
});

it('marks a listing as orderable only when it can actually be ordered', function () {
    $this->getJson("/api/v1/listings/{$this->listing->id}")
        ->assertOk()
        ->assertJsonPath('data.is_orderable', true);

    // Магазин выключил доставку — товар остаётся, корзина пропадает
    $this->store->update(['has_delivery' => false]);

    $this->getJson("/api/v1/listings/{$this->listing->id}")
        ->assertOk()
        ->assertJsonPath('data.is_orderable', false);
});
