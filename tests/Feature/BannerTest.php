<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// User::factory() assumes an `email_verified_at` column that this project's
// users table does not have — build the row directly with real columns instead.
function actingAsBannerRole(string $role): User
{
    $user = User::factory()->create(['name' => 'Test ' . $role, 'role' => $role]);
    test()->actingAs($user);

    return $user;
}

function createListingForBanner(): Listing
{
    $region = Region::create(['name_ru' => 'Регион', 'name_tk' => 'Region']);
    $city = City::create(['region_id' => $region->id, 'name_ru' => 'Город', 'name_tk' => 'Şäher']);
    $category = Category::create(['name_ru' => 'Категория', 'slug' => Str::random(10), 'level' => 1]);
    $owner = User::factory()->create(['name' => 'Listing Owner']);

    return Listing::create([
        'user_id' => $owner->id,
        'category_id' => $category->id,
        'title' => 'Тестовое объявление',
        'region_id' => $region->id,
        'city_id' => $city->id,
        'phone' => '+99361234567',
    ]);
}

beforeEach(function () {
    Storage::fake('public');
});

it('creates a banner with a url link', function () {
    actingAsBannerRole('admin');

    $this->post(route('banners.store'), [
        'title_ru' => 'Скидка 20%',
        'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        'link_type' => 'url',
        'link_url' => 'https://example.com/promo',
    ])->assertRedirect();

    $this->assertDatabaseHas('banners', [
        'title_ru' => 'Скидка 20%',
        'link_type' => 'url',
        'link_url' => 'https://example.com/promo',
        'listing_id' => null,
    ]);
});

it('creates a banner linked to a listing', function () {
    actingAsBannerRole('admin');
    $listing = createListingForBanner();

    $this->post(route('banners.store'), [
        'title_ru' => 'Топ объявление',
        'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        'link_type' => 'listing',
        'listing_id' => $listing->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('banners', [
        'title_ru' => 'Топ объявление',
        'link_type' => 'listing',
        'listing_id' => $listing->id,
        'link_url' => null,
    ]);
});

it('creates a banner without a link', function () {
    actingAsBannerRole('admin');

    $this->post(route('banners.store'), [
        'title_ru' => 'Просто картинка',
        'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
    ])->assertRedirect();

    $this->assertDatabaseHas('banners', [
        'title_ru' => 'Просто картинка',
        'link_type' => null,
        'link_url' => null,
        'listing_id' => null,
    ]);
});

it('requires link_url when link_type is url', function () {
    actingAsBannerRole('admin');

    $this->post(route('banners.store'), [
        'title_ru' => 'Баннер',
        'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        'link_type' => 'url',
    ])->assertSessionHasErrors('link_url');
});

it('requires an existing listing_id when link_type is listing', function () {
    actingAsBannerRole('admin');

    $this->post(route('banners.store'), [
        'title_ru' => 'Баннер',
        'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        'link_type' => 'listing',
        'listing_id' => 999999,
    ])->assertSessionHasErrors('listing_id');
});

it('requires an image on create', function () {
    actingAsBannerRole('admin');

    $this->post(route('banners.store'), [
        'title_ru' => 'Баннер',
    ])->assertSessionHasErrors('image');
});

it('toggles is_active', function () {
    actingAsBannerRole('admin');
    $banner = Banner::create(['title_ru' => 'Баннер', 'image' => 'banners/test.webp', 'is_active' => true, 'sort_order' => 1]);

    $this->patch(route('banners.toggle', $banner))->assertRedirect();

    expect($banner->fresh()->is_active)->toBeFalse();
});

it('swaps sort_order with the neighbor on move', function () {
    actingAsBannerRole('admin');
    $first = Banner::create(['title_ru' => 'A', 'image' => 'banners/a.webp', 'sort_order' => 1]);
    $second = Banner::create(['title_ru' => 'B', 'image' => 'banners/b.webp', 'sort_order' => 2]);

    $this->patch(route('banners.move', $second), ['direction' => 'up'])->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(2)
        ->and($second->fresh()->sort_order)->toBe(1);
});

it('forbids manager from deleting a banner even with permission granted', function () {
    Setting::set('manager_can_manage_banners', '1');
    actingAsBannerRole('manager');
    $banner = Banner::create(['title_ru' => 'Баннер', 'image' => 'banners/test.webp']);

    $this->delete(route('banners.destroy', $banner))->assertForbidden();
});

it('blocks manager from banner routes until the permission is granted, then allows it', function () {
    actingAsBannerRole('manager');

    $this->get(route('banners.index'))->assertForbidden();

    Setting::set('manager_can_manage_banners', '1');

    $this->get(route('banners.index'))->assertOk();
});

it('returns only active and in-schedule banners via the api, ordered by sort_order', function () {
    actingAsClient();

    $second = Banner::create(['title_ru' => 'Активный', 'image' => 'banners/a.webp', 'is_active' => true, 'sort_order' => 2]);
    $first = Banner::create(['title_ru' => 'Первый', 'image' => 'banners/b.webp', 'is_active' => true, 'sort_order' => 1]);
    Banner::create(['title_ru' => 'Выключен', 'image' => 'banners/c.webp', 'is_active' => false, 'sort_order' => 3]);
    Banner::create(['title_ru' => 'Ещё не начался', 'image' => 'banners/d.webp', 'is_active' => true, 'starts_at' => now()->addDay(), 'sort_order' => 4]);
    Banner::create(['title_ru' => 'Уже закончился', 'image' => 'banners/e.webp', 'is_active' => true, 'ends_at' => now()->subDay(), 'sort_order' => 5]);

    $response = $this->getJson('/api/v1/banners')->assertOk();

    $response->assertJsonCount(2, 'data');
    expect($response->json('data.0.id'))->toBe($first->id)
        ->and($response->json('data.1.id'))->toBe($second->id);
});
