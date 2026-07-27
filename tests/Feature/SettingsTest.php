<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// User::factory() assumes an `email_verified_at` column that this project's
// users table does not have — build the row directly with real columns instead.
function actingAsSettingsRole(string $role): User
{
    $user = User::create([
        'name' => 'Test ' . $role,
        'phone' => '+993' . fake()->unique()->numerify('#########'),
        'role' => $role,
        'password' => Hash::make('password'),
        'remember_token' => Str::random(10),
    ]);
    test()->actingAs($user);

    return $user;
}

it('lets admin view the settings page', function () {
    actingAsSettingsRole('admin');

    $this->get(route('settings.index'))->assertOk();
});

it('forbids manager from viewing the settings page', function () {
    actingAsSettingsRole('manager');

    $this->get(route('settings.index'))->assertForbidden();
});

it('lets admin toggle the manager news permission', function () {
    actingAsSettingsRole('admin');

    $this->patch(route('settings.manager-permissions'), ['can_manage_news' => true])
        ->assertRedirect();

    expect((bool) Setting::get('manager_can_manage_news'))->toBeTrue();
});

it('blocks manager from news routes until the permission is granted, then allows it', function () {
    actingAsSettingsRole('manager');

    $this->get(route('news.index'))->assertForbidden();

    Setting::set('manager_can_manage_news', '1');

    $this->get(route('news.index'))->assertOk();
});

it('returns monitoring JSON only to admin', function () {
    actingAsSettingsRole('admin');
    $this->get(route('settings.monitoring'))
        ->assertOk()
        ->assertJsonStructure([
            'queues' => ['ok', 'pending', 'failed', 'worker', 'checked_at'],
            'ws'     => ['ok', 'host', 'port', 'checked_at'],
            'fcm'    => ['ok', 'configured', 'project_id', 'tokens', 'checked_at'],
        ]);

    actingAsSettingsRole('manager');
    $this->get(route('settings.monitoring'))->assertForbidden();
});

it('lets admin update the boost interval and validates it', function () {
    actingAsSettingsRole('admin');

    $this->patch(route('settings.boost'), ['boost_interval_hours' => 48])->assertRedirect();
    expect((int) Setting::get('boost_interval_hours'))->toBe(48);

    // Ноль/пусто отклоняются валидацией
    $this->patch(route('settings.boost'), ['boost_interval_hours' => 0])
        ->assertSessionHasErrors('boost_interval_hours');
});

it('forbids manager from changing the boost interval', function () {
    actingAsSettingsRole('manager');

    $this->patch(route('settings.boost'), ['boost_interval_hours' => 12])->assertForbidden();
});
