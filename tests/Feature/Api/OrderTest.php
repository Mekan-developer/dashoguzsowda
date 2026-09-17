<?php

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
 * Заказы: корзина собирается на устройстве, сюда приходит готовый заказ — и
 * всегда на один магазин. Заказать можно только товар магазина с доставкой;
 * решение по заказу принимает владелец магазина, он же доставляет, и остатки
 * списываются в момент, когда он принял заказ.
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

it('places an order for a single store', function () {
    Sanctum::actingAs($this->buyer);

    $second = orderListing($this->store, ['title' => 'Сахар', 'price' => 50, 'stock_qty' => null]);

    $response = $this->postJson('/api/v1/orders', orderPayload([
        ['listing_id' => $this->listing->id, 'qty' => 2],
        ['listing_id' => $second->id,        'qty' => 3],
    ]))->assertCreated();

    // 2 × 100 + 3 × 50 = 350; магазин в заказе всегда один
    expect((float) $response->json('data.total'))->toBe(350.0)
        ->and($response->json('data.status'))->toBe('pending')
        ->and($response->json('data.stores'))->toHaveCount(1)
        ->and($response->json('data.can_cancel'))->toBeTrue();

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('suborders', 1);
    $this->assertDatabaseCount('order_items', 2);

    // Пока продавец не ответил, остаток не трогаем: это ещё не резерв
    expect($this->listing->fresh()->stock_qty)->toBe(10);
});

it('refuses a cart with goods of more than one store', function () {
    // Заказ ведёт сам продавец, поэтому «общего» заказа на двух продавцов не
    // существует: мобилка режет корзину по магазинам, здесь только страховка
    $otherStore   = orderStore(User::factory()->create(), ['name' => 'Ikinji dükan']);
    $otherListing = orderListing($otherStore, ['price' => 50]);

    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', orderPayload([
        ['listing_id' => $this->listing->id, 'qty' => 1],
        ['listing_id' => $otherListing->id,  'qty' => 1],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
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

    // Опт видит только розничный продавец — покупает он как владелец магазина
    orderStore($this->buyer, ['name' => 'Bereket', 'sells_wholesale' => false]);
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

    orderStore($this->buyer, ['name' => 'Bereket', 'sells_wholesale' => false]);
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $listing->id, 'qty' => 3]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('sends the order to the store owner right after it is placed', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    // Заказ ведёт продавец, поэтому push уходит прямо ему
    Queue::assertPushed(SendPushNotificationJob::class, 1);

    Sanctum::actingAs($this->owner);
    $this->getJson('/api/v1/my/store/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_status', 'pending')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.can_respond', true)
        ->assertJsonPath('meta.pending', 1)
        ->assertJsonPath('meta.to_deliver', 0);
});

it('filters store orders by to_deliver and completed tabs', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $waiting = Order::first()->suborders()->first();

    $second = orderListing($this->store, ['title' => 'Мука', 'price' => 40, 'stock_qty' => 5]);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $second->id, 'qty' => 1]]))
        ->assertCreated();
    $toDeliver = Order::latest('id')->first()->suborders()->first();

    $third = orderListing($this->store, ['title' => 'Масло', 'price' => 30, 'stock_qty' => 5]);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $third->id, 'qty' => 1]]))
        ->assertCreated();
    $done = Order::latest('id')->first()->suborders()->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$toDeliver->id}/accept")->assertOk();
    $this->postJson("/api/v1/my/store/orders/{$done->id}/accept")->assertOk();
    $this->postJson("/api/v1/my/store/orders/{$done->id}/complete")->assertOk();

    $this->getJson('/api/v1/my/store/orders?status=to_deliver')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $toDeliver->id)
        ->assertJsonPath('meta.pending', 1)
        ->assertJsonPath('meta.to_deliver', 1);

    $this->getJson('/api/v1/my/store/orders?status=completed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $done->id);

    $this->getJson('/api/v1/my/store/orders?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $waiting->id);
});

