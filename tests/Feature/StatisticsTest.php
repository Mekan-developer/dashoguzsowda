<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsStatsAdmin(): User
{
    $admin = User::factory()->admin()->create(['name' => 'Stats Admin']);
    test()->actingAs($admin);

    return $admin;
}

function makeUserRegisteredAt(string $createdAt): User
{
    $user = User::factory()->create(['name' => 'User ' . $createdAt]);
    $user->forceFill(['created_at' => $createdAt])->save();

    return $user;
}

it('shows total users and new users for today by default', function () {
    actingAsStatsAdmin();
    makeUserRegisteredAt(now()->toDateTimeString());
    makeUserRegisteredAt(now()->subDays(10)->toDateTimeString());

    $this->get(route('statistics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Statistics/Index')
            ->where('stats.users.total', 2)
            ->where('stats.users.period', 1));
});

it('counts new users for the week and month presets', function () {
    actingAsStatsAdmin();
    makeUserRegisteredAt(now()->toDateTimeString());              // сегодня
    makeUserRegisteredAt(now()->subDays(3)->toDateTimeString());  // на этой неделе
    makeUserRegisteredAt(now()->subDays(20)->toDateTimeString()); // в этом месяце
    makeUserRegisteredAt(now()->subDays(60)->toDateTimeString()); // вне месяца

    $this->get(route('statistics.index', ['period' => 'week']))
        ->assertInertia(fn (Assert $page) => $page->where('stats.users.period', 2));

    $this->get(route('statistics.index', ['period' => 'month']))
        ->assertInertia(fn (Assert $page) => $page->where('stats.users.period', 3));
});

it('counts new users for a custom date range', function () {
    actingAsStatsAdmin();
    makeUserRegisteredAt('2026-06-05 12:00:00');
    makeUserRegisteredAt('2026-06-25 12:00:00');
    makeUserRegisteredAt('2026-07-05 12:00:00');

    $this->get(route('statistics.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.users.period', 2)
            ->where('stats.users.total', 3));
});

it('rejects an invalid period preset and a reversed date range', function () {
    actingAsStatsAdmin();

    $this->from(route('dashboard'))
        ->get(route('statistics.index', ['period' => 'year']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('period');

    $this->from(route('dashboard'))
        ->get(route('statistics.index', ['from' => '2026-06-30', 'to' => '2026-06-01']))
        ->assertSessionHasErrors('to');
});
