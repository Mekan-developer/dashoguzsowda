<?php

use App\Models\City;
use App\Models\District;
use App\Models\Region;
use App\Models\User;

beforeEach(function () {
    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->district = District::create(['city_id' => $this->city->id, 'name_ru' => 'Центр', 'name_tk' => 'Merkez', 'is_hidden' => false]);
});

it('renders the directory tree', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('regions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Regions/Index')
            ->has('regions', 1)
            ->has('regions.0.cities', 1)
            ->has('regions.0.cities.0.districts', 1)
        );
});

it('validates region names', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('regions.store'), ['name_ru' => '', 'name_tk' => ''])
        ->assertSessionHasErrors(['name_ru', 'name_tk']);
});

/** Скрытие региона каскадно прячет города и районы. */
it('cascades hiding from region down to districts', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('regions.toggle', $this->region))
        ->assertRedirect();

    expect((bool) $this->region->fresh()->is_hidden)->toBeTrue()
        ->and((bool) $this->city->fresh()->is_hidden)->toBeTrue()
        ->and((bool) $this->district->fresh()->is_hidden)->toBeTrue();
});

/**
 * Обратное раскрытие каскад НЕ делает: какие города были скрыты до этого —
 * неизвестно, админ включает их точечно.
 */
it('does not cascade when a region is shown again', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('regions.toggle', $this->region));
    $this->actingAs($admin)->patch(route('regions.toggle', $this->region));

    expect((bool) $this->region->fresh()->is_hidden)->toBeFalse()
        ->and((bool) $this->city->fresh()->is_hidden)->toBeTrue();
});

it('cascades hiding from city down to districts', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('cities.toggle', $this->city))
        ->assertRedirect();

    expect((bool) $this->city->fresh()->is_hidden)->toBeTrue()
        ->and((bool) $this->district->fresh()->is_hidden)->toBeTrue();
});

it('rejects a duplicate district name within the same city', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('districts.store', $this->city), ['name_ru' => 'Центр', 'name_tk' => 'Merkez'])
        ->assertSessionHasErrors('name_ru');
});

it('allows the same district name in another city', function () {
    $other = City::create(['region_id' => $this->region->id, 'name_ru' => 'Теджен', 'name_tk' => 'Tejen']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('districts.store', $other), ['name_ru' => 'Центр', 'name_tk' => 'Merkez'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(District::where('city_id', $other->id)->count())->toBe(1);
});

it('lets a district keep its own name on update', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('districts.update', $this->district), ['name_ru' => 'Центр', 'name_tk' => 'Merkez täze'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->district->fresh()->name_tk)->toBe('Merkez täze');
});

it('lets only an admin delete directory entries', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->delete(route('regions.destroy', $this->region))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('regions.destroy', $this->region))
        ->assertRedirect();

    expect(Region::count())->toBe(0);
});
