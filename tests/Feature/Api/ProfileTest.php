<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Region;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create(['name' => 'Old Name']);
    Sanctum::actingAs($this->user);
});

it('returns the current profile', function () {
    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.phone', $this->user->phone);
});

it('updates profile fields partially', function () {
    $region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);

    $this->putJson('/api/v1/profile', [
        'name'       => 'New Name',
        'gender'     => 'male',
        'birth_date' => '1995-04-12',
        'region_id'  => $region->id,
        'city_id'    => $city->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.region.id', $region->id);

    // Частичное обновление: остальные поля не затираются
    $this->putJson('/api/v1/profile', ['gender' => 'female'])->assertOk();

    $fresh = $this->user->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->gender)->toBe('female')
        ->and($fresh->birth_date->format('Y-m-d'))->toBe('1995-04-12');
});

it('allows clearing optional fields', function () {
    $this->putJson('/api/v1/profile', ['name' => null])
        ->assertOk()
        ->assertJsonPath('data.name', null);
});

it('validates profile fields', function () {
    $this->putJson('/api/v1/profile', [
        'gender'     => 'other',
        'birth_date' => '2999-01-01',
        'region_id'  => 999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gender', 'birth_date', 'region_id']);
});

it('uploads an avatar as webp and replaces the old one', function () {
    $first = $this->postJson('/api/v1/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('me.jpg', 800, 600),
    ])->assertOk()->json('data.avatar');

    $firstPath = ltrim(str_replace('/storage/', '', $first), '/');
    expect($first)->toEndWith('.webp');
    Storage::disk('public')->assertExists($firstPath);

    $second = $this->postJson('/api/v1/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('me2.png', 500, 500),
    ])->assertOk()->json('data.avatar');

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $second), '/'));
});

it('deletes the avatar', function () {
    $this->postJson('/api/v1/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('me.jpg', 400, 400),
    ])->assertOk();

    $path = $this->user->fresh()->avatar;

    $this->deleteJson('/api/v1/profile/avatar')
        ->assertOk()
        ->assertJsonPath('data.avatar', null);

    Storage::disk('public')->assertMissing($path);
});

it('changes phone after sms confirmation', function () {
    $newPhone = '+99365999888';

    $this->postJson('/api/v1/profile/phone/send-code', ['phone' => $newPhone])->assertOk();

    $code = app(SmsCodeRepositoryInterface::class)->findLatest($newPhone)->code;

    $this->postJson('/api/v1/profile/phone/confirm', ['phone' => $newPhone, 'code' => $code])
        ->assertOk()
        ->assertJsonPath('data.phone', $newPhone);

    expect($this->user->fresh()->phone)->toBe($newPhone);
});

it('rejects phone change to an already taken number', function () {
    $other = User::factory()->create();

    $this->postJson('/api/v1/profile/phone/send-code', ['phone' => $other->phone])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('rejects phone change with a wrong code', function () {
    $newPhone = '+99365999888';
    $oldPhone = $this->user->phone;

    $this->postJson('/api/v1/profile/phone/send-code', ['phone' => $newPhone])->assertOk();

    $this->postJson('/api/v1/profile/phone/confirm', ['phone' => $newPhone, 'code' => '000000'])
        ->assertStatus(422);

    expect($this->user->fresh()->phone)->toBe($oldPhone);
});

// mobile_docs/BACKEND_API.md §2 — store/tariff/stats/is_premium в профиле

it('reports is_premium false and no store on the free tariff', function () {
    Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt',
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => 30, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.is_premium', false)
        ->assertJsonPath('data.store', null)
        ->assertJsonPath('data.tariff.name', 'Basic')
        ->assertJsonPath('data.tariff.ads_limit', 5)
        // По can_have_store мобилка решает, показывать ли раздел «Мой магазин»:
        // сравнивать имя тарифа нельзя, набор тарифов меняется из админки
        ->assertJsonPath('data.tariff.can_have_store', false)
        ->assertJsonPath('data.tariff.can_see_wholesale', false)
        ->assertJsonPath('data.subscription.name', 'Basic');
});

it('reports is_premium true on a paid tariff', function () {
    $premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true,
        'can_have_store' => true, 'can_see_wholesale' => true,
    ]);
    app(UserRepositoryInterface::class)->assignTariff($this->user, $premium->id, now()->addDays(30));

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.is_premium', true)
        ->assertJsonPath('data.tariff.name', 'Premium')
        ->assertJsonPath('data.tariff.can_have_store', true)
        ->assertJsonPath('data.tariff.can_see_wholesale', true)
        // Сумма к передаче админу — чтобы не запрашивать каталог отдельно
        // (целое число уезжает в JSON как 250, без дробной части)
        ->assertJsonPath('data.tariff.price', 250);
});

it('rejects a nested store update on a tariff without can_have_store', function () {
    Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt',
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => 30, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);

    $this->putJson('/api/v1/profile', ['store' => ['name' => 'My shop']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('store');

    expect(Store::where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('saves a nested store on a premium tariff and returns it in the profile', function () {
    $premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium',
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);
    app(UserRepositoryInterface::class)->assignTariff($this->user, $premium->id, now()->addDays(30));

    $category = Category::create(['name_ru' => 'Одежда', 'name_tk' => 'Egin-eşik', 'slug' => 'clothes', 'level' => 1]);

    $this->putJson('/api/v1/profile', [
        'store' => [
            'name'        => 'Altyn Bazar',
            'description' => 'Optom harytlar',
            'phone'       => '+99361234567',
            'address'     => 'Aşgabat, Berkarar',
            'category_id' => $category->id,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.store.name', 'Altyn Bazar')
        ->assertJsonPath('data.store.category_name_ru', 'Одежда');

    $store = Store::where('user_id', $this->user->id)->first();
    expect($store)->not->toBeNull()
        ->and($store->name)->toBe('Altyn Bazar')
        ->and($store->category_id)->toBe($category->id);
});

it('aggregates views_count and likes_count into stats', function () {
    $region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city     = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $category = Category::create(['name_ru' => 'Разное', 'name_tk' => 'Dürli', 'slug' => 'misc', 'level' => 1]);

    $mine = Listing::create([
        'user_id' => $this->user->id, 'category_id' => $category->id, 'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Моё объявление', 'type' => 'goods', 'phone' => $this->user->phone, 'status' => 'approved',
    ]);
    // views не в $fillable (защита от мобильного клиента) — проставляем напрямую
    $mine->forceFill(['views' => 42])->save();

    $other = User::factory()->create();
    $otherListing = Listing::create([
        'user_id' => $other->id, 'category_id' => $category->id, 'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Чужое объявление', 'type' => 'goods', 'phone' => $other->phone, 'status' => 'approved',
    ]);
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $otherListing->id]);

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.stats.views_count', 42)
        ->assertJsonPath('data.stats.likes_count', 1);
});
