<?php

use App\Jobs\ProcessListingImagesJob;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Region;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    // Дефолтный (бесплатный) тариф — activeTariff() падает на него при отсутствии личного
    $this->tariff = Tariff::create([
        'name_ru' => 'Free', 'name_tk' => 'Free', 'listings_limit' => 5, 'videos_limit' => 1,
        'boost_limit' => 1, 'duration_days' => 30, 'is_free' => true, 'is_active' => true,
    ]);

    $this->region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city   = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);

    $root = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport', 'level' => 1]);
    $this->leaf = Category::create([
        'parent_id' => $root->id, 'name_ru' => 'Велосипеды', 'name_tk' => 'Tigirler',
        'slug' => 'bikes', 'level' => 2,
    ]);
    $this->rootCategory = $root;

    $this->user = User::factory()->create();
});

function listingPayload(array $overrides = []): array
{
    return array_merge([
        'title'       => 'Продам велосипед',
        'description' => 'Отличное состояние, почти не катались',
        'type'        => 'goods',
        'category_id' => test()->leaf->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'price'       => 1500,
        'photos'      => [UploadedFile::fake()->image('bike.jpg', 900, 700)],
    ], $overrides);
}

function makeListing(array $overrides = []): Listing
{
    return Listing::create(array_merge([
        'user_id'     => test()->user->id,
        'category_id' => test()->leaf->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'title'       => 'Объявление',
        'description' => 'Описание',
        'type'        => 'goods',
        'phone'       => test()->user->phone,
        'status'      => 'approved',
    ], $overrides));
}

// ─── Создание ───────────────────────────────────────────────────────────────

it('requires auth to create a listing', function () {
    $this->postJson('/api/v1/listings', [])->assertUnauthorized();
});

