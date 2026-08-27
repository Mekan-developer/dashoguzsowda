<?php

use App\Models\Category;
use App\Models\City;
use App\Models\ComplaintReason;
use App\Models\Listing;
use App\Models\Region;
use App\Models\RejectionReason;
use App\Models\User;
use App\Models\Video;

/**
 * Правки в админке шли через $request->only(...) без Form Request:
 * пустое обязательное поле упиралось в NOT NULL и отдавало 500 с PDOException,
 * а нечисловая цена и type вне enum доходили до драйвера БД (В-3, К-4).
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function makeAdminListing(array $overrides = []): Listing
{
    $region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city     = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'tr-'.uniqid(), 'level' => 1]);

    return Listing::create([
        'user_id'     => User::factory()->create()->id,
        'category_id' => $category->id,
        'region_id'   => $region->id,
        'city_id'     => $city->id,
        'title'       => 'Исходный заголовок',
        'description' => 'Описание',
        'price'       => 100,
        'type'        => 'goods',
        'phone'       => '+99361110000',
        'status'      => 'approved',
        ...$overrides,
    ]);
}

// ─── Объявления ─────────────────────────────────────────────────────────────

it('rejects an empty listing title with 422 instead of a database error', function () {
    $listing = makeAdminListing();

    $this->put(route('listings.update', $listing), ['title' => '', 'description' => 'x', 'price' => 10])
        ->assertSessionHasErrors('title');

    expect($listing->fresh()->title)->toBe('Исходный заголовок');
});

it('rejects a non-numeric listing price', function () {
    $listing = makeAdminListing();

    $this->put(route('listings.update', $listing), ['title' => 'Ок', 'price' => 'не-число'])
        ->assertSessionHasErrors('price');

    expect((float) $listing->fresh()->price)->toBe(100.0);
});

it('saves a valid listing edit', function () {
    $listing = makeAdminListing();

    $this->put(route('listings.update', $listing), [
        'title' => 'Новый заголовок', 'description' => 'Новое описание', 'price' => 250,
    ])->assertRedirect();

    expect($listing->fresh()->title)->toBe('Новый заголовок')
        ->and((float) $listing->fresh()->price)->toBe(250.0)
        // Правка модератором не отправляет объявление на повторную модерацию
        ->and($listing->fresh()->status)->toBe('approved');
});

// ─── Ролики ─────────────────────────────────────────────────────────────────

it('rejects an empty video title', function () {
    $video = Video::create([
        'user_id' => User::factory()->create()->id,
        'title'   => 'Исходный',
        'path'    => 'videos/x/original.mp4',
        'status'  => 'pending',
    ]);

    $this->put(route('videos.update', $video), ['title' => ''])
        ->assertSessionHasErrors('title');

    expect($video->fresh()->title)->toBe('Исходный');
});

// ─── Справочники причин ─────────────────────────────────────────────────────

it('rejects an unknown rejection reason type', function () {
    $reason = RejectionReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'listing', 'is_active' => true]);

    $this->put(route('rejection-reasons.update', $reason), [
        'name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'что-то-своё',
    ])->assertSessionHasErrors('type');

    expect($reason->fresh()->type)->toBe('listing');
});

it('rejects empty rejection reason names', function () {
    $reason = RejectionReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'listing', 'is_active' => true]);

    $this->put(route('rejection-reasons.update', $reason), ['name_ru' => '', 'name_tk' => '', 'type' => 'listing'])
        ->assertSessionHasErrors(['name_ru', 'name_tk']);

    expect($reason->fresh()->name_ru)->toBe('Спам');
});

it('rejects empty complaint reason names', function () {
    $reason = ComplaintReason::create(['name_ru' => 'Мошенничество', 'name_tk' => 'Galplyk', 'is_active' => true]);

    $this->put(route('complaint-reasons.update', $reason), ['name_ru' => '', 'name_tk' => ''])
        ->assertSessionHasErrors(['name_ru', 'name_tk']);

    expect($reason->fresh()->name_ru)->toBe('Мошенничество');
});

/**
 * Settings/Index.vue переключает активность одним полем:
 * router.put(route('rejection-reasons.update', id), { is_active: !item.is_active }).
 * Требовать в update name_ru/name_tk/type — значит сломать этот тумблер.
 */
it('toggles a rejection reason without resending the other fields', function () {
    $reason = RejectionReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'listing', 'is_active' => true]);

    $this->put(route('rejection-reasons.update', $reason), ['is_active' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect((bool) $reason->fresh()->is_active)->toBeFalse()
        ->and($reason->fresh()->name_ru)->toBe('Спам')
        ->and($reason->fresh()->type)->toBe('listing');
});

it('toggles a complaint reason without resending the other fields', function () {
    $reason = ComplaintReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'is_active' => true]);

    $this->put(route('complaint-reasons.update', $reason), ['is_active' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect((bool) $reason->fresh()->is_active)->toBeFalse()
        ->and($reason->fresh()->name_ru)->toBe('Спам');
});

it('saves a valid complaint reason edit', function () {
    $reason = ComplaintReason::create(['name_ru' => 'Мошенничество', 'name_tk' => 'Galplyk', 'is_active' => true]);

    $this->put(route('complaint-reasons.update', $reason), [
        'name_ru' => 'Обман', 'name_tk' => 'Aldaw', 'is_active' => false,
    ])->assertRedirect();

    expect($reason->fresh()->name_ru)->toBe('Обман')
        ->and((bool) $reason->fresh()->is_active)->toBeFalse();
});
