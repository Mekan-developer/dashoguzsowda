<?php

use App\Jobs\ProcessListingImagesJob;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);

    $root = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport-admin', 'level' => 1]);
    $this->leaf = Category::create([
        'parent_id' => $root->id, 'name_ru' => 'Велосипеды', 'name_tk' => 'Tigirler',
        'slug' => 'bikes-admin', 'level' => 2,
    ]);

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function adminListingPayload(array $overrides = []): array
{
    return array_merge([
        'user_id'     => test()->owner->id,
        'title'       => 'Админское объявление',
        'description' => 'Создано из админки',
        'type'        => 'goods',
        'category_id' => test()->leaf->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'price'       => 1500,
        'photos'      => [UploadedFile::fake()->image('bike.jpg', 900, 700)],
    ], $overrides);
}

it('lets admin create an approved listing for a user', function () {
    $this->actingAs($this->admin);

    $this->post(route('listings.store'), adminListingPayload())
        ->assertRedirect();

    $listing = Listing::where('user_id', $this->owner->id)->first();
    expect($listing)->not->toBeNull()
        ->and($listing->status)->toBe('approved')
        ->and($listing->title)->toBe('Админское объявление');

    Queue::assertPushed(ProcessListingImagesJob::class);
});

it('lets manager create a listing', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $this->actingAs($manager);

    $this->post(route('listings.store'), adminListingPayload([
        'title' => 'От менеджера',
    ]))->assertRedirect();

    expect(Listing::where('title', 'От менеджера')->exists())->toBeTrue();
});

it('lets admin update listing fields without resetting status', function () {
    $listing = Listing::create([
        'user_id' => $this->owner->id,
        'category_id' => $this->leaf->id,
        'region_id' => $this->region->id,
        'city_id' => $this->city->id,
        'title' => 'Старое',
        'description' => 'Описание',
        'type' => 'goods',
        'phone' => $this->owner->phone,
        'status' => 'approved',
    ]);
    ListingMedia::create(['listing_id' => $listing->id, 'path' => 'listings/1/a.webp', 'type' => 'image', 'order' => 0]);

    $this->actingAs($this->admin);

    $this->patch(route('listings.update', $listing), [
        'title' => 'Новое название',
        'description' => 'Новое описание',
        'type' => 'goods',
        'category_id' => $this->leaf->id,
        'region_id' => $this->region->id,
        'city_id' => $this->city->id,
        'phone' => '+99361111111',
    ])->assertRedirect();

    $listing->refresh();
    expect($listing->title)->toBe('Новое название')
        ->and($listing->status)->toBe('approved')
        ->and($listing->phone)->toBe('+99361111111');
});

it('lets admin boost and delete a listing', function () {
    $listing = Listing::create([
        'user_id' => $this->owner->id,
        'category_id' => $this->leaf->id,
        'region_id' => $this->region->id,
        'city_id' => $this->city->id,
        'title' => 'На удаление',
        'description' => 'Описание',
        'type' => 'goods',
        'phone' => $this->owner->phone,
        'status' => 'approved',
    ]);

    $this->actingAs($this->admin);

    $this->patch(route('listings.boost', $listing))->assertRedirect();
    expect($listing->fresh()->is_boosted)->toBeTrue();

    $this->delete(route('listings.destroy', $listing))->assertRedirect(route('listings.index'));
    expect(Listing::find($listing->id))->toBeNull();
});

it('forbids manager from deleting a listing', function () {
    $listing = Listing::create([
        'user_id' => $this->owner->id,
        'category_id' => $this->leaf->id,
        'region_id' => $this->region->id,
        'city_id' => $this->city->id,
        'title' => 'Защищённое',
        'description' => 'Описание',
        'type' => 'goods',
        'phone' => $this->owner->phone,
        'status' => 'approved',
    ]);
    $manager = User::factory()->create(['role' => 'manager']);
    $this->actingAs($manager);

    $this->delete(route('listings.destroy', $listing))->assertForbidden();
});
