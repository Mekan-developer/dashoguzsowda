<?php

use App\Actions\PlaceOrderAction;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Комиссия платформы с проданного товара.
 *
 * Ставка своя у каждого магазина и ставится админом в карточке магазина.
 * Считается она с каждой позиции и удерживается с магазина: сумма, которую
 * платит покупатель, от комиссии не меняется.
 */
beforeEach(function () {
    Queue::fake();

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->leaf   = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food', 'level' => 1]);

    $this->premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true,
        'can_have_store' => true, 'can_see_wholesale' => true,
    ]);

    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->buyer = User::factory()->create(['name' => 'Merdan', 'phone' => '+99361123456']);

    $this->owner = User::factory()->create([
        'tariff_id' => $this->premium->id, 'tariff_ends_at' => now()->addDays(30),
    ]);
    $this->store   = commissionStore($this->owner, ['commission_percent' => 5]);
    $this->listing = commissionListing($this->store, ['price' => 100, 'stock_qty' => 10]);
});

function commissionStore(User $owner, array $overrides = []): Store
{
    return Store::create(array_merge([
        'user_id'      => $owner->id,
        'name'         => 'Altyn Bazar',
        'phone'        => '+99361234567',
        'region_id'    => test()->region->id,
        'city_id'      => test()->city->id,
        'sells_retail' => true,
        'has_delivery' => true,
        'status'       => 'approved',
        'is_active'    => true,
    ], $overrides));
}

function commissionListing(Store $store, array $overrides = []): Listing
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

function placeCommissionOrder(array $items): App\Models\Order
{
    return app(PlaceOrderAction::class)->execute(test()->buyer, [
        'items'   => $items,
        'address' => 'ул. Магтымгулы, 12',
    ]);
}

it('считает комиссию с каждой позиции по ставке магазина', function () {
    $order = placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    $item = $order->items->first();

    // 100 × 2 = 200, комиссия 5% = 10
    expect((float) $item->total)->toBe(200.0)
        ->and((float) $item->commission_amount)->toBe(10.0)
        ->and((float) $order->suborders->first()->commission_percent)->toBe(5.0)
        ->and((float) $order->suborders->first()->commission_total)->toBe(10.0)
        ->and((float) $order->commission_total)->toBe(10.0);
});

it('не увеличивает сумму покупателя — комиссия удерживается с магазина', function () {
    $order = placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    expect((float) $order->total)->toBe(200.0)
        ->and($order->payout)->toBe(190.0)
        ->and($order->suborders->first()->payout)->toBe(190.0);
});

it('берёт свою ставку у каждого магазина', function () {
    // Заказ всегда на один магазин, поэтому ставки сравниваем по двум заказам
    $otherOwner = User::factory()->create([
        'tariff_id' => $this->premium->id, 'tariff_ends_at' => now()->addDays(30),
    ]);
    $otherStore   = commissionStore($otherOwner, ['name' => 'Bereket', 'commission_percent' => 10]);
    $otherListing = commissionListing($otherStore, ['price' => 300, 'stock_qty' => 5]);

    $ours   = placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 1]]);  // 100 × 5%  = 5
    $theirs = placeCommissionOrder([['listing_id' => $otherListing->id, 'qty' => 1]]);   // 300 × 10% = 30

    expect((float) $ours->suborders->first()->commission_total)->toBe(5.0)
        ->and((float) $ours->commission_total)->toBe(5.0)
        ->and((float) $theirs->suborders->first()->commission_total)->toBe(30.0)
        ->and((float) $theirs->commission_total)->toBe(30.0)
        ->and((float) $theirs->total)->toBe(300.0);
});

it('считает комиссию и от оптовой цены', function () {
    $wholesale = commissionListing($this->store, [
        'title' => 'Сахар', 'price' => 100, 'wholesale_price' => 80, 'min_order_qty' => 10, 'stock_qty' => 100,
    ]);

    // tariff_* нет в $fillable — update() их молча пропустил бы
    $this->buyer->forceFill([
        'tariff_id' => $this->premium->id,
        'tariff_ends_at' => now()->addDays(30),
    ])->save();

    $order = placeCommissionOrder([['listing_id' => $wholesale->id, 'qty' => 10]]);
    $item  = $order->items->first();

    expect((bool) $item->is_wholesale)->toBeTrue()
        ->and((float) $item->total)->toBe(800.0)
        ->and((float) $item->commission_amount)->toBe(40.0);
});

it('оставляет заказ без комиссии, если у магазина ставка не задана', function () {
    $this->store->update(['commission_percent' => 0]);

    $order = placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    expect((float) $order->commission_total)->toBe(0.0)
        ->and((float) $order->items->first()->commission_amount)->toBe(0.0)
        ->and($order->payout)->toBe(200.0);
});

it('фиксирует ставку на момент заказа — новая на старый заказ не влияет', function () {
    $order = placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    $this->store->update(['commission_percent' => 20]);

    $order->refresh()->load('suborders.items');

    expect((float) $order->suborders->first()->commission_percent)->toBe(5.0)
        ->and((float) $order->suborders->first()->commission_total)->toBe(10.0)
        ->and((float) $order->commission_total)->toBe(10.0);
});

it('даёт админу ставить свой процент каждому магазину', function () {
    $this->actingAs($this->admin)
        ->put(route('stores.update', $this->store->id), [
            'name'               => $this->store->name,
            'phone'              => $this->store->phone,
            'address'            => 'ул. Гарашсызлык, 1',
            'sells_retail'       => true,
            'has_delivery'       => true,
            'commission_percent' => 7.5,
        ])
        ->assertRedirect();

    expect((float) $this->store->fresh()->commission_percent)->toBe(7.5);
});

it('не принимает процент больше 100', function () {
    $this->actingAs($this->admin)
        ->put(route('stores.update', $this->store->id), [
            'name'               => $this->store->name,
            'commission_percent' => 130,
        ])
        ->assertSessionHasErrors('commission_percent');
});

it('показывает комиссию в карточке заказа в админке', function () {
    placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->where('orders.data.0.commission_total', '10.00')
            ->where('orders.data.0.suborders.0.commission_percent', '5.00')
            ->where('orders.data.0.suborders.0.commission_total', '10.00')
            ->where('orders.data.0.suborders.0.items.0.commission_amount', '10.00'));
});

it('показывает владельцу магазина его комиссию и остаток', function () {
    placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/my/store/orders')
        ->assertOk()
        ->assertJsonPath('data.0.subtotal', 200)
        ->assertJsonPath('data.0.commission_percent', 5)
        ->assertJsonPath('data.0.commission', 10)
        ->assertJsonPath('data.0.payout', 190)
        ->assertJsonPath('data.0.items.0.commission', 10)
        ->assertJsonPath('data.0.items.0.payout', 190);
});

it('не отдаёт комиссию покупателю', function () {
    $order = placeCommissionOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);

    Sanctum::actingAs($this->buyer);

    $response = $this->getJson("/api/v1/orders/{$order->id}")->assertOk();

    expect($response->json('data.total'))->toBe(200)
        ->and($response->json('data'))->not->toHaveKey('commission_total')
        ->and($response->json('data.stores.0'))->not->toHaveKey('commission');
});
