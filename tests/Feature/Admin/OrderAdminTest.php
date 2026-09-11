<?php

use App\Actions\RespondToSuborderAction;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Раздел «Заказы» в админке — только просмотр.
 *
 * Заказ ведёт владелец магазина: он подтверждает наличие, доставляет сам и
 * получает деньги. Админу раздел нужен, чтобы видеть, что, у кого и кому
 * продано, поэтому действий здесь нет ни одного. Менеджеру закрыт целиком.
 */
beforeEach(function () {
    Queue::fake();

    $this->admin = User::factory()->admin()->create();

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->leaf   = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food', 'level' => 1]);

    $this->owner   = User::factory()->create();
    $this->store   = adminOrderStore($this->owner, ['name' => 'Altyn Bazar']);
    $this->listing = adminOrderListing($this->store, ['stock_qty' => 10]);
    $this->buyer   = User::factory()->create(['name' => 'Merdan', 'phone' => '+99361123456']);
});

function adminOrderStore(User $owner, array $overrides = []): Store
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

function adminOrderListing(Store $store, array $overrides = []): Listing
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

/** Заказ, оформленный покупателем через мобильное API. */
function placeAdminOrder(array $items = null, User $buyer = null): Order
{
    $order = app(\App\Actions\PlaceOrderAction::class)->execute(
        $buyer ?? test()->buyer,
        [
            'items'   => $items ?? [['listing_id' => test()->listing->id, 'qty' => 2]],
            'address' => 'ул. Магтымгулы, 12',
        ],
    );

    return $order;
}

it('shows what was sold, by whom and to whom', function () {
    placeAdminOrder();

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.status', 'pending')
            ->where('orders.data.0.items_count', 1)
            ->where('orders.data.0.total', '200.00')
            // Кому продано
            ->where('orders.data.0.contact_name', 'Merdan')
            // Кто продал: магазин и его владелец
            ->has('orders.data.0.suborders', 1)
            ->where('orders.data.0.suborders.0.store.name', 'Altyn Bazar')
            ->where('orders.data.0.suborders.0.user.id', $this->owner->id)
            ->where('orders.data.0.suborders.0.status', 'pending')
            // Что именно продано — состав приходит сразу, без второго запроса
            ->has('orders.data.0.suborders.0.items', 1)
            ->where('orders.data.0.suborders.0.items.0.title', 'Рис длиннозёрный')
            ->where('orders.data.0.suborders.0.items.0.qty', 2)
            ->where('orders.data.0.suborders.0.items.0.total', '200.00')
            ->where('counts.pending', 1)
            ->has('stores', 1),
        );
});

it('filters orders by store', function () {
    placeAdminOrder();

    $otherStore   = adminOrderStore(User::factory()->create(), ['name' => 'Ikinji dükan']);
    $otherListing = adminOrderListing($otherStore);
    placeAdminOrder([['listing_id' => $otherListing->id, 'qty' => 1]]);

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['store_id' => $otherStore->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.suborders.0.store.name', 'Ikinji dükan'),
        );
});

it('finds an order by its number', function () {
    $order = placeAdminOrder();

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['search' => $order->number]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));

    // Нечисловой поиск не должен ронять запрос — ищем по имени покупателя
    $this->actingAs($this->admin)
        ->get(route('orders.index', ['search' => 'Merdan']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
});

it('shows the decision of the seller and who made it', function () {
    $order    = placeAdminOrder();
    $suborder = $order->suborders()->first();

    app(RespondToSuborderAction::class)
        ->execute($suborder, $this->owner, 'declined', 'Товар закончился');

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.status', 'rejected')
            ->where('orders.data.0.decision_comment', 'Товар закончился')
            ->where('orders.data.0.decider.id', $this->owner->id)
            ->where('orders.data.0.suborders.0.status', 'declined'),
        );
});

it('sorts orders by date in both directions', function () {
    $this->travelTo(now()->subDays(3));
    $old = placeAdminOrder();
    $this->travelBack();
    $new = placeAdminOrder();

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'desc')
            ->where('orders.data.0.id', $new->id)
            ->where('orders.data.1.id', $old->id),
        );

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['sort' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.id', $old->id)
            ->where('orders.data.1.id', $new->id),
        );
});

