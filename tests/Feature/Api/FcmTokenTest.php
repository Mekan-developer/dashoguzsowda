<?php

use App\Models\FcmToken;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('registers a new fcm token without re-login', function () {
    $this->putJson('/api/v1/profile/fcm-token', ['fcm_token' => 'new-token', 'platform' => 'android'])
        ->assertOk();

    $token = FcmToken::where('user_id', $this->user->id)->first();
    expect($token->token)->toBe('new-token')
        ->and($token->platform)->toBe('android');
});

it('upserts by token when the same token refreshes for another user session', function () {
    FcmToken::create(['user_id' => $this->user->id, 'token' => 'shared-token', 'platform' => 'android']);

    $this->putJson('/api/v1/profile/fcm-token', ['fcm_token' => 'shared-token', 'platform' => 'ios'])
        ->assertOk();

    expect(FcmToken::where('token', 'shared-token')->count())->toBe(1)
        ->and(FcmToken::where('token', 'shared-token')->first()->platform)->toBe('ios');
});

it('ignores an empty fcm token instead of deleting all device tokens', function () {
    FcmToken::create(['user_id' => $this->user->id, 'token' => 'keep-me']);

    $this->putJson('/api/v1/profile/fcm-token', ['fcm_token' => null])->assertOk();

    expect(FcmToken::where('user_id', $this->user->id)->where('token', 'keep-me')->exists())->toBeTrue();
});

it('rejects an invalid platform value', function () {
    $this->putJson('/api/v1/profile/fcm-token', ['fcm_token' => 'x', 'platform' => 'windows'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('platform');
});
