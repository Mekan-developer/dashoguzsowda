<?php

use App\Models\City;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->district = District::create(['city_id' => $this->city->id, 'name_ru' => 'Центр', 'name_tk' => 'Merkez']);

    $this->premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);
    $this->basic = Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => 30, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);

    $this->owner = User::factory()->create([
        'tariff_id' => $this->premium->id, 'tariff_ends_at' => now()->addDays(30),
    ]);
});

function storePayload(array $overrides = []): array
{
    return array_merge([
        'name'      => 'Altyn Bazar',
        'phone'     => '+99361234567',
        'region_id' => test()->region->id,
        'city_id'   => test()->city->id,
    ], $overrides);
}

it('creates a store in pending status', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', storePayload([
        'district_id'     => $this->district->id,
        'sells_wholesale' => true,
        'has_delivery'    => true,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.sells_wholesale', true)
        ->assertJsonPath('data.has_delivery', true);

    $this->assertDatabaseHas('stores', [
        'user_id' => $this->owner->id,
        'status'  => 'pending',
        'city_id' => $this->city->id,
    ]);
});

it('forbids a store without the premium tariff', function () {
    $user = User::factory()->create(['tariff_id' => $this->basic->id, 'tariff_ends_at' => now()->addDays(30)]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/my/store', storePayload())->assertForbidden();
});

it('allows only one store per user', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', storePayload())->assertCreated();
    $this->postJson('/api/v1/my/store', storePayload(['name' => 'Второй']))->assertStatus(422);
});

it('requires at least one trade type', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', storePayload([
        'sells_retail' => false, 'sells_wholesale' => false,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('sells_retail');
});

it('requires a valid phone and an address', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', ['name' => 'Без адреса', 'phone' => '12345'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone', 'region_id', 'city_id']);
});

it('sends the store back to moderation when a showcase field changes', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', storePayload())->assertCreated();
    Store::where('user_id', $this->owner->id)->update(['status' => 'approved']);

    $this->putJson('/api/v1/my/store', ['name' => 'Новое имя'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('keeps the store approved when only delivery settings change', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', storePayload())->assertCreated();
    Store::where('user_id', $this->owner->id)->update(['status' => 'approved']);

    // Доставка и вид торговли — рабочие настройки, а не витрина:
    // из-за них магазин на перемодерацию не уходит
    $this->putJson('/api/v1/my/store', ['has_delivery' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.has_delivery', true);
});

it('returns 404 when the user has no store yet', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/my/store')->assertNotFound();
});

it('uploads a logo converted to webp', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/my/store', storePayload([
        'logo' => UploadedFile::fake()->image('logo.jpg', 600, 600),
    ]))->assertCreated();

    $store = Store::where('user_id', $this->owner->id)->firstOrFail();

    expect($store->logo)->toEndWith('.webp');
    Storage::disk('public')->assertExists($store->logo);
});
