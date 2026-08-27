<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * К-3: гейт вызывал $user->hasRole('admin') из Spatie, а App\Models\User
 * трейт HasRoles не подключает — при APP_ENV=production /horizon отдавал 500
 * (BadMethodCallException) вместо 403, и очереди в проде было не посмотреть.
 */

it('allows only admin through the horizon gate', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->manager()->create())->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('viewHorizon'))->toBeFalse();
});

it('denies the horizon gate to guests without throwing', function () {
    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});
