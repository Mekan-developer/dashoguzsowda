<?php

use App\Actions\AssignTariffAction;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// User::factory() assumes an `email_verified_at` column that this project's
// users table does not have — build the row directly with real columns instead.
function actingAsTariffRole(string $role): User
{
    $user = User::factory()->create(['name' => 'Test ' . $role, 'role' => $role]);
    test()->actingAs($user);

    return $user;
}

function tariffPayload(array $overrides = []): array
{
    return array_merge([
        'name_ru' => 'Золотой', 'name_tk' => 'Altyn',
        // Цена нужна админу: тариф оплачивается наличными на руки
        'price' => 250,
        'listings_limit' => 15, 'videos_limit' => 6, 'boost_limit' => 4,
        'duration_days' => 30, 'is_active' => true, 'is_free' => false,
    ], $overrides);
}

it('creates a tariff with bilingual name and all limits persisted', function () {
    actingAsTariffRole('admin');

    $this->post(route('tariffs.store'), tariffPayload())->assertRedirect();

    $tariff = Tariff::where('name_ru', 'Золотой')->firstOrFail();
    expect($tariff->name_tk)->toBe('Altyn')
        ->and($tariff->listings_limit)->toBe(15)
        ->and($tariff->videos_limit)->toBe(6)
        ->and($tariff->boost_limit)->toBe(4);
});

it('updates a tariff without losing the name or boost limit', function () {
    actingAsTariffRole('admin');
    $tariff = Tariff::create(tariffPayload(['name_ru' => 'Старт', 'name_tk' => 'Start', 'boost_limit' => 1]));

    $this->put(route('tariffs.update', $tariff), tariffPayload([
        'name_ru' => 'Старт+', 'name_tk' => 'Start+', 'boost_limit' => 2,
    ]))->assertRedirect();

    $tariff->refresh();
    expect($tariff->name_ru)->toBe('Старт+')
        ->and($tariff->name_tk)->toBe('Start+')
        ->and($tariff->boost_limit)->toBe(2);
});

it('only lets one tariff be free at a time', function () {
    actingAsTariffRole('admin');
    $free = Tariff::create(tariffPayload(['name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => true]));
    $paid = Tariff::create(tariffPayload(['name_ru' => 'Платный', 'name_tk' => 'Pully', 'is_free' => false]));

    $this->put(route('tariffs.update', $paid), tariffPayload([
        'name_ru' => $paid->name_ru, 'name_tk' => $paid->name_tk, 'is_free' => true,
    ]))->assertRedirect();

    expect((bool) $free->fresh()->is_free)->toBeFalse()
        ->and((bool) $paid->fresh()->is_free)->toBeTrue();
});

/**
 * Бесплатный тариф бессрочен: срок в днях к нему не применяется, форма его не
 * показывает, а пришедшее из старой формы значение сервер отбрасывает.
 */
it('drops the duration of a free tariff', function () {
    actingAsTariffRole('admin');

    $this->post(route('tariffs.store'), tariffPayload([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0, 'is_free' => true,
    ]))->assertRedirect();

    expect(Tariff::where('name_ru', 'Бесплатный')->firstOrFail()->duration_days)->toBeNull();
});

it('clears the duration when a paid tariff becomes free', function () {
    actingAsTariffRole('admin');
    $tariff = Tariff::create(tariffPayload(['name_ru' => 'Старт', 'name_tk' => 'Start']));

    $this->put(route('tariffs.update', $tariff), tariffPayload([
        'name_ru' => 'Старт', 'name_tk' => 'Start', 'is_free' => true,
    ]))->assertRedirect();

    expect($tariff->fresh()->duration_days)->toBeNull();
});

it('still requires a duration for a paid tariff', function () {
    actingAsTariffRole('admin');

    $payload = tariffPayload(['name_ru' => 'Без срока', 'name_tk' => 'Möhletsiz']);
    unset($payload['duration_days']);

    $this->post(route('tariffs.store'), $payload)->assertSessionHasErrors('duration_days');
});

/**
 * Раз тариф бессрочен, срок пользователю не проставляется вовсе — иначе он
 * «истёк» бы через 30 дней и лимиты пришлось бы каждый раз добирать запросом
 * бесплатного тарифа.
 */
it('grants a free tariff without an expiry date', function () {
    $free = Tariff::create(tariffPayload([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'is_free' => true, 'duration_days' => null,
    ]));
    $user = User::factory()->create();

    app(AssignTariffAction::class)->execute($user, $free);
    $user->refresh();

    expect($user->tariff_ends_at)->toBeNull()
        ->and($user->activeTariff()->id)->toBe($free->id);
});

/**
 * Тарифы — это лимиты и деньги, поэтому целиком закрыты от менеджера
 * (CLAUDE.md → «Роли»: менеджеру нельзя менять критические настройки).
 */
it('closes tariffs to a manager entirely', function () {
    actingAsTariffRole('manager');
    $tariff = Tariff::create(tariffPayload());

    $this->get(route('tariffs.index'))->assertForbidden();
    $this->post(route('tariffs.store'), tariffPayload(['name_ru' => 'Менеджерский', 'name_tk' => 'Dolandyryjy']))
        ->assertForbidden();
    $this->put(route('tariffs.update', $tariff), tariffPayload())->assertForbidden();
    $this->patch(route('tariffs.toggle', $tariff))->assertForbidden();
    $this->delete(route('tariffs.destroy', $tariff))->assertForbidden();
});

/**
 * Бесплатный тариф — тот, на котором каждый клиент с регистрации и после
 * истечения платного: выключить или удалить его нельзя.
 */
it('never turns the free tariff off', function () {
    actingAsTariffRole('admin');
    $free = Tariff::create(tariffPayload(['name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => true, 'duration_days' => null]));

    $this->patch(route('tariffs.toggle', $free))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error');

    expect((bool) $free->fresh()->is_active)->toBeTrue();
});

it('keeps the free tariff active when it is saved from the form', function () {
    actingAsTariffRole('admin');
    $free = Tariff::create(tariffPayload(['name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => true, 'duration_days' => null]));

    $this->put(route('tariffs.update', $free), tariffPayload([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => true, 'is_active' => false,
    ]))->assertRedirect();

    expect((bool) $free->fresh()->is_active)->toBeTrue();
});

it('never deletes the free tariff', function () {
    actingAsTariffRole('admin');
    $free = Tariff::create(tariffPayload(['name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => true, 'duration_days' => null]));

    $this->delete(route('tariffs.destroy', $free))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error');

    expect(Tariff::find($free->id))->not->toBeNull();
});

it('does not let the free flag be taken off the free tariff', function () {
    actingAsTariffRole('admin');
    $free = Tariff::create(tariffPayload(['name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => true, 'duration_days' => null]));

    $this->put(route('tariffs.update', $free), tariffPayload([
        'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'is_free' => false,
    ]))->assertSessionHasErrors('is_free');

    expect((bool) $free->fresh()->is_free)->toBeTrue();
});

it('still toggles and deletes paid tariffs', function () {
    actingAsTariffRole('admin');
    $paid = Tariff::create(tariffPayload(['name_ru' => 'Платный', 'name_tk' => 'Pully']));

    $this->patch(route('tariffs.toggle', $paid))->assertSessionHas('toast.type', 'success');
    expect((bool) $paid->fresh()->is_active)->toBeFalse();

    $this->delete(route('tariffs.destroy', $paid))->assertSessionHas('toast.type', 'success');
    expect(Tariff::find($paid->id))->toBeNull();
});
