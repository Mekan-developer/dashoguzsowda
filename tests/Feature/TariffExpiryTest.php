<?php

use App\Actions\AssignTariffAction;
use App\Events\TariffExpired;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Region;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Video;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Бесплатный тариф выдаётся при создании, платный по истечении сменяется
 * бесплатным, а контент сверх его лимитов скрывается (CLAUDE.md → «Тарифы»).
 */
beforeEach(function () {
    Queue::fake();

    $this->free = Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'listings_limit' => 2, 'videos_limit' => 1, 'boost_limit' => 1,
        'duration_days' => null, 'is_free' => true, 'is_active' => true, 'can_have_store' => false,
    ]);
    $this->premium = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 10, 'videos_limit' => 5, 'boost_limit' => 5,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true,
    ]);

    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport', 'level' => 1]);

    $this->user = User::factory()->create([
        'tariff_id' => $this->premium->id, 'tariff_ends_at' => now()->subHour(),
    ]);
});

/** Объявление «возрастом» $daysAgo дней — порядок скрытия идёт по created_at */
function expiryListing(int $daysAgo, string $status = 'approved'): Listing
{
    $listing = Listing::create([
        'user_id'     => test()->user->id,
        'category_id' => test()->category->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'title'       => "Объявление {$daysAgo}",
        'type'        => 'goods',
        'phone'       => test()->user->phone,
        'status'      => $status,
    ]);
    Listing::whereKey($listing->id)->update(['created_at' => now()->subDays($daysAgo)]);

    return $listing;
}

function expiryVideo(int $daysAgo, string $status = 'approved'): Video
{
    $video = Video::create([
        'user_id'          => test()->user->id,
        'category_id'      => test()->category->id,
        'title'            => "Ролик {$daysAgo}",
        'path'             => 'videos/'.uniqid().'/original.mp4',
        'duration_seconds' => 10,
        'status'           => $status,
    ]);
    Video::whereKey($video->id)->update(['created_at' => now()->subDays($daysAgo)]);

    return $video;
}

// ─── Бесплатный тариф при создании ─────────────────────────────────────────

it('gives the free tariff to a user registered by sms', function () {
    $phone = '+99365000111';

    $this->postJson('/api/v1/auth/send-code', ['phone' => $phone])->assertOk();
    $code = app(SmsCodeRepositoryInterface::class)->findLatest($phone)->code;

    $this->postJson('/api/v1/auth/verify', ['phone' => $phone, 'code' => $code])
        ->assertOk()
        ->assertJsonPath('data.is_new', true);

    $user = User::where('phone', $phone)->first();

    expect($user->tariff_id)->toBe($this->free->id)
        ->and($user->tariff_ends_at)->toBeNull();
});

it('does not give a tariff to panel staff', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->tariff_id)->toBeNull();
});

// ─── Истечение ─────────────────────────────────────────────────────────────

it('moves a user with an expired paid tariff to the free one', function () {
    Event::fake([TariffExpired::class]);

    $this->artisan('tariffs:expire')->assertSuccessful();

    $this->user->refresh();
    expect($this->user->tariff_id)->toBe($this->free->id)
        ->and($this->user->tariff_ends_at)->toBeNull();

    Event::assertDispatched(TariffExpired::class, fn ($e) => $e->user->is($this->user)
        && $e->expiredTariff->is($this->premium));
});

it('leaves a still valid paid tariff alone', function () {
    $this->user->forceFill(['tariff_ends_at' => now()->addDay()])->save();
    expiryListing(1);
    expiryListing(2);
    expiryListing(3);

    $this->artisan('tariffs:expire')->assertSuccessful();

    expect($this->user->fresh()->tariff_id)->toBe($this->premium->id)
        ->and(Listing::where('status', 'suspended')->count())->toBe(0);
});

it('hides the oldest approved listings and videos over the free limits', function () {
    Event::fake([TariffExpired::class]);

    $newest  = expiryListing(1);
    $middle  = expiryListing(5);
    $oldest  = expiryListing(9);
    $older   = expiryListing(7);
    $newVid  = expiryVideo(1);
    $oldVid  = expiryVideo(4);

    $this->artisan('tariffs:expire')->assertSuccessful();

    // Лимит бесплатного — 2 объявления и 1 ролик: остаются самые свежие
    expect($newest->fresh()->status)->toBe('approved')
        ->and($middle->fresh()->status)->toBe('approved')
        ->and($older->fresh()->status)->toBe('suspended')
        ->and($oldest->fresh()->status)->toBe('suspended')
        ->and($newVid->fresh()->status)->toBe('approved')
        ->and($oldVid->fresh()->status)->toBe('suspended');

    Event::assertDispatched(TariffExpired::class, fn ($e) => $e->suspendedListings === 2 && $e->suspendedVideos === 1);
});

