<?php

use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * К-5: orWhere в UserRepository::paginate не был сгруппирован, а OR в SQL
 * слабее AND — совпадение по name проходило мимо `role = 'user'`, и в списке
 * пользователей всплывали admin/manager (с телефоном, note и причиной блокировки).
 * Фильтры «статус» и «регион» при активном поиске тоже переставали работать.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('never shows admins or managers in the user list search', function () {
    User::factory()->admin()->create(['name' => 'Zorro']);
    User::factory()->manager()->create(['name' => 'Zorro Manager']);
    $client = User::factory()->create(['name' => 'Zorro Client']);

    $this->get(route('users.index', ['search' => 'Zorro']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $client->id));
});

it('keeps status and region filters as AND while searching', function () {
    $ahal   = Region::create(['name_ru' => 'Ахал', 'name_tk' => 'Ahal']);
    $mary   = Region::create(['name_ru' => 'Мары', 'name_tk' => 'Mary']);

    $target = User::factory()->create(['name' => 'Zorro One', 'region_id' => $ahal->id]);
    User::factory()->create(['name' => 'Zorro Two', 'region_id' => $mary->id]);
    User::factory()->blocked()->create(['name' => 'Zorro Three', 'region_id' => $ahal->id]);

    $this->get(route('users.index', ['search' => 'Zorro', 'status' => 'active', 'region_id' => $ahal->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id));
});

it('treats % and _ in the search box as plain characters', function () {
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Bob']);

    $this->get(route('users.index', ['search' => '%']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('users.data', 0));
});
