<?php

use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;

/**
 * Тариф истекает по времени, без действия пользователя, — витрину гасит
 * ежедневная команда stores:sync-visibility (routes/console.php).
 */
beforeEach(function () {
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
});

function makeVisibilityStore(User $owner, bool $isActive): Store
{
    return Store::create([
        'user_id'   => $owner->id,
        'name'      => 'Altyn Bazar',
        'status'    => 'approved',
        'is_active' => $isActive,
    ]);
}

it('turns off a store whose owner tariff expired', function () {
    $owner = User::factory()->create([
        'tariff_id' => $this->premium->id, 'tariff_ends_at' => now()->subDay(),
    ]);
    $store = makeVisibilityStore($owner, true);

    $this->artisan('stores:sync-visibility')->assertSuccessful();

    // Магазин сохранён, просто не показывается покупателям
    expect($store->fresh()->is_active)->toBeFalse()
        ->and(Store::whereKey($store->id)->exists())->toBeTrue();
});

it('turns a store back on when the tariff is valid again', function () {
    $owner = User::factory()->create([
        'tariff_id' => $this->premium->id, 'tariff_ends_at' => now()->addDays(10),
    ]);
    $store = makeVisibilityStore($owner, false);

    $this->artisan('stores:sync-visibility')->assertSuccessful();

    expect($store->fresh()->is_active)->toBeTrue();
});

it('leaves stores of owners on a tariff without the store right turned off', function () {
    $owner = User::factory()->create([
        'tariff_id' => $this->basic->id, 'tariff_ends_at' => now()->addDays(10),
    ]);
    $store = makeVisibilityStore($owner, true);

    $this->artisan('stores:sync-visibility')->assertSuccessful();

    expect($store->fresh()->is_active)->toBeFalse();
});
