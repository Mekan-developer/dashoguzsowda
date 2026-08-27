<?php

use App\Models\City;
use App\Models\Region;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * is_profile_complete — по нему мобильное приложение решает, вести ли на
 * /register после splash/OTP (CLAUDE_CODE_BACKEND_PLAN.md, задача 1).
 */
beforeEach(function () {
    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
});

it('reports an incomplete profile for a freshly registered user', function () {
    Sanctum::actingAs(User::factory()->create(['name' => null, 'region_id' => null, 'city_id' => null]));

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.is_profile_complete', false);
});

it('reports a complete profile once name, region and city are set', function () {
    $user = User::factory()->create(['name' => null, 'region_id' => null, 'city_id' => null]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile', [
        'name'      => 'Мекан',
        'region_id' => $this->region->id,
        'city_id'   => $this->city->id,
    ])->assertOk()->assertJsonPath('data.is_profile_complete', true);

    $this->getJson('/api/v1/profile')->assertJsonPath('data.is_profile_complete', true);
});

it('stays incomplete while the city is missing', function () {
    $user = User::factory()->create(['name' => null, 'region_id' => null, 'city_id' => null]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile', ['name' => 'Мекан', 'region_id' => $this->region->id])
        ->assertOk()
        ->assertJsonPath('data.is_profile_complete', false);
});

it('does not count a blank name as filled in', function () {
    Sanctum::actingAs(User::factory()->create([
        'name' => '   ', 'region_id' => $this->region->id, 'city_id' => $this->city->id,
    ]));

    $this->getJson('/api/v1/profile')->assertJsonPath('data.is_profile_complete', false);
});

it('exposes the flag right after the sms login', function () {
    $user = User::factory()->create([
        'name' => 'Мекан', 'region_id' => $this->region->id, 'city_id' => $this->city->id,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.is_profile_complete', true)
        // плоские id, которые читает мобильное приложение
        ->assertJsonPath('data.region_id', $this->region->id)
        ->assertJsonPath('data.city_id', $this->city->id);
});
