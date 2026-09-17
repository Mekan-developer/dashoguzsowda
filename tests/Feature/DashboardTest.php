<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use App\Models\Video;

beforeEach(function () {
    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'tr', 'level' => 1]);
    $this->author   = User::factory()->create();
});

function makeDashboardListing(array $overrides = []): Listing
{
    return Listing::create([
        'user_id'     => test()->author->id,
        'category_id' => test()->category->id,
        'region_id'   => test()->region->id,
        'city_id'     => test()->city->id,
        'title'       => 'Объявление',
        'type'        => 'goods',
        'phone'       => '+99361110000',
        'status'      => 'approved',
        ...$overrides,
    ]);
}

it('renders the dashboard with stats, charts and recent items', function () {
    makeDashboardListing(['title' => 'Одобренное']);
    makeDashboardListing(['title' => 'На модерации', 'status' => 'pending']);

    Video::create([
        'user_id' => $this->author->id, 'category_id' => $this->category->id,
        'title' => 'Ролик', 'path' => 'videos/a/o.mp4', 'status' => 'pending',
    ]);

    $reason = ComplaintReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'is_active' => true]);
    Complaint::create([
        'user_id' => $this->author->id, 'complaint_reason_id' => $reason->id, 'status' => 'new', 'text' => 'Жалоба',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.users', 1)              // admin в счёт role=user не идёт
            ->where('stats.listings', 2)
            ->where('stats.listings_pending', 1)
            ->where('stats.videos', 1)
            ->where('stats.videos_pending', 1)
            ->where('stats.complaints_new', 1)
            ->has('charts.users_7d')
            ->has('charts.listings_7d')
            ->has('topCategories', 1)
            ->has('recentListings', 2)
            ->has('recentComplaints', 1)
        );
});

it('is closed to users of the mobile app', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('counts listings per category for the top list', function () {
    makeDashboardListing();
    makeDashboardListing();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('topCategories.0.listings_count', 2));
});
