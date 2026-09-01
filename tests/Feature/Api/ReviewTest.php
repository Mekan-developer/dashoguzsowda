<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Region;
use App\Models\Review;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $this->city     = City::create(['region_id' => $this->region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $this->category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'transport', 'level' => 1]);

    $this->user  = User::factory()->create();
    $this->owner = User::factory()->create();

    $this->listing = Listing::create([
        'user_id'     => $this->owner->id,
        'category_id' => $this->category->id,
        'region_id'   => $this->region->id,
        'city_id'     => $this->city->id,
        'title'       => 'Продам велосипед',
        'type'        => 'goods',
        'phone'       => $this->owner->phone,
        'status'      => 'approved',
    ]);
});

it('requires auth to leave a review', function () {
    $this->postJson('/api/v1/reviews', ['text' => 'Отлично', 'listing_id' => $this->listing->id])
        ->assertUnauthorized();
});

it('creates a listing review with pending status', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/reviews', [
        'text'       => 'Отличный продавец, всё честно',
        'rating'     => 5,
        'listing_id' => $this->listing->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('message', __('messages.review_submitted'));

    $review = Review::sole();
    expect($review->user_id)->toBe($this->user->id)
        ->and($review->listing_id)->toBe($this->listing->id)
        ->and($review->target_user_id)->toBeNull()
        ->and($review->status)->toBe('pending');
});

it('creates a user review with pending status', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/reviews', [
        'text'           => 'Надёжный человек',
        'target_user_id' => $this->owner->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.target_user_id', $this->owner->id);

    expect(Review::sole()->listing_id)->toBeNull();
});

it('requires exactly one review target — listing or user', function () {
    Sanctum::actingAs($this->user);

    // Ни одного объекта
    $this->postJson('/api/v1/reviews', ['text' => 'Текст'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['listing_id', 'target_user_id']);

    // Оба объекта сразу
    $this->postJson('/api/v1/reviews', [
        'text'           => 'Текст',
        'listing_id'     => $this->listing->id,
        'target_user_id' => $this->owner->id,
    ])->assertUnprocessable();
});

it('validates rating range', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/reviews', [
        'text'       => 'Текст',
        'rating'     => 6,
        'listing_id' => $this->listing->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('rating');
});

it('forbids blocked user from leaving a review', function () {
    Sanctum::actingAs(User::factory()->blocked()->create());

    $this->postJson('/api/v1/reviews', [
        'text'       => 'Текст',
        'listing_id' => $this->listing->id,
    ])->assertForbidden();
});

it('lets the author edit own review and sends it back to moderation', function () {
    $review = Review::create([
        'user_id'             => $this->user->id,
        'listing_id'          => $this->listing->id,
        'text'                => 'Первый вариант',
        'rating'              => 2,
        'status'              => 'rejected',
        'rejection_reason_id' => null,
    ]);

    $this->putJson("/api/v1/reviews/{$review->id}", ['text' => 'Исправил'])->assertUnauthorized();

    Sanctum::actingAs($this->user);

    $this->putJson("/api/v1/reviews/{$review->id}", ['text' => 'Продавец всё исправил', 'rating' => 5])
        ->assertOk()
        ->assertJsonPath('data.text', 'Продавец всё исправил')
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('message', __('messages.review_updated'));

    expect($review->fresh()->status)->toBe('pending');
});

it('keeps the review target and rating when editing', function () {
    $review = Review::create([
        'user_id'    => $this->user->id,
        'listing_id' => $this->listing->id,
        'text'       => 'Текст',
        'rating'     => 4,
        'status'     => 'approved',
    ]);

    Sanctum::actingAs($this->user);

    // Объект отзыва не меняется, даже если его прислали
    $this->putJson("/api/v1/reviews/{$review->id}", [
        'text'           => 'Новый текст',
        'target_user_id' => $this->owner->id,
    ])->assertOk()->assertJsonPath('data.rating', 4);

    expect($review->fresh()->listing_id)->toBe($this->listing->id)
        ->and($review->fresh()->target_user_id)->toBeNull();

    // Явный null снимает оценку
    $this->putJson("/api/v1/reviews/{$review->id}", ['text' => 'Без оценки', 'rating' => null])
        ->assertOk()
        ->assertJsonPath('data.rating', null);
});

