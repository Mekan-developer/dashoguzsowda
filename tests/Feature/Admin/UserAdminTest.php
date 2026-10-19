<?php

use App\Models\Region;
use App\Models\Tariff;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->free = Tariff::create([
        'name' => 'Basic', 'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt', 'price' => 0,
        'listings_limit' => 5, 'videos_limit' => 2, 'boost_limit' => 3,
        'duration_days' => null, 'is_free' => true, 'is_active' => true,
    ]);
    $this->paid = Tariff::create([
        'name' => 'Premium', 'name_ru' => 'Премиум', 'name_tk' => 'Premium', 'price' => 250,
        'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50,
        'duration_days' => 30, 'is_free' => false, 'is_active' => true,
    ]);

    $this->client = User::factory()->create();
    $this->admin  = User::factory()->admin()->create();
});

it('shows the user card with tariff and stats', function () {
    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('users.show', $this->client))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Users/Show')
            ->where('user.id', $this->client->id)
            ->where('user.tariff.id', $this->free->id)
            ->has('stats')
            ->has('userListings'));
});

it('blocks a user with a reason and unblocks them back', function () {
    $this->actingAs($this->admin);

    $this->patch(route('users.block', $this->client), ['reason' => 'Спам в объявлениях'])->assertRedirect();

    $this->client->refresh();
    expect($this->client->status)->toBe('blocked')
        ->and($this->client->blocked_reason)->toBe('Спам в объявлениях')
        ->and($this->client->blocked_at)->not->toBeNull();

    $this->patch(route('users.unblock', $this->client))->assertRedirect();

    $this->client->refresh();
    expect($this->client->status)->toBe('active')
        ->and($this->client->blocked_reason)->toBeNull()
        ->and($this->client->blocked_at)->toBeNull();
});

it('updates the admin-editable profile fields', function () {
    $region = Region::create(['name_ru' => 'Дашогуз', 'name_tk' => 'Daşoguz']);
    $this->actingAs($this->admin);

    $this->put(route('users.update', $this->client), [
        'name' => 'Мекан', 'note' => 'Оптовик, звонить после обеда', 'region_id' => $region->id,
    ])->assertRedirect();

    $this->client->refresh();
    expect($this->client->name)->toBe('Мекан')
        ->and($this->client->note)->toBe('Оптовик, звонить после обеда')
        ->and($this->client->region_id)->toBe($region->id);

    $this->put(route('users.update', $this->client), ['region_id' => 999])->assertSessionHasErrors('region_id');
});

it('assigns a paid tariff for its duration and the free one without an end date', function () {
    $this->actingAs($this->admin);

    $this->post(route('users.tariff', $this->client), ['tariff_id' => $this->paid->id])->assertRedirect();

    $this->client->refresh();
    expect($this->client->tariff_id)->toBe($this->paid->id)
        ->and($this->client->tariff_ends_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $this->post(route('users.tariff', $this->client), ['tariff_id' => $this->free->id])->assertRedirect();

    $this->client->refresh();
    expect($this->client->tariff_id)->toBe($this->free->id)
        ->and($this->client->tariff_ends_at)->toBeNull();

    $this->post(route('users.tariff', $this->client), ['tariff_id' => 999])->assertSessionHasErrors('tariff_id');
});

it('deletes a user', function () {
    $this->actingAs($this->admin);

    $this->delete(route('users.destroy', $this->client))->assertRedirect(route('users.index'));

    expect(User::find($this->client->id))->toBeNull();
});

it('lets a manager view users but not change them', function () {
    $this->actingAs(User::factory()->manager()->create());

    $this->patch(route('users.block', $this->client))->assertForbidden();
    $this->post(route('users.tariff', $this->client), ['tariff_id' => $this->paid->id])->assertForbidden();
    $this->delete(route('users.destroy', $this->client))->assertForbidden();

    $this->client->refresh();
    expect($this->client->status)->toBe('active')
        ->and($this->client->tariff_id)->toBe($this->free->id);
});
