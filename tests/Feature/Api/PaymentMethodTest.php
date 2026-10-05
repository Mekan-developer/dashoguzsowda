<?php

use App\Models\Category;
use App\Models\City;
use App\Models\FcmToken;
use App\Models\Listing;
use App\Models\PaymentMethod;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Способ оплаты заказа.
 *
 * Онлайн-оплаты в проекте нет: покупатель говорит, чем рассчитается, продавец
 * везёт заказ сам и получает деньги на месте. Справочник ведёт админ, магазин
 * отмечает из него свои способы, покупатель выбирает только из набора магазина.
 */
beforeEach(function () {
    Queue::fake();

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->leaf   = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food', 'level' => 1]);

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

    // Справочник заведён миграцией: наличные, перевод на карту, терминал
    $this->cash     = PaymentMethod::orderBy('sort_order')->first();
    $this->transfer = PaymentMethod::orderBy('sort_order')->skip(1)->first();
    $this->terminal = PaymentMethod::orderBy('sort_order')->skip(2)->first();

    $this->store = payStore($this->owner);
    // Магазин заведён напрямую, минуя API: набор проставляем сами, как это
    // сделал бы StoreService при создании магазина владельцем
    $this->store->paymentMethods()->sync([$this->cash->id]);
    $this->listing = payListing($this->store);

    FcmToken::create(['user_id' => $this->owner->id, 'token' => 'owner-token']);
});

function payStore(User $owner, array $overrides = []): Store
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

function payListing(Store $store, array $overrides = []): Listing
{
    return Listing::create(array_merge([
        'user_id'     => $store->user_id,
        'store_id'    => $store->id,
        'category_id' => test()->leaf->id,
        'title'       => 'Рис',
        'description' => 'Мешок 25 кг',
        'type'        => 'goods',
        'price'       => 100,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'phone'       => '+99361234567',
        'status'      => 'approved',
    ], $overrides));
}

function payOrderPayload(Listing $listing, array $overrides = []): array
{
    return array_merge([
        'items'   => [['listing_id' => $listing->id, 'qty' => 1]],
        'address' => 'ул. Мира, 1',
    ], $overrides);
}

// ── Набор магазина ─────────────────────────────────────────────────────────

it('даёт новому магазину способ по умолчанию, если владелец ничего не выбрал', function () {
    $owner = User::factory()->create([
        'tariff_id' => Tariff::where('can_have_store', true)->value('id'),
        'tariff_ends_at' => now()->addDays(30),
    ]);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/my/store', [
        'name'      => 'Täze Dükan',
        'phone'     => '+99361234568',
        'region_id' => $this->region->id,
        'city_id'   => $this->city->id,
    ])
        ->assertCreated()
        ->assertJsonCount(1, 'data.payment_methods')
        ->assertJsonPath('data.payment_methods.0.id', $this->cash->id);
});

it('сохраняет набор способов, выбранный владельцем', function () {
    Sanctum::actingAs($this->owner);

    $this->putJson('/api/v1/my/store', [
        'payment_method_ids' => [$this->cash->id, $this->terminal->id],
    ])->assertOk()->assertJsonCount(2, 'data.payment_methods');

    expect($this->store->paymentMethods()->pluck('payment_methods.id')->all())
        ->toEqualCanonicalizing([$this->cash->id, $this->terminal->id]);
});