it('validates review edits', function () {
    $review = Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Текст', 'status' => 'approved']);

    Sanctum::actingAs($this->user);

    $this->putJson("/api/v1/reviews/{$review->id}", ['text' => '', 'rating' => 6])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text', 'rating']);
});

it('forbids editing and deleting someone else review', function () {
    $review = Review::create(['user_id' => $this->owner->id, 'listing_id' => $this->listing->id, 'text' => 'Чужой отзыв', 'status' => 'approved']);

    Sanctum::actingAs($this->user);

    $this->putJson("/api/v1/reviews/{$review->id}", ['text' => 'Подмена'])->assertForbidden();
    $this->deleteJson("/api/v1/reviews/{$review->id}")->assertForbidden();

    expect($review->fresh()->text)->toBe('Чужой отзыв');
});

it('lets the author delete own review', function () {
    $review = Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Передумал', 'rating' => 5, 'status' => 'approved']);

    $this->deleteJson("/api/v1/reviews/{$review->id}")->assertUnauthorized();

    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/v1/reviews/{$review->id}")
        ->assertOk()
        ->assertJsonPath('message', __('messages.review_deleted'));

    expect(Review::find($review->id))->toBeNull();

    // Из публичной ленты и рейтинга отзыв тоже исчез
    $this->getJson("/api/v1/listings/{$this->listing->id}/reviews")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.rating.average', null);
});

it('forbids blocked user from editing but allows deleting own review', function () {
    $blocked = User::factory()->blocked()->create();
    $review  = Review::create(['user_id' => $blocked->id, 'listing_id' => $this->listing->id, 'text' => 'Текст', 'status' => 'approved']);

    Sanctum::actingAs($blocked);

    $this->putJson("/api/v1/reviews/{$review->id}", ['text' => 'Правка'])->assertForbidden();
    $this->deleteJson("/api/v1/reviews/{$review->id}")->assertOk();
});

it('returns only approved reviews of a listing with rating summary', function () {
    $second = User::factory()->create();

    Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Отличный товар', 'rating' => 5, 'status' => 'approved']);
    Review::create(['user_id' => $second->id,     'listing_id' => $this->listing->id, 'text' => 'Нормально',      'rating' => 4, 'status' => 'approved']);
    // Без оценки: попадает в список и count, но не в среднее
    Review::create(['user_id' => $second->id,     'listing_id' => $this->listing->id, 'text' => 'Без оценки',     'status' => 'approved']);
    // Эти в публичную выдачу попасть не должны
    Review::create(['user_id' => $second->id,     'listing_id' => $this->listing->id, 'text' => 'На модерации',   'rating' => 1, 'status' => 'pending']);
    Review::create(['user_id' => $second->id,     'listing_id' => $this->listing->id, 'text' => 'Отклонён',       'rating' => 1, 'status' => 'rejected']);

    $response = $this->getJson("/api/v1/listings/{$this->listing->id}/reviews")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.rating.count', 3)
        ->assertJsonPath('meta.rating.rated_count', 2)
        ->assertJsonPath('meta.rating.average', 4.5)
        ->assertJsonPath('meta.rating.breakdown.5', 1)
        ->assertJsonPath('meta.rating.breakdown.1', 0);

    // Автор отзыва отдаётся вместе с текстом — второй запрос мобилке не нужен
    expect($response->json('data.0.author.id'))->not->toBeNull()
        ->and(collect($response->json('data'))->pluck('status')->unique()->all())->toBe(['approved']);
});