it('does not lift waiting orders above the date order', function () {
    // Админ заказы не ведёт: старый «ждущий» не должен обгонять свежий принятый
    $this->travelTo(now()->subDays(3));
    placeAdminOrder();
    $this->travelBack();

    $fresh = placeAdminOrder();
    app(RespondToSuborderAction::class)->execute($fresh->suborders()->first(), $this->owner, 'accepted');

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $fresh->id));
});

it('filters orders by date range', function () {
    $this->travelTo(now()->subDays(10));
    placeAdminOrder();
    $this->travelBack();
    $recent = placeAdminOrder();

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['from' => now()->subDays(2)->toDateString(), 'to' => now()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $recent->id)
            ->where('summary.orders', 1),
        );
});

it('rejects a broken sort or date range', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.index', ['sort' => 'sideways']))
        ->assertSessionHasErrors('sort');

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['from' => '2026-09-10', 'to' => '2026-09-01']))
        ->assertSessionHasErrors('to');
});

it('sums up orders without declined and canceled ones', function () {
    $second = User::factory()->create(['name' => 'Aýna']);

    placeAdminOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);          // 200
    placeAdminOrder([['listing_id' => $this->listing->id, 'qty' => 3]], $second); // 300

    // Отказ продавца денег не принёс: заказ считается, штуки и сумма — нет
    $declined = placeAdminOrder([['listing_id' => $this->listing->id, 'qty' => 1]]);
    app(RespondToSuborderAction::class)->execute($declined->suborders()->first(), $this->owner, 'declined');

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.orders', 3)
            ->where('summary.buyers', 2)
            ->where('summary.qty', 5)
            ->where('summary.total', 500)
            ->where('summary.commission', 0),
        );
});

it('shows who ordered how much and at which store', function () {
    $otherStore   = adminOrderStore(User::factory()->create(), ['name' => 'Ikinji dükan']);
    $otherListing = adminOrderListing($otherStore, ['price' => 50]);
    $second       = User::factory()->create(['name' => 'Aýna', 'phone' => '+99365000000']);

    // Merdan: два заказа в Altyn Bazar и один в Ikinji dükan, последний — давно
    $this->travelTo(now()->subDays(5));
    placeAdminOrder([['listing_id' => $this->listing->id, 'qty' => 2]]);   // 200
    placeAdminOrder([['listing_id' => $this->listing->id, 'qty' => 1]]);   // 100
    placeAdminOrder([['listing_id' => $otherListing->id, 'qty' => 4]]);    // 200
    $this->travelBack();

    // Aýna заказывала позже всех
    placeAdminOrder([['listing_id' => $otherListing->id, 'qty' => 1]], $second);

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['view' => 'buyers']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders', null)
            ->has('buyers.data', 2)
            // Сначала тот, кто заказывал последним
            ->where('buyers.data.0.name', 'Aýna')
            ->where('buyers.data.1.name', 'Merdan')
            ->where('buyers.data.1.phone', '+99361123456')
            ->where('buyers.data.1.orders_count', 3)
            ->where('buyers.data.1.qty', 7)
            ->where('buyers.data.1.total', 500)
            ->has('buyers.data.1.stores', 2)
            ->where('buyers.data.1.stores.0.name', 'Altyn Bazar')
            ->where('buyers.data.1.stores.0.orders_count', 2)
            ->where('buyers.data.1.stores.1.name', 'Ikinji dükan')
            ->where('buyers.data.1.stores.1.orders_count', 1),
        );

    $this->actingAs($this->admin)
        ->get(route('orders.index', ['view' => 'buyers', 'sort' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page->where('buyers.data.0.name', 'Merdan'));
});

it('gives the admin no way to decide on an order', function () {
    // Решение принимает продавец: у админки нет ни одного маршрута действия
    expect(Route::has('orders.approve'))->toBeFalse()
        ->and(Route::has('orders.reject'))->toBeFalse()
        ->and(Route::has('orders.cancel'))->toBeFalse()
        ->and(Route::has('orders.complete'))->toBeFalse();

    $this->actingAs($this->admin)->patch('/admin/orders/1/approve')->assertNotFound();
});

it('closes the orders section to a manager', function () {
    $manager = User::factory()->manager()->create();
    placeAdminOrder();

    $this->actingAs($manager)->get(route('orders.index'))->assertForbidden();
});
