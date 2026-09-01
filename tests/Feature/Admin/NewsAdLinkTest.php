<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\News;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;

/**
 * Рекламная новость ведёт мобилку на существующий публичный эндпоинт:
 * store → GET /v1/stores/{id}, listing и product → GET /v1/listings/{id}.
 * ID вводится руками, поэтому Form Request обязан проверить, что цель
 * действительно откроется — иначе кнопка в приложении упрётся в 404.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

function makeNewsTargetStore(array $overrides = []): Store
{
    return Store::create([
        'user_id'   => User::factory()->create()->id,
        'name'      => 'Altyn Bazar',
        'status'    => 'approved',
        'is_active' => true,
        ...$overrides,
    ]);
}

function makeNewsTargetListing(array $overrides = []): Listing
{
    $region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city     = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $category = Category::create(['name_ru' => 'Продукты', 'name_tk' => 'Azyk', 'slug' => 'food-'.uniqid(), 'level' => 1]);

    return Listing::create([
        'user_id'     => User::factory()->create()->id,
        'category_id' => $category->id,
        'region_id'   => $region->id,
        'city_id'     => $city->id,
        'title'       => 'Мешок риса',
        'description' => 'Описание',
        'price'       => 100,
        'type'        => 'goods',
        'phone'       => '+99361110000',
        'status'      => 'approved',
        ...$overrides,
    ]);
}

function newsAdPayload(array $overrides = []): array
{
    return [
        'title_ru'     => 'Скидки в Altyn Bazar',
        'type'         => 'ad',
        'is_published' => true,
        ...$overrides,
    ];
}

it('saves an ad news pointing at an approved store', function () {
    $store = makeNewsTargetStore();

    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'store',
        'ad_link_id'   => $store->id,
    ]))->assertSessionHasNoErrors();

    expect(News::first())
        ->ad_link_type->toBe('store')
        ->ad_link_id->toBe($store->id);
});

it('rejects a store that is not visible in the public showcase', function () {
    $pending  = makeNewsTargetStore(['status' => 'pending']);
    $disabled = makeNewsTargetStore(['user_id' => User::factory()->create()->id, 'is_active' => false]);

    foreach ([$pending, $disabled] as $store) {
        $this->post(route('news.store'), newsAdPayload([
            'ad_link_type' => 'store',
            'ad_link_id'   => $store->id,
        ]))->assertSessionHasErrors('ad_link_id');
    }

    expect(News::count())->toBe(0);
});

it('accepts an approved listing and refuses a pending one', function () {
    $approved = makeNewsTargetListing();
    $pending  = makeNewsTargetListing(['status' => 'pending']);

    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'listing',
        'ad_link_id'   => $approved->id,
    ]))->assertSessionHasNoErrors();

    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'listing',
        'ad_link_id'   => $pending->id,
    ]))->assertSessionHasErrors('ad_link_id');

    expect(News::count())->toBe(1);
});

it('requires a store-owned listing for the product link type', function () {
    $store   = makeNewsTargetStore();
    $product = makeNewsTargetListing(['store_id' => $store->id]);
    $plain   = makeNewsTargetListing();

    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'product',
        'ad_link_id'   => $product->id,
    ]))->assertSessionHasNoErrors();

    // Обычное объявление без магазина товаром не является
    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'product',
        'ad_link_id'   => $plain->id,
    ]))->assertSessionHasErrors('ad_link_id');

    expect(News::count())->toBe(1);
});

it('refuses a missing target and the retired profile link type', function () {
    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'listing',
        'ad_link_id'   => 999999,
    ]))->assertSessionHasErrors('ad_link_id');

    // profile переехал в store: публичной карточки пользователя в API нет
    $this->post(route('news.store'), newsAdPayload([
        'ad_link_type' => 'profile',
        'ad_link_id'   => User::factory()->create()->id,
    ]))->assertSessionHasErrors('ad_link_type');

    expect(News::count())->toBe(0);
});

it('requires the link fields for an ad and clears them for a regular news', function () {
    $this->post(route('news.store'), newsAdPayload())
        ->assertSessionHasErrors(['ad_link_type', 'ad_link_id']);

    $this->post(route('news.store'), [
        'title_ru'     => 'Обычная',
        'type'         => 'regular',
        'ad_link_type' => 'store',
        'ad_link_id'   => makeNewsTargetStore()->id,
        'is_published' => true,
    ])->assertSessionHasNoErrors();

    expect(News::first())
        ->ad_link_type->toBeNull()
        ->ad_link_id->toBeNull();
});

it('validates the link when an existing news is switched to an ad', function () {
    $news  = News::create(['title_ru' => 'Обычная', 'type' => 'regular', 'is_published' => true]);
    $store = makeNewsTargetStore();

    $this->put(route('news.update', $news), newsAdPayload([
        'ad_link_type' => 'store',
        'ad_link_id'   => $store->id + 100,
    ]))->assertSessionHasErrors('ad_link_id');

    $this->put(route('news.update', $news), newsAdPayload([
        'ad_link_type' => 'store',
        'ad_link_id'   => $store->id,
    ]))->assertSessionHasNoErrors();

    expect($news->fresh())
        ->type->toBe('ad')
        ->ad_link_type->toBe('store')
        ->ad_link_id->toBe($store->id);
});
