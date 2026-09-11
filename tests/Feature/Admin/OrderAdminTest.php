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