it('sorts listing reviews by rating and paginates', function () {
    foreach ([3, 5, 4] as $i => $rating) {
        Review::create([
            'user_id'    => $this->user->id,
            'listing_id' => $this->listing->id,
            'text'       => "Отзыв {$i}",
            'rating'     => $rating,
            'status'     => 'approved',
        ]);
    }

    $this->getJson("/api/v1/listings/{$this->listing->id}/reviews?sort=rating_desc")
        ->assertOk()
        ->assertJsonPath('data.0.rating', 5)
        ->assertJsonPath('data.2.rating', 3);

    $this->getJson("/api/v1/listings/{$this->listing->id}/reviews?limit=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 2);
});

it('hides reviews of a listing that has not passed moderation', function () {
    $this->listing->update(['status' => 'pending']);

    $this->getJson("/api/v1/listings/{$this->listing->id}/reviews")->assertNotFound();
});

it('returns approved reviews about a seller', function () {
    Review::create(['user_id' => $this->user->id, 'target_user_id' => $this->owner->id, 'text' => 'Надёжный', 'rating' => 5, 'status' => 'approved']);
    Review::create(['user_id' => $this->user->id, 'target_user_id' => $this->owner->id, 'text' => 'Ждём',     'rating' => 2, 'status' => 'pending']);

    $this->getJson("/api/v1/users/{$this->owner->id}/reviews")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.text', 'Надёжный')
        ->assertJsonPath('meta.rating.average', 5);
});

it('shows own reviews with moderation status', function () {
    Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Мой отзыв', 'status' => 'pending']);
    Review::create(['user_id' => $this->owner->id, 'listing_id' => $this->listing->id, 'text' => 'Чужой отзыв', 'status' => 'approved']);

    $this->getJson('/api/v1/reviews/my')->assertUnauthorized();

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/reviews/my')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.listing.id', $this->listing->id);

    $this->getJson('/api/v1/reviews/my?status=approved')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exposes listing rating in feed and card', function () {
    Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Пять', 'rating' => 5, 'status' => 'approved']);
    Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Три',  'rating' => 3, 'status' => 'approved']);
    // Оценка на модерации в среднее не попадает
    Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Один', 'rating' => 1, 'status' => 'pending']);
    // Отзыв о самом продавце — отдельный рейтинг в карточке
    Review::create(['user_id' => $this->user->id, 'target_user_id' => $this->owner->id, 'text' => 'Продавец', 'rating' => 4, 'status' => 'approved']);

    $this->getJson('/api/v1/listings')
        ->assertOk()
        ->assertJsonPath('data.0.rating.average', 4)
        ->assertJsonPath('data.0.rating.count', 2);

    $this->getJson("/api/v1/listings/{$this->listing->id}")
        ->assertOk()
        ->assertJsonPath('data.rating.average', 4)
        ->assertJsonPath('data.rating.count', 2)
        ->assertJsonPath('data.user.rating.average', 4)
        ->assertJsonPath('data.user.rating.count', 1);
});

it('renders admin reviews page with paginator, counts and search filter', function () {
    $admin = User::factory()->admin()->create();

    Review::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id, 'text' => 'Отличный велосипед', 'status' => 'pending']);
    Review::create(['user_id' => $this->user->id, 'target_user_id' => $this->owner->id, 'text' => 'Надёжный продавец', 'status' => 'approved']);

    $this->actingAs($admin)->get(route('reviews.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reviews/Index')
            ->has('reviews.data', 2)
            ->has('reviews.links')
            ->where('counts.pending', 1)
            ->where('counts.approved', 1)
            ->has('rejectionReasons'));

    // Серверный поиск: по тексту и по имени объекта-пользователя
    $this->actingAs($admin)->get(route('reviews.index', ['search' => 'велосипед']))
        ->assertInertia(fn ($page) => $page->has('reviews.data', 1));
});
