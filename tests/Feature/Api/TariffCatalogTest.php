<?php

use App\Models\Tariff;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt',
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => 30, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);
    Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium',
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);
    // Неактивный, со slug — не должен попадать в каталог и не принимается PUT /subscription
    Tariff::create([
        'name' => 'Archived', 'name_ru' => 'Архивный', 'name_tk' => 'Arhiw',
        'listings_limit' => 1, 'videos_limit' => 1, 'boost_limit' => 1,
        'duration_days' => 30, 'is_free' => false, 'is_active' => false,
    ]);
    // Без slug — не должен попадать в мобильный каталог
    Tariff::create([
        'name' => null, 'name_ru' => 'Кастомный', 'name_tk' => 'Adaty däl',
        'listings_limit' => 1, 'videos_limit' => 1, 'boost_limit' => 1,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true,
    ]);

    $this->user = User::factory()->create();
});

it('requires auth to read the tariff catalog', function () {
    $this->getJson('/api/v1/tariffs')->assertUnauthorized();
});

it('lists only active tariffs with a mobile slug, with zeroed usage', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/tariffs')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Basic')->toContain('Premium')->not->toContain(null);

    $response->assertJsonPath('data.0.name', 'Basic')
        ->assertJsonPath('data.0.ads_limit', 5)
        ->assertJsonPath('data.0.ads_used', 0)
        ->assertJsonPath('data.1.name', 'Premium')
        ->assertJsonPath('data.1.ads_used', 0);
});

it('creates a request instead of granting a paid tariff', function () {
    Sanctum::actingAs($this->user);

    // Оплата идёт наличными админу, поэтому платный тариф здесь не выдаётся
    $this->putJson('/api/v1/profile/subscription', ['tariff_name' => 'Premium'])
        ->assertStatus(202)
        ->assertJsonPath('data.tariff_request.status', 'pending')
        ->assertJsonPath('data.tariff_request.tariff_name', 'Premium')
        ->assertJsonPath('data.is_premium', false);

    expect($this->user->fresh()->tariff)->toBeNull();
    $this->assertDatabaseHas('tariff_requests', [
        'user_id' => $this->user->id,
        'status'  => 'pending',
    ]);
});

it('rejects a second pending request', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile/subscription', ['tariff_name' => 'Premium'])->assertStatus(202);

    $this->putJson('/api/v1/profile/subscription', ['tariff_name' => 'Premium'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tariff_name');
});

it('assigns a free tariff immediately', function () {
    Sanctum::actingAs($this->user);

    // Бесплатный тариф денег не требует — это способ отказаться от платного
    $this->putJson('/api/v1/profile/subscription', ['tariff_name' => 'Basic'])
        ->assertOk()
        ->assertJsonPath('data.tariff.name', 'Basic');

    expect($this->user->fresh()->tariff->name)->toBe('Basic');
    $this->assertDatabaseCount('tariff_requests', 0);
});

it('rejects an unknown tariff slug', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile/subscription', ['tariff_name' => 'Nonexistent'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tariff_name');
});

it('rejects an inactive tariff slug', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile/subscription', ['tariff_name' => 'Archived'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tariff_name');
});