it('searches store orders by phone, name and order number', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload(
        [['listing_id' => $this->listing->id, 'qty' => 1]],
        ['contact_name' => 'Merdan', 'phone' => '+99365000099'],
    ))->assertCreated();

    $order = Order::first();
    $suborder = $order->suborders()->first();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/my/store/orders?q=65000099')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $suborder->id);

    $this->getJson('/api/v1/my/store/orders?q=Merdan')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/my/store/orders?q='.$order->number)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $suborder->id);

    $this->getJson('/api/v1/my/store/orders?q=неттакого')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('confirms the order and takes the stock when the owner accepts it', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 3]]))
        ->assertCreated();

    $order    = Order::first();
    $suborder = $order->suborders()->first();

    // Ответ продавца и есть решение по заказу: админ в нём не участвует
    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.order_status', 'approved')
        ->assertJsonPath('data.can_respond', false)
        ->assertJsonPath('data.can_complete', true);

    expect($order->fresh()->status)->toBe('approved')
        ->and($order->fresh()->decided_by)->toBe($this->owner->id)
        ->and($this->listing->fresh()->stock_qty)->toBe(7);

    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'stock_taken' => true]);

    // Продавцу — при оформлении, покупателю — сейчас
    Queue::assertPushed(SendPushNotificationJob::class, 2);
});

it('gives the owner the contacts and the address of the buyer', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload(
        [['listing_id' => $this->listing->id, 'qty' => 1]],
        ['contact_name' => 'Merdan', 'phone' => '+99365000000', 'city_id' => $this->city->id, 'comment' => 'Позвонить за час'],
    ))->assertCreated();

    $suborder = Order::first()->suborders()->first();

    // Доставку делает продавец — без контактов и адреса заказ не выполнить
    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/my/store/orders/{$suborder->id}")
        ->assertOk()
        ->assertJsonPath('data.buyer.name', 'Merdan')
        ->assertJsonPath('data.buyer.phone', '+99365000000')
        ->assertJsonPath('data.delivery.address', 'ул. Магтымгулы, 12')
        ->assertJsonPath('data.delivery.city.id', $this->city->id)
        ->assertJsonPath('data.delivery.comment', 'Позвонить за час');
});

it('closes the order as delivered when the owner has taken it', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $suborder = Order::first()->suborders()->first();

    Sanctum::actingAs($this->owner);

    // Пока наличие не подтверждено, доставлять нечего
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/complete")->assertStatus(422);

    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")->assertOk();
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.order_status', 'completed')
        ->assertJsonPath('data.can_complete', false);

    expect(Order::first()->status)->toBe('completed');

    // Заказ продавцу, подтверждение и «доставлен» покупателю
    Queue::assertPushed(SendPushNotificationJob::class, 3);
});

it('answers the owner only once', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $suborder = Order::first()->suborders()->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")->assertOk();

    // Решение принято — переигрывать его в приложении уже нельзя
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/decline")->assertStatus(422);

    $this->getJson("/api/v1/my/store/orders/{$suborder->id}")
        ->assertOk()
        ->assertJsonPath('data.can_respond', false);
});

it('rejects the order and keeps the stock when the owner declines', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 4]]))
        ->assertCreated();

    $order    = Order::first();
    $suborder = $order->suborders()->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/decline", ['comment' => 'Товар закончился'])
        ->assertOk()
        ->assertJsonPath('data.status', 'declined')
        ->assertJsonPath('data.order_status', 'rejected');

    // Товар отказавшегося магазина никуда не едет — списаний не было
    expect($this->listing->fresh()->stock_qty)->toBe(10);
    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'stock_taken' => false]);

    // Причину отказа покупатель видит в своём заказе
    Sanctum::actingAs($this->buyer);
    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_comment', 'Товар закончился')
        ->assertJsonPath('data.stores.0.comment', 'Товар закончился')
        ->assertJsonPath('data.can_cancel', false);
});

it('hides orders of other stores', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 1]]))
        ->assertCreated();

    $suborder = Order::first()->suborders()->first();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/my/store/orders/{$suborder->id}")->assertNotFound();
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/accept")->assertNotFound();
    $this->postJson("/api/v1/my/store/orders/{$suborder->id}/complete")->assertNotFound();
});

it('lets the buyer cancel only until the owner has answered', function () {
    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 2]]))
        ->assertCreated();

    $order = Order::first();

    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    // Часть магазина закрывается вместе с заказом, а продавец узнаёт об отмене
    expect($order->suborders()->first()->fresh()->status)->toBe('canceled');
    Queue::assertPushed(SendPushNotificationJob::class, 2);

    // Второй заказ продавец успел принять — дальше вопрос решается с ним
    $this->postJson('/api/v1/orders', orderPayload([['listing_id' => $this->listing->id, 'qty' => 2]]))
        ->assertCreated();
    $accepted = Order::latest('id')->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/my/store/orders/{$accepted->suborders()->first()->id}/accept")->assertOk();

    Sanctum::actingAs($this->buyer);
    $this->postJson("/api/v1/orders/{$accepted->id}/cancel")->assertStatus(422);
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