it('creates a listing with photos, pending status and queued image processing', function () {
    Sanctum::actingAs($this->user);

    $response = $this->post('/api/v1/listings', listingPayload([
        'tags'     => ['велосипед', 'спорт'],
        'location' => ['lat' => 37.95, 'lng' => 58.38],
    ]), ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.title', 'Продам велосипед')
        ->assertJsonPath('data.tags.0', 'велосипед')
        ->assertJsonCount(1, 'data.photos')
        // Queue::fake() — job не выполнялся, конвертация ещё не готова
        ->assertJsonPath('data.photos.0.processing', true);

    $listing = Listing::first();
    expect($listing->phone)->toBe($this->user->phone) // не передан — подставлен телефон автора
        ->and($listing->media)->toHaveCount(1);

    Queue::assertPushed(ProcessListingImagesJob::class, 1);
    Storage::disk('public')->assertExists($listing->media->first()->path);
});

it('validates required fields and photo limits', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/listings', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'description', 'type', 'category_id', 'region_id', 'city_id', 'photos']);

    $this->post('/api/v1/listings', listingPayload([
        'photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 9)),
    ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photos']);
});

it('rejects a non-leaf category', function () {
    Sanctum::actingAs($this->user);

    $this->post('/api/v1/listings', listingPayload(['category_id' => $this->rootCategory->id]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

it('enforces the tariff listings limit with 403', function () {
    $this->tariff->update(['listings_limit' => 1]);
    Sanctum::actingAs($this->user);

    $this->post('/api/v1/listings', listingPayload(), ['Accept' => 'application/json'])->assertCreated();
    $this->post('/api/v1/listings', listingPayload(), ['Accept' => 'application/json'])->assertForbidden();
});

it('forbids a blocked user from creating a listing even with a valid token', function () {
    $blocked = User::factory()->blocked()->create();
    Sanctum::actingAs($blocked);

    $this->postJson('/api/v1/listings', listingPayload())->assertForbidden();

    expect(Listing::count())->toBe(0);
});

// ─── Публичная выдача ───────────────────────────────────────────────────────

it('lists only approved listings, boosted first', function () {
    actingAsClient();

    $pending = makeListing(['status' => 'pending']);
    $old     = makeListing(['title' => 'Старое поднятое', 'is_boosted' => true, 'created_at' => now()->subDay()]);
    $fresh   = makeListing(['title' => 'Новое обычное']);

    $this->getJson('/api/v1/listings')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $old->id)
        ->assertJsonPath('data.1.id', $fresh->id);
});

it('searches by title and description', function () {
    actingAsClient();

    makeListing(['title' => 'Продам велосипед']);
    makeListing(['title' => 'Куплю гараж', 'description' => 'Рядом с парком']);

    $this->getJson('/api/v1/listings?search=гараж')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Куплю гараж');
});

it('filters by parent category including its subtree', function () {
    actingAsClient();

    makeListing(); // категория-лист внутри rootCategory

    $otherRoot = Category::create(['name_ru' => 'Другое', 'name_tk' => 'Başga', 'slug' => 'other', 'level' => 1]);
    makeListing(['category_id' => $otherRoot->id, 'title' => 'Не должно попасть']);

    $this->getJson("/api/v1/listings?category_id={$this->rootCategory->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by several categories at once, each with its subtree', function () {
    actingAsClient();

    makeListing(['title' => 'Велосипед']); // лист внутри rootCategory

    $phones = Category::create(['name_ru' => 'Телефоны', 'name_tk' => 'Telefonlar', 'slug' => 'phones', 'level' => 1]);
    makeListing(['category_id' => $phones->id, 'title' => 'Телефон']);

    $furniture = Category::create(['name_ru' => 'Мебель', 'name_tk' => 'Mebel', 'slug' => 'furniture', 'level' => 1]);
    makeListing(['category_id' => $furniture->id, 'title' => 'Не должно попасть']);

    $response = $this->getJson("/api/v1/listings?category_ids[]={$this->rootCategory->id}&category_ids[]={$phones->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('title')->sort()->values()->all())
        ->toBe(['Велосипед', 'Телефон']);
});

it('combines category_ids with the price filter', function () {
    actingAsClient();

    makeListing(['title' => 'Дешёвый', 'price' => 100]);
    makeListing(['title' => 'Дорогой', 'price' => 900]);

    $this->getJson("/api/v1/listings?category_ids[]={$this->rootCategory->id}&price_max=500")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Дешёвый');
});

it('narrows the section to the subcategories picked inside it', function () {
    actingAsClient();

    makeListing(['title' => 'Велосипед']); // лист «Велосипеды» внутри rootCategory

    $cars = Category::create([
        'parent_id' => $this->rootCategory->id, 'name_ru' => 'Авто', 'name_tk' => 'Awto',
        'slug' => 'cars', 'level' => 2,
    ]);
    makeListing(['category_id' => $cars->id, 'title' => 'Не должно попасть']);

    $this->getJson("/api/v1/listings?category_id={$this->rootCategory->id}&category_ids[]={$this->leaf->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Велосипед');
});

it('rejects a subcategory from another section', function () {
    actingAsClient();

    $otherRoot = Category::create(['name_ru' => 'Оптом', 'name_tk' => 'Optom', 'slug' => 'optom', 'level' => 1]);
    $foreign = Category::create([
        'parent_id' => $otherRoot->id, 'name_ru' => 'Одежда', 'name_tk' => 'Egin-eşik',
        'slug' => 'clothes', 'level' => 2,
    ]);

    $this->getJson("/api/v1/listings?category_id={$this->rootCategory->id}&category_ids[]={$this->leaf->id}&category_ids[]={$foreign->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_ids.1'])
        ->assertJsonMissingValidationErrors(['category_ids.0']);
});

it('rejects unknown ids in category_ids', function () {
    actingAsClient();

    $this->getJson('/api/v1/listings?category_ids[]=999999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_ids.0']);
});

it('returns the full 3-level category path', function () {
    actingAsClient();

    $leaf3 = Category::create([
        'parent_id' => $this->leaf->id, 'name_ru' => 'test', 'name_tk' => 'test',
        'slug' => 'test-leaf', 'level' => 3,
    ]);
    $listing = makeListing(['category_id' => $leaf3->id]);

    $this->getJson("/api/v1/listings/{$listing->id}")
        ->assertOk()
        ->assertJsonPath('data.category.path.0.name_ru', $this->rootCategory->name_ru)
        ->assertJsonPath('data.category.path.1.name_ru', $this->leaf->name_ru)
        ->assertJsonPath('data.category.path.2.name_ru', 'test');
});

it('filters by type and price range', function () {
    actingAsClient();

    makeListing(['type' => 'services', 'price' => 100]);
    makeListing(['type' => 'goods', 'price' => 900]);

    $this->getJson('/api/v1/listings?type=services')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/listings?price_min=500')->assertJsonCount(1, 'data');
});

it('sorts by distance when geolocation is enabled', function () {
    actingAsClient();

    $far  = makeListing(['title' => 'Далеко', 'location' => ['lat' => 40.0, 'lng' => 60.0]]);
    $near = makeListing(['title' => 'Рядом', 'location' => ['lat' => 37.96, 'lng' => 58.39]]);
    makeListing(['title' => 'Без гео', 'location' => null]);

    $this->getJson('/api/v1/listings?sort=nearest&lat=37.95&lng=58.38')
        ->assertOk()
        ->assertJsonCount(2, 'data') // объявления без геолокации не попадают в «рядом»
        ->assertJsonPath('data.0.id', $near->id)
        ->assertJsonPath('data.1.id', $far->id);
});

// ─── Карточка и просмотры ──────────────────────────────────────────────────

it('increments views for visitors but not for the owner', function () {
    actingAsClient();

    $listing = makeListing();

    $this->getJson("/api/v1/listings/{$listing->id}")->assertOk();
    expect($listing->fresh()->views)->toBe(1);

    Sanctum::actingAs($this->user);
    $this->getJson("/api/v1/listings/{$listing->id}")->assertOk();
    expect($listing->fresh()->views)->toBe(1);
});

it('hides foreign pending listings but shows own with rejection reason', function () {
    actingAsClient();

    $pending = makeListing(['status' => 'pending']);

    $this->getJson("/api/v1/listings/{$pending->id}")->assertNotFound();

    Sanctum::actingAs($this->user);
    $this->getJson("/api/v1/listings/{$pending->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

// ─── Мои объявления ────────────────────────────────────────────────────────

it('returns own listings with status filter', function () {
    makeListing(['status' => 'pending']);
    makeListing(['status' => 'approved']);
    makeListing(['status' => 'rejected']);
    makeListing(['status' => 'approved', 'user_id' => User::factory()->create()->id]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/listings/my')->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/listings/my?status=rejected')->assertOk()->assertJsonCount(1, 'data');
});

// ─── Редактирование ────────────────────────────────────────────────────────

it('lets the owner edit: listing goes back to moderation', function () {
    $listing = makeListing(['status' => 'rejected']);
    ListingMedia::create(['listing_id' => $listing->id, 'path' => 'listings/x/a.webp', 'type' => 'image', 'order' => 1]);

    Sanctum::actingAs($this->user);

    $this->post("/api/v1/listings/{$listing->id}", ['title' => 'Исправленный заголовок'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.title', 'Исправленный заголовок');

    expect($listing->fresh()->rejection_reason_id)->toBeNull();
});

it('swaps photos on update but never allows zero photos', function () {
    $listing = makeListing();
    $media = ListingMedia::create(['listing_id' => $listing->id, 'path' => 'listings/x/a.webp', 'type' => 'image', 'order' => 1]);

    Sanctum::actingAs($this->user);

    // Удалить единственное фото без замены нельзя
    $this->post("/api/v1/listings/{$listing->id}", ['remove_media_ids' => [$media->id]], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photos']);

    // С заменой — можно
    $this->post("/api/v1/listings/{$listing->id}", [
        'remove_media_ids' => [$media->id],
        'photos'           => [UploadedFile::fake()->image('new.jpg')],
    ], ['Accept' => 'application/json'])->assertOk();

    expect(ListingMedia::find($media->id))->toBeNull()
        ->and($listing->fresh()->media)->toHaveCount(1);
});

it('forbids a blocked user from editing their own listing', function () {
    $listing = makeListing(['status' => 'rejected', 'user_id' => $this->user->id]);
    app(UserRepositoryInterface::class)->block($this->user, null);
    Sanctum::actingAs($this->user->refresh());

    $this->postJson("/api/v1/listings/{$listing->id}", ['title' => 'Попытка правки'])->assertForbidden();
});

it('forbids editing and deleting foreign listings', function () {
    $listing = makeListing();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/listings/{$listing->id}", ['title' => 'Чужое'])->assertForbidden();
    $this->deleteJson("/api/v1/listings/{$listing->id}")->assertForbidden();
});

// ─── Удаление ──────────────────────────────────────────────────────────────

it('deletes own listing with its media files', function () {
    Sanctum::actingAs($this->user);
    $this->post('/api/v1/listings', listingPayload(), ['Accept' => 'application/json'])->assertCreated();

    $listing = Listing::first();
    $mediaPath = $listing->media->first()->path;

    $this->deleteJson("/api/v1/listings/{$listing->id}")->assertOk();

    expect(Listing::find($listing->id))->toBeNull();
    Storage::disk('public')->assertMissing($mediaPath);
});

// ─── Поднятие ──────────────────────────────────────────────────────────────

it('boosts an approved listing and enforces the interval', function () {
    $listing = makeListing();
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/listings/{$listing->id}/boost")
        ->assertOk()
        ->assertJsonPath('data.is_boosted', true);

    // Повторное поднятие до истечения интервала — отказ
    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertUnprocessable();
});

it('cannot boost a pending listing', function () {
    $listing = makeListing(['status' => 'pending']);
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertForbidden();
});

it('enforces the tariff boost limit across listings with a 403', function () {
    // Тарифная фикстура: boost_limit = 1 — считает одновременно поднятые объявления
    $first  = makeListing(['title' => 'Первое']);
    $second = makeListing(['title' => 'Второе']);
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/listings/{$first->id}/boost")->assertOk();

    // Второе, ещё не поднятое объявление — интервал ему не мешает, но квота на
    // одновременные поднятия уже занята первым
    $this->postJson("/api/v1/listings/{$second->id}/boost")->assertForbidden();
});

it('allows re-boosting an already boosted listing after the interval despite the limit', function () {
    // boost_limit = 1: своё единственное поднятое объявление всё равно можно
    // поднимать повторно — оно уже занимает слот, а не претендует на новый.
    $listing = makeListing();
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertOk();

    // Интервал по умолчанию 24ч — перематываем время за него
    $this->travel(25)->hours();
    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertOk();
});

it('reads the boost interval from admin settings', function () {
    App\Models\Setting::set('boost_interval_hours', '1');
    $listing = makeListing();
    Sanctum::actingAs($this->user);

    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertOk();

    // Через 30 минут интервал (1ч) ещё не прошёл
    $this->travel(30)->minutes();
    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertUnprocessable();

    // Через 2 часа — уже можно
    $this->travel(90)->minutes();
    $this->postJson("/api/v1/listings/{$listing->id}/boost")->assertOk();
});
