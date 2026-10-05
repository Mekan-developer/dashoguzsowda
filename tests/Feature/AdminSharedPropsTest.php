<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Region;
use App\Models\Review;
use App\Models\User;
use App\Models\Video;

/**
 * Общие props админки (В-7): счётчики бейджей и текущий пользователь.
 * Шесть COUNT-ов заменены одним запросом — тест фиксирует, что числа сошлись.
 */
it('shares sidebar counters computed in a single query', function () {
    $admin = User::factory()->admin()->create();

    $region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city     = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'tr', 'level' => 1]);
    $author   = User::factory()->create();

    $listing = Listing::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Ожидает', 'type' => 'goods', 'phone' => '+99361110000', 'status' => 'pending',
    ]);
    Listing::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'region_id' => $region->id, 'city_id' => $city->id,
        'title' => 'Одобрено', 'type' => 'goods', 'phone' => '+99361110000', 'status' => 'approved',
    ]);

    Video::create([
        'user_id' => $author->id, 'category_id' => $category->id,
        'title' => 'Ролик', 'path' => 'videos/a/o.mp4', 'status' => 'pending',
    ]);

    $reason = ComplaintReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'is_active' => true]);
    Complaint::create([
        'user_id' => $author->id, 'listing_id' => $listing->id,
        'complaint_reason_id' => $reason->id, 'status' => 'new',
    ]);

    Review::create(['user_id' => $author->id, 'listing_id' => $listing->id, 'text' => 'Отзыв', 'status' => 'pending']);

    // Два непрочитанных от одного пользователя — диалог считается один раз
    Message::create(['user_id' => $author->id, 'sender' => 'user', 'text' => 'а', 'is_read' => false]);
    Message::create(['user_id' => $author->id, 'sender' => 'user', 'text' => 'б', 'is_read' => false]);
    Message::create(['user_id' => $author->id, 'sender' => 'admin', 'text' => 'в', 'is_read' => false]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('navCounts.pendingListings', 1)
            ->where('navCounts.pendingVideos', 1)
            ->where('navCounts.newComplaints', 1)
            ->where('navCounts.pendingReviews', 1)
            ->where('navCounts.unreadChats', 1)
            // admin в счёт новых пользователей не попадает — только role=user
            ->where('navCounts.newUsers', 1)
        );
});

it('shares only the fields the frontend needs about the current user', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Админ',
        'note' => 'внутренняя заметка',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.name', 'Админ')
            ->where('auth.user.role', 'admin')
            ->has('auth.user.locale')
            ->has('auth.user.id')
            ->missing('auth.user.note')
            ->missing('auth.user.phone')
            ->missing('auth.user.email')
            ->missing('auth.user.blocked_reason')
        );
});
