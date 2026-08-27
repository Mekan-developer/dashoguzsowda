<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires auth for preferences', function () {
    $this->getJson('/api/v1/preferences')->assertUnauthorized();
    $this->putJson('/api/v1/preferences', ['onboarding_completed' => true])->assertUnauthorized();
});

it('returns onboarding_completed false by default', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/preferences')
        ->assertOk()
        ->assertExactJson(['data' => ['onboarding_completed' => false]]);
});

it('saves the onboarding flag', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/preferences', ['onboarding_completed' => true])
        ->assertOk()
        ->assertExactJson(['data' => ['onboarding_completed' => true]]);

    $this->getJson('/api/v1/preferences')
        ->assertExactJson(['data' => ['onboarding_completed' => true]]);

    expect($user->fresh()->onboarding_completed)->toBeTrue();
});

it('allows turning the onboarding flag back off', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/preferences', ['onboarding_completed' => true]);
    $this->putJson('/api/v1/preferences', ['onboarding_completed' => false])
        ->assertOk()
        ->assertExactJson(['data' => ['onboarding_completed' => false]]);
});

it('validates the onboarding flag', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/preferences', [])
        ->assertStatus(422)->assertJsonValidationErrors('onboarding_completed');

    $this->putJson('/api/v1/preferences', ['onboarding_completed' => 'ага'])
        ->assertStatus(422)->assertJsonValidationErrors('onboarding_completed');
});

/** Язык и тема остаются device-local — сервер их не хранит. */
it('does not accept locale or theme', function () {
    $user = User::factory()->create(['locale' => 'ru']);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/preferences', [
        'onboarding_completed' => true,
        'locale'               => 'tk',
        'theme'                => 'dark',
    ])->assertOk()->assertExactJson(['data' => ['onboarding_completed' => true]]);

    expect($user->fresh()->locale)->toBe('ru');
});
