<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Раздел «Заказы» в админке. Онлайн-оплаты нет: заказ ведёт админ руками —
 * обзванивает магазины, подтверждает наличие и везёт. Поэтому «Подтвердить»
 * здесь имеет побочные эффекты (списание остатков, рассылка магазинам),
 * а менеджеру раздел закрыт целиком.
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

it('shows which store each order belongs to and what the store answered', function () {
    placeAdminOrder();

    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.status', 'pending')
            ->where('orders.data.0.items_count', 1)
            // Магазин и его ответ — то, ради чего раздел и нужен
            ->has('orders.data.0.suborders', 1)
            ->where('orders.data.0.suborders.0.store.name', 'Altyn Bazar')
            ->where('orders.data.0.suborders.0.status', 'pending')
            // Состав приходит сразу: строка раскрывается без второго запроса
            ->has('orders.data.0.suborders.0.items', 1)
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

it('takes the stock when the admin approves an order', function () {
    $order = placeAdminOrder();

    $this->actingAs($this->admin)
        ->patch(route('orders.approve', $order->id))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('approved')
        ->and($this->listing->fresh()->stock_qty)->toBe(8);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'processed_by' => $this->admin->id]);
});

it('closes the store parts along with the order status', function () {
    // Подтверждение: магазин не отвечал в приложении — админ подтвердил заказ
    // после разговора с ним, значит наличие подтверждено
    $approved = placeAdminOrder();
    $this->actingAs($this->admin)->patch(route('orders.approve', $approved->id));

    expect($approved->suborders()->first()->fresh()->status)->toBe('accepted');

    // Заказ доставлен — части остаются подтверждёнными
    $this->actingAs($this->admin)->patch(route('orders.complete', $approved->id));
    expect($approved->suborders()->first()->fresh()->status)->toBe('accepted');

    // Отмена: части закрываются вместе с заказом, а не висят в «ждём ответа»
    $canceled = placeAdminOrder();
    $this->actingAs($this->admin)->patch(route('orders.cancel', $canceled->id));
    expect($canceled->suborders()->first()->fresh()->status)->toBe('canceled');

    // Отказ — то же самое
    $rejected = placeAdminOrder();
    $this->actingAs($this->admin)->patch(route('orders.reject', $rejected->id), ['comment' => 'Товара нет']);
    expect($rejected->suborders()->first()->fresh()->status)->toBe('canceled');
});

it('keeps the answer of a store that already declined', function () {
    $order    = placeAdminOrder();
    $suborder = $order->suborders()->first();

    app(\App\Actions\RespondToSuborderAction::class)
        ->execute($suborder, $this->owner, 'declined', 'Товар закончился');

    $this->actingAs($this->admin)->patch(route('orders.approve', $order->id));

    // Отказ магазина не переписывается подтверждением заказа
    expect($suborder->fresh()->status)->toBe('declined')
        ->and($suborder->fresh()->comment)->toBe('Товар закончился')
        // И товар отказавшегося магазина не списан
        ->and($this->listing->fresh()->stock_qty)->toBe(10);
});

it('requires a reason to reject an order', function () {
    $order = placeAdminOrder();

    $this->actingAs($this->admin)
        ->patch(route('orders.reject', $order->id), [])
        ->assertSessionHasErrors('comment');

    $this->actingAs($this->admin)
        ->patch(route('orders.reject', $order->id), ['comment' => 'Товара нет на складе'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('rejected')
        ->and($order->fresh()->admin_comment)->toBe('Товара нет на складе')
        // Отказ до подтверждения остатков не касался — списания не было
        ->and($this->listing->fresh()->stock_qty)->toBe(10);
});

it('closes a confirmed order as delivered', function () {
    $order = placeAdminOrder();
    $this->actingAs($this->admin)->patch(route('orders.approve', $order->id));

    $this->actingAs($this->admin)
        ->patch(route('orders.complete', $order->id))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('completed');
});

it('refuses to close an order the admin has not confirmed yet', function () {
    $order = placeAdminOrder();

    $this->actingAs($this->admin)
        ->patch(route('orders.complete', $order->id))
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe('pending');
});

it('returns the stock when the admin cancels a confirmed order', function () {
    $order = placeAdminOrder();
    $this->actingAs($this->admin)->patch(route('orders.approve', $order->id));
    expect($this->listing->fresh()->stock_qty)->toBe(8);

    $this->actingAs($this->admin)
        ->patch(route('orders.cancel', $order->id))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('canceled')
        ->and($this->listing->fresh()->stock_qty)->toBe(10);
});

it('closes the orders section to a manager', function () {
    $manager = User::factory()->manager()->create();
    $order   = placeAdminOrder();

    $this->actingAs($manager)->get(route('orders.index'))->assertForbidden();
    $this->actingAs($manager)->patch(route('orders.approve', $order->id))->assertForbidden();
    $this->actingAs($manager)->patch(route('orders.cancel', $order->id))->assertForbidden();
});
