<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Listing;
use App\Models\Region;
use App\Models\Review;
use App\Models\Store;
use App\Models\Tariff;
use App\Models\TariffRequest;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Str;

/**
 * Разделы модерации получают тот же водораздел «просмотрено», что и Users
 * (см. AdminSectionSeenBadgeTest), но не вместо счётчика «в очереди», а
 * рядом с ним: счётчики pending-статусов/newComplaints обязаны оставаться
 * как есть — это то, что реально ждёт обработки, а не «новое с последнего
 * визита». Гасится только hasNew* — вспомогательная точка, что что-то
 * появилось.
 */
function seedModerationRefs(): array
{
    $region = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'mod-seen', 'level' => 1]);
    $author = User::factory()->create();

    return compact('region', 'city', 'category', 'author');
}

it('shows a "new" dot on Listings until opened, without touching the pending queue count', function () {
    $admin = User::factory()->admin()->create();
    ['region' => $region, 'city' => $city, 'category' => $category, 'author' => $author] = seedModerationRefs();

    $makeListing = fn (string $title) => Listing::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => $title, 'type' => 'goods', 'phone' => '+99361110000', 'status' => 'pending',
    ]);

    $makeListing('Первое');

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingListings', 1)
        ->where('counts.hasNewListings', true));

    $this->actingAs($admin)->get(route('listings.index'));

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingListings', 1) // всё ещё в очереди — открытие раздела не обработка
        ->where('counts.hasNewListings', false));

    $makeListing('Второе');

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingListings', 2)
        ->where('counts.hasNewListings', true));
});

it('shows a "new" dot on Videos until opened, without touching the pending queue count', function () {
    $admin = User::factory()->admin()->create();
    $author = User::factory()->create();
    $category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'mod-vid', 'level' => 1]);

    $makeVideo = fn (string $title) => Video::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'title' => $title, 'path' => 'videos/a/'.Str::random(8).'.mp4', 'status' => 'pending',
    ]);

    $makeVideo('Ролик 1');

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingVideos', 1)
        ->where('counts.hasNewVideos', true));

    $this->actingAs($admin)->get(route('videos.index'));

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingVideos', 1)
        ->where('counts.hasNewVideos', false));

    $makeVideo('Ролик 2');

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingVideos', 2)
        ->where('counts.hasNewVideos', true));
});

it('shows a "new" dot on Reviews until opened, without touching the pending queue count', function () {
    $admin = User::factory()->admin()->create();
    ['region' => $region, 'city' => $city, 'category' => $category, 'author' => $author] = seedModerationRefs();

    $listing = Listing::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Товар', 'type' => 'goods', 'phone' => '+99361110000', 'status' => 'approved',
    ]);

    $makeReview = fn (string $text) => Review::create([
        'user_id' => $author->id, 'listing_id' => $listing->id, 'text' => $text, 'status' => 'pending',
    ]);

    $makeReview('Отзыв 1');

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingReviews', 1)
        ->where('counts.hasNewReviews', true));

    $this->actingAs($admin)->get(route('reviews.index'));

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingReviews', 1)
        ->where('counts.hasNewReviews', false));

    $makeReview('Отзыв 2');

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingReviews', 2)
        ->where('counts.hasNewReviews', true));
});

it('shows a "new" dot on Complaints until opened, without touching the pending queue count', function () {
    $admin = User::factory()->admin()->create();
    ['region' => $region, 'city' => $city, 'category' => $category, 'author' => $author] = seedModerationRefs();

    $listing = Listing::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Товар', 'type' => 'goods', 'phone' => '+99361110000', 'status' => 'approved',
    ]);
    $reason = ComplaintReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'is_active' => true]);

    $makeComplaint = fn () => Complaint::create([
        'user_id' => $author->id, 'listing_id' => $listing->id,
        'complaint_reason_id' => $reason->id, 'status' => 'new',
    ]);

    $makeComplaint();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.newComplaints', 1)
        ->where('counts.hasNewComplaints', true));

    $this->actingAs($admin)->get(route('complaints.index'));

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.newComplaints', 1)
        ->where('counts.hasNewComplaints', false));

    $makeComplaint();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.newComplaints', 2)
        ->where('counts.hasNewComplaints', true));
});

it('shows a "new" dot on Stores until opened, without touching the pending queue count', function () {
    $admin = User::factory()->admin()->create();

    $makeStore = fn () => Store::create([
        'user_id' => User::factory()->create()->id, 'name' => 'Магазин', 'status' => 'pending',
    ]);

    $makeStore();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingStores', 1)
        ->where('counts.hasNewStores', true));

    $this->actingAs($admin)->get(route('stores.index'));

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingStores', 1)
        ->where('counts.hasNewStores', false));

    $makeStore();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingStores', 2)
        ->where('counts.hasNewStores', true));
});

it('shows a "new" dot on Tariff requests until opened, without touching the pending queue count', function () {
    $admin = User::factory()->admin()->create();
    $tariff = Tariff::create([
        'name_ru' => 'Золотой', 'name_tk' => 'Altyn', 'price' => 100,
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 1,
        'duration_days' => 30, 'is_active' => true, 'is_free' => false,
    ]);

    $makeRequest = fn () => TariffRequest::create([
        'user_id' => User::factory()->create()->id, 'tariff_id' => $tariff->id,
        'amount' => $tariff->price, 'status' => 'pending',
    ]);

    $makeRequest();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingTariffRequests', 1)
        ->where('counts.hasNewTariffRequests', true));

    $this->actingAs($admin)->get(route('tariff-requests.index'));

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingTariffRequests', 1)
        ->where('counts.hasNewTariffRequests', false));

    $makeRequest();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('counts.pendingTariffRequests', 2)
        ->where('counts.hasNewTariffRequests', true));
});