it('не даёт снять у магазина все способы оплаты', function () {
    Sanctum::actingAs($this->owner);

    $this->putJson('/api/v1/my/store', ['payment_method_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method_ids');

    expect($this->store->paymentMethods()->count())->toBe(1);
});

it('не принимает выключенный способ в наборе магазина', function () {
    $this->transfer->update(['is_active' => false]);
    Sanctum::actingAs($this->owner);

    $this->putJson('/api/v1/my/store', ['payment_method_ids' => [$this->transfer->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method_ids.0');
});

it('отдаёт набор магазина в его публичной карточке', function () {
    actingAsClient();

    $this->store->paymentMethods()->sync([$this->cash->id, $this->transfer->id]);

    $this->getJson("/api/v1/stores/{$this->store->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.payment_methods')
        ->assertJsonPath('data.payment_methods.0.name_ru', $this->cash->name_ru);
});

// ── Выбор при оформлении заказа ────────────────────────────────────────────

it('записывает в заказ способ оплаты, выбранный покупателем', function () {
    $this->store->paymentMethods()->sync([$this->cash->id, $this->terminal->id]);
    Sanctum::actingAs($this->buyer);

    $response = $this->postJson('/api/v1/orders', payOrderPayload($this->listing, [
        'payment_method_id' => $this->terminal->id,
    ]))->assertCreated();

    $this->assertDatabaseHas('orders', [
        'id'                => $response->json('data.id'),
        'payment_method_id' => $this->terminal->id,
    ]);

    $this->getJson('/api/v1/orders/'.$response->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.payment_method.id', $this->terminal->id);
});

it('оставляет заказ без способа оплаты — это необязательное поле', function () {
    Sanctum::actingAs($this->buyer);

    $response = $this->postJson('/api/v1/orders', payOrderPayload($this->listing))->assertCreated();

    $this->getJson('/api/v1/orders/'.$response->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.payment_method', null);
});

it('отбивает способ оплаты, которого магазин не принимает', function () {
    $this->store->paymentMethods()->sync([$this->cash->id]);
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', payOrderPayload($this->listing, [
        'payment_method_id' => $this->transfer->id,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method_id');

    $this->assertDatabaseCount('orders', 0);
});

it('отбивает выключенный способ оплаты при заказе', function () {
    $this->store->paymentMethods()->sync([$this->cash->id, $this->transfer->id]);
    $this->transfer->update(['is_active' => false]);
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', payOrderPayload($this->listing, [
        'payment_method_id' => $this->transfer->id,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method_id');
});

it('показывает продавцу, чем с ним рассчитаются', function () {
    $this->store->paymentMethods()->sync([$this->cash->id, $this->terminal->id]);

    Sanctum::actingAs($this->buyer);
    $orderId = $this->postJson('/api/v1/orders', payOrderPayload($this->listing, [
        'payment_method_id' => $this->terminal->id,
    ]))->json('data.id');

    $suborderId = \App\Models\Suborder::where('order_id', $orderId)->value('id');

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/my/store/orders/{$suborderId}")
        ->assertOk()
        ->assertJsonPath('data.payment_method.id', $this->terminal->id)
        ->assertJsonPath('data.payment_method.name_ru', $this->terminal->name_ru);
});

// ── Справочник в админке ───────────────────────────────────────────────────

it('даёт админу вести справочник способов оплаты', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('payment-methods.store'), ['name_ru' => 'Рассрочка', 'name_tk' => 'Bölekleýin'])
        ->assertRedirect();

    $this->assertDatabaseHas('payment_methods', ['name_ru' => 'Рассрочка', 'is_active' => true]);

    $method = PaymentMethod::where('name_ru', 'Рассрочка')->first();

    $this->actingAs($admin)
        ->put(route('payment-methods.update', $method), ['is_active' => false])
        ->assertRedirect();

    expect($method->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('payment-methods.destroy', $method))
        ->assertRedirect();

    $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
});

it('даёт админу править набор способов в карточке магазина', function () {
    $this->actingAs(adminUser(), 'web')
        ->put(route('stores.update', $this->store), [
            'name'               => $this->store->name,
            'commission_percent' => 0,
            'payment_method_ids' => [$this->transfer->id, $this->terminal->id],
        ])
        ->assertRedirect();

    expect($this->store->paymentMethods()->pluck('payment_methods.id')->all())
        ->toEqualCanonicalizing([$this->transfer->id, $this->terminal->id]);
});

it('не даёт удалить способ, которым уже расплатились в заказе', function () {
    $this->store->paymentMethods()->sync([$this->cash->id]);

    Sanctum::actingAs($this->buyer);
    $this->postJson('/api/v1/orders', payOrderPayload($this->listing, [
        'payment_method_id' => $this->cash->id,
    ]))->assertCreated();

    // Веб-гард явно: Sanctum::actingAs выше подменил гард по умолчанию
    $this->actingAs(adminUser(), 'web')
        ->delete(route('payment-methods.destroy', $this->cash))
        ->assertSessionHasErrors('payment_method');

    $this->assertDatabaseHas('payment_methods', ['id' => $this->cash->id]);
});

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}
