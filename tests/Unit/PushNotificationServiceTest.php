<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\FcmToken;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('dispatches one job per token and stringifies data payload', function () {
    $user = User::factory()->create();
    FcmToken::create(['user_id' => $user->id, 'token' => 'tok-1']);
    FcmToken::create(['user_id' => $user->id, 'token' => 'tok-2']);

    app(PushNotificationService::class)->sendToUser($user, 'Title', 'Body', ['type' => 'listing', 'id' => 42]);

    Queue::assertPushed(SendPushNotificationJob::class, 2);
});

it('sends nothing when the user has no registered device tokens', function () {
    $user = User::factory()->create();

    app(PushNotificationService::class)->sendToUser($user, 'Title', 'Body', ['type' => 'chat']);

    Queue::assertNotPushed(SendPushNotificationJob::class);
});

it('sendToUsers returns the count of users actually reached', function () {
    $withToken = User::factory()->create();
    $withoutToken = User::factory()->create();
    FcmToken::create(['user_id' => $withToken->id, 'token' => 'tok-1']);

    $reached = app(PushNotificationService::class)->sendToUsers(
        collect([$withToken, $withoutToken]), 'Title', 'Body', ['type' => 'news', 'id' => 1],
    );

    expect($reached)->toBe(1);
    Queue::assertPushed(SendPushNotificationJob::class, 1);
});
