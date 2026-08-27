<?php

use App\Models\City;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->ahal      = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->mary      = Region::create(['name_ru' => 'Мары', 'name_tk' => 'Mary']);
    $this->anau      = City::create(['region_id' => $this->ahal->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->baýramaly = City::create(['region_id' => $this->mary->id, 'name_ru' => 'Байрамали', 'name_tk' => 'Baýramaly']);
    // districts.is_hidden по умолчанию true — выбрать можно только показанный район
    $this->anauEtrap = District::create([
        'city_id' => $this->anau->id, 'name_ru' => 'Центр', 'name_tk' => 'Merkez', 'is_hidden' => false,
    ]);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

// ─── В-12: связка регион → город → район ────────────────────────────────────

it('saves region, city and district when they belong to each other', function () {
    $this->putJson('/api/v1/profile', [
        'region_id'   => $this->ahal->id,
        'city_id'     => $this->anau->id,
        'district_id' => $this->anauEtrap->id,
    ])->assertOk()
        ->assertJsonPath('data.region_id', $this->ahal->id)
        ->assertJsonPath('data.city_id', $this->anau->id)
        ->assertJsonPath('data.district_id', $this->anauEtrap->id)
        ->assertJsonPath('data.district.name_ru', 'Центр');
});

it('rejects a city that belongs to another region', function () {
    $this->putJson('/api/v1/profile', [
        'region_id' => $this->ahal->id,
        'city_id'   => $this->baýramaly->id,
    ])->assertStatus(422)->assertJsonValidationErrors('city_id');
});

it('rejects a district that belongs to another city', function () {
    $this->putJson('/api/v1/profile', [
        'region_id'   => $this->mary->id,
        'city_id'     => $this->baýramaly->id,
        'district_id' => $this->anauEtrap->id,
    ])->assertStatus(422)->assertJsonValidationErrors('district_id');
});

/** Частичное обновление: город остаётся от прежнего региона и должен стать невалидным. */
it('rejects changing only the region when the saved city no longer fits', function () {
    $this->putJson('/api/v1/profile', [
        'region_id' => $this->ahal->id,
        'city_id'   => $this->anau->id,
    ])->assertOk();

    $this->putJson('/api/v1/profile', ['region_id' => $this->mary->id])
        ->assertStatus(422)->assertJsonValidationErrors('city_id');
});

it('still allows updating unrelated fields without touching location', function () {
    $this->putJson('/api/v1/profile', ['name' => 'Мекан'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Мекан');
});

// ─── В-11: привилегированные поля не проходят mass assignment ───────────────

it('ignores privileged fields sent to the profile endpoint', function () {
    $this->putJson('/api/v1/profile', [
        'name'              => 'Мекан',
        'role'              => 'admin',
        'status'            => 'blocked',
        'tariff_id'         => 999,
        'phone_verified_at' => null,
    ])->assertOk();

    $fresh = $this->user->fresh();

    expect($fresh->role)->toBe('user')
        ->and($fresh->status)->toBe('active')
        ->and($fresh->tariff_id)->toBeNull()
        ->and($fresh->phone_verified_at)->not->toBeNull();
});

it('does not mass assign privileged attributes on the model itself', function () {
    $user = new User();
    $user->fill([
        'name'      => 'Мекан',
        'role'      => 'admin',
        'status'    => 'blocked',
        'tariff_id' => 999,
    ]);

    expect($user->name)->toBe('Мекан')
        ->and($user->role)->toBeNull()
        ->and($user->status)->toBeNull()
        ->and($user->tariff_id)->toBeNull();
});