it('counts pending listings towards the quota but never hides them', function () {
    $pending  = expiryListing(1, 'pending');
    $approved = expiryListing(2);
    $old      = expiryListing(3);

    $this->artisan('tariffs:expire')->assertSuccessful();

    expect($pending->fresh()->status)->toBe('pending')
        ->and($approved->fresh()->status)->toBe('approved')
        ->and($old->fresh()->status)->toBe('suspended');
});

it('removes suspended listings from the public feed but shows them to the owner', function () {
    actingAsClient();

    $kept   = expiryListing(1);
    expiryListing(2);
    $hidden = expiryListing(3);

    $this->artisan('tariffs:expire')->assertSuccessful();

    $this->getJson('/api/v1/listings')
        ->assertOk()
        ->assertJsonMissing(['id' => $hidden->id])
        ->assertJsonFragment(['id' => $kept->id]);

    $this->getJson("/api/v1/listings/{$hidden->id}")->assertNotFound();

    Sanctum::actingAs($this->user->fresh());
    $this->getJson('/api/v1/listings/my?status=suspended')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $hidden->id)
        ->assertJsonPath('data.0.status', 'suspended');
});

it('turns off the store when the tariff with the store right expires', function () {
    $store = Store::create([
        'user_id' => $this->user->id, 'name' => 'Altyn', 'status' => 'approved', 'is_active' => true,
    ]);

    $this->artisan('tariffs:expire')->assertSuccessful();

    expect($store->fresh()->is_active)->toBeFalse();
});

it('fails without touching anyone when there is no free tariff', function () {
    $this->free->update(['is_free' => false]);
    expiryListing(1);
    expiryListing(2);
    expiryListing(3);

    $this->artisan('tariffs:expire')->assertFailed();

    expect($this->user->fresh()->tariff_id)->toBe($this->premium->id)
        ->and(Listing::where('status', 'suspended')->count())->toBe(0);
});

it('pushes the user how much was hidden', function () {
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->withArgs(function ($user, $title, $body, $data) {
        return $user->is($this->user)
            && $title === __('messages.push_tariff_expired_title')
            && $body === __('messages.push_tariff_expired_body_hidden', ['tariff' => 'Premium', 'listings' => 1, 'videos' => 0])
            && $data === ['type' => 'tariff', 'id' => (string) $this->free->id];
    });
    $this->app->instance(PushNotificationService::class, $push);

    expiryListing(1);
    expiryListing(2);
    expiryListing(3);

    $this->artisan('tariffs:expire')->assertSuccessful();
});

// ─── Возврат при новом тарифе ──────────────────────────────────────────────

it('restores the newest suspended content when a bigger tariff is assigned', function () {
    $this->user->forceFill(['tariff_id' => $this->free->id, 'tariff_ends_at' => null])->save();
    $active    = expiryListing(1);
    $newest    = expiryListing(2, 'suspended');
    $oldest    = expiryListing(9, 'suspended');
    $video     = expiryVideo(3, 'suspended');

    // Бесплатный даёт 2 объявления и 1 ролик: занято одно — свободно одно место,
    // вернуться должно самое свежее; ролику место есть
    $result = app(AssignTariffAction::class)->execute($this->user, $this->free);

    expect($newest->fresh()->status)->toBe('approved')
        ->and($oldest->fresh()->status)->toBe('suspended')
        ->and($active->fresh()->status)->toBe('approved')
        ->and($video->fresh()->status)->toBe('approved')
        ->and($result['restored'])->toBe(['listings' => 1, 'videos' => 1]);

    app(AssignTariffAction::class)->execute($this->user, $this->premium);

    expect($oldest->fresh()->status)->toBe('approved');
});

// ─── Правка скрытого владельцем ────────────────────────────────────────────

it('does not let the owner bring a suspended listing back over the limit', function () {
    $this->user->forceFill(['tariff_id' => $this->free->id, 'tariff_ends_at' => null])->save();
    expiryListing(1);
    expiryListing(2);
    $hidden = expiryListing(3, 'suspended');
    ListingMedia::create(['listing_id' => $hidden->id, 'path' => 'listings/x/a.webp', 'type' => 'image', 'order' => 1]);

    Sanctum::actingAs($this->user->fresh());

    $this->postJson("/api/v1/listings/{$hidden->id}", ['title' => 'Новый заголовок'])
        ->assertForbidden();

    expect($hidden->fresh()->status)->toBe('suspended');
});

it('sends an edited suspended listing to moderation when there is room', function () {
    $this->user->forceFill(['tariff_id' => $this->free->id, 'tariff_ends_at' => null])->save();
    expiryListing(1);
    $hidden = expiryListing(3, 'suspended');
    ListingMedia::create(['listing_id' => $hidden->id, 'path' => 'listings/x/a.webp', 'type' => 'image', 'order' => 1]);

    Sanctum::actingAs($this->user->fresh());

    $this->postJson("/api/v1/listings/{$hidden->id}", ['title' => 'Новый заголовок'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});
