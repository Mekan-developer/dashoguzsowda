<?php

use App\Events\SmsCodeRequested;
use App\Models\City;
use App\Models\District;
use App\Models\Region;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function actingAsUsersAdmin(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin);

    return $admin;
}

/** @return array{0: Region, 1: City, 2: District} */
function usersGeo(): array
{
    $region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city     = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $district = District::create(['city_id' => $city->id, 'name_ru' => 'Гями', 'name_tk' => 'Gämi', 'is_hidden' => false]);

    return [$region, $city, $district];
}

it('creates an immediately active user with phone and region only', function () {
    Event::fake([SmsCodeRequested::class]);
    actingAsUsersAdmin();
    [$region] = usersGeo();

    $this->post(route('users.store'), [
        'phone'      => '+99361000001',
        'activation' => 'active',
        'region_id'  => $region->id,
    ])->assertRedirect();

    $user = User::where('phone', '+99361000001')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('user')
        ->and($user->status)->toBe('active')
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBeEmpty();

    Event::assertNotDispatched(SmsCodeRequested::class);
});

it('creates an unverified user and sends sms code when activation is sms', function () {
    Event::fake([SmsCodeRequested::class]);
    actingAsUsersAdmin();
    [$region] = usersGeo();

    $this->post(route('users.store'), [
        'phone'      => '+99361000002',
        'activation' => 'sms',
        'region_id'  => $region->id,
    ])->assertRedirect();

    $user = User::where('phone', '+99361000002')->first();
    expect($user->phone_verified_at)->toBeNull();

    // Код создан и событие отправки через локальный модем поднято
    expect(app(SmsCodeRepositoryInterface::class)->findActive('+99361000002'))->not->toBeNull();
    Event::assertDispatched(SmsCodeRequested::class, fn ($e) => $e->phone === '+99361000002');
});

it('saves optional profile fields including avatar and district', function () {
    Storage::fake('public');
    actingAsUsersAdmin();
    [$region, $city, $district] = usersGeo();

    $this->post(route('users.store'), [
        'phone'       => '+99361000003',
        'activation'  => 'active',
        'region_id'   => $region->id,
        'city_id'     => $city->id,
        'district_id' => $district->id,
        'name'        => 'Мекан',
        'gender'      => 'male',
        'birth_date'  => '1995-05-20',
        'avatar'      => UploadedFile::fake()->image('avatar.jpg', 400, 400),
    ])->assertRedirect();

    $user = User::where('phone', '+99361000003')->first();
    expect($user->name)->toBe('Мекан')
        ->and($user->gender)->toBe('male')
        ->and($user->birth_date->format('Y-m-d'))->toBe('1995-05-20')
        ->and($user->city_id)->toBe($city->id)
        ->and($user->district_id)->toBe($district->id)
        ->and($user->avatar)->not->toBeNull();

    Storage::disk('public')->assertExists($user->avatar);
});

it('rejects a duplicate phone', function () {
    actingAsUsersAdmin();
    [$region] = usersGeo();
    User::factory()->create(['phone' => '+99361000004']);

    $this->from(route('users.index'))->post(route('users.store'), [
        'phone'      => '+99361000004',
        'activation' => 'active',
        'region_id'  => $region->id,
    ])->assertSessionHasErrors('phone');
});

it('rejects invalid phone format and missing region', function () {
    actingAsUsersAdmin();

    $this->post(route('users.store'), [
        'phone'      => '+99261000005', // не +993
        'activation' => 'active',
    ])->assertSessionHasErrors(['phone', 'region_id']);
});

it('rejects a city from another region and a district from another city', function () {
    actingAsUsersAdmin();
    [$region] = usersGeo();
    $otherRegion   = Region::create(['name_ru' => 'Мары', 'name_tk' => 'Mary']);
    $otherCity     = City::create(['region_id' => $otherRegion->id, 'name_ru' => 'Мары', 'name_tk' => 'Mary']);
    $otherDistrict = District::create(['city_id' => $otherCity->id, 'name_ru' => 'Векиль', 'name_tk' => 'Wekil']);

    $this->post(route('users.store'), [
        'phone'      => '+99361000006',
        'activation' => 'active',
        'region_id'  => $region->id,
        'city_id'    => $otherCity->id,
    ])->assertSessionHasErrors('city_id');

    $city = City::where('region_id', $region->id)->first();

    $this->post(route('users.store'), [
        'phone'       => '+99361000006',
        'activation'  => 'active',
        'region_id'   => $region->id,
        'city_id'     => $city->id,
        'district_id' => $otherDistrict->id,
    ])->assertSessionHasErrors('district_id');
});

it('reports phone availability for the live check', function () {
    actingAsUsersAdmin();
    $existing = User::factory()->create(['phone' => '+99361000007']);

    $this->getJson(route('users.check-phone', ['phone' => '+99361000008']))
        ->assertOk()
        ->assertJson(['available' => true, 'user_id' => null]);

    $this->getJson(route('users.check-phone', ['phone' => '+99361000007']))
        ->assertOk()
        ->assertJson(['available' => false, 'user_id' => $existing->id]);
});

it('creates an admin with email and password for panel login', function () {
    Event::fake([SmsCodeRequested::class]);
    actingAsUsersAdmin();

    $this->post(route('users.store'), [
        'role'                  => 'admin',
        'phone'                 => '+99361000010',
        'email'                 => 'newadmin@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'name'                  => 'Новый админ',
    ])->assertRedirect();

    $user = User::where('phone', '+99361000010')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('admin')
        ->and($user->email)->toBe('newadmin@example.com')
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and(\Illuminate\Support\Facades\Hash::check('secret123', $user->password))->toBeTrue();

    Event::assertNotDispatched(SmsCodeRequested::class);
});

it('creates a manager with email and password', function () {
    actingAsUsersAdmin();

    $this->post(route('users.store'), [
        'role'                  => 'manager',
        'phone'                 => '+99361000011',
        'email'                 => 'manager2@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertRedirect();

    expect(User::where('phone', '+99361000011')->value('role'))->toBe('manager');
});

it('requires email and password when creating staff', function () {
    actingAsUsersAdmin();

    $this->from(route('users.index'))->post(route('users.store'), [
        'role'  => 'admin',
        'phone' => '+99361000012',
    ])->assertSessionHasErrors(['email', 'password']);
});

it('forbids managers from creating users', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);
    [$region] = usersGeo();

    $this->post(route('users.store'), [
        'role'       => 'user',
        'phone'      => '+99361000013',
        'activation' => 'active',
        'region_id'  => $region->id,
    ])->assertForbidden();
});
