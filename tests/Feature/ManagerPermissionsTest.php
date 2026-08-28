<?php

use App\Models\Category;
use App\Models\City;
use App\Models\ComplaintReason;
use App\Models\District;
use App\Models\Region;
use App\Models\RejectionReason;
use App\Models\Tariff;
use App\Models\User;

/**
 * Раскладка прав менеджера по CLAUDE.md → «Роли».
 *
 * Доступ описан в routes/web.php и только там — контроллеры его не дублируют,
 * поэтому этот файл фиксирует границу целиком.
 */
beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->actingAs($this->manager);
});

it('lets a manager into moderation, chat and statistics', function () {
    foreach (['dashboard', 'listings.index', 'videos.index', 'chat.index', 'complaints.index', 'reviews.index', 'statistics.index'] as $name) {
        $this->get(route($name))->assertOk();
    }
});

it('lets a manager view users but not manage them', function () {
    $user = User::factory()->create();

    $this->get(route('users.index'))->assertOk();
    $this->get(route('users.show', $user))->assertOk();

    $this->post(route('users.store'), [])->assertForbidden();
    $this->put(route('users.update', $user), [])->assertForbidden();
    $this->patch(route('users.block', $user), [])->assertForbidden();
    $this->patch(route('users.unblock', $user))->assertForbidden();
    $this->post(route('users.tariff', $user), [])->assertForbidden();
    $this->delete(route('users.destroy', $user))->assertForbidden();
    $this->get(route('users.check-phone', ['phone' => '+99361234567']))->assertForbidden();
});

it('closes the catalog structure to a manager', function () {
    $category = Category::create(['name_ru' => 'Транспорт', 'name_tk' => 'Ulag', 'slug' => 'tr', 'level' => 1]);
    $region   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $city     = City::create(['region_id' => $region->id, 'name_ru' => 'Анау', 'name_tk' => 'Änew']);
    $district = District::create(['city_id' => $city->id, 'name_ru' => 'Центр', 'name_tk' => 'Merkez']);

    $this->get(route('categories.index'))->assertForbidden();
    $this->post(route('categories.store'), [])->assertForbidden();
    $this->delete(route('categories.destroy', $category))->assertForbidden();

    $this->get(route('regions.index'))->assertForbidden();
    $this->post(route('regions.store'), [])->assertForbidden();
    $this->patch(route('regions.toggle', $region))->assertForbidden();
    $this->post(route('cities.store'), [])->assertForbidden();
    $this->post(route('districts.store', $city), [])->assertForbidden();
    $this->delete(route('districts.destroy', $district))->assertForbidden();
});

it('closes push, settings and reason directories to a manager', function () {
    $rejection = RejectionReason::create(['name_ru' => 'Спам', 'name_tk' => 'Spam', 'type' => 'listing', 'is_active' => true]);
    $complaint = ComplaintReason::create(['name_ru' => 'Обман', 'name_tk' => 'Aldaw', 'is_active' => true]);

    $this->get(route('push.index'))->assertForbidden();
    $this->post(route('push.send'), [])->assertForbidden();
    $this->get(route('settings.index'))->assertForbidden();
    $this->patch(route('settings.boost'), [])->assertForbidden();

    // У справочников причин index-роута нет — списками управляет страница
    // настроек, так что граница проверяется на пишущих роутах.
    $this->post(route('rejection-reasons.store'), [])->assertForbidden();
    $this->put(route('rejection-reasons.update', $rejection), [])->assertForbidden();
    $this->delete(route('rejection-reasons.destroy', $rejection))->assertForbidden();
    $this->post(route('complaint-reasons.store'), [])->assertForbidden();
    $this->put(route('complaint-reasons.update', $complaint), [])->assertForbidden();
    $this->delete(route('complaint-reasons.destroy', $complaint))->assertForbidden();
});

it('does not let a manager delete content', function () {
    $tariff = Tariff::create([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'listings_limit' => 5, 'videos_limit' => 2,
        'boost_limit' => 1, 'duration_days' => 30, 'is_free' => true, 'is_active' => true,
    ]);

    $this->get(route('tariffs.index'))->assertForbidden();
    $this->delete(route('tariffs.destroy', $tariff))->assertForbidden();
});

it('keeps news and banners behind their permission flags for a manager', function () {
    // Флаги в настройках по умолчанию выключены
    $this->get(route('news.index'))->assertForbidden();
    $this->get(route('banners.index'))->assertForbidden();
});
