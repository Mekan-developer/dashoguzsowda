<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\FcmToken;
use App\Models\User;
use App\Repositories\Interfaces\FcmTokenRepositoryInterface;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerError;

it('sends the notification via FCM messaging', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')->once();

    $repository = Mockery::mock(FcmTokenRepositoryInterface::class);
    $repository->shouldNotReceive('deleteToken');

    (new SendPushNotificationJob('tok-1', 'Title', 'Body', ['type' => 'chat']))
        ->handle($messaging, $repository);
});

it('removes the token when fcm reports it as no longer registered', function () {
    $user = User::factory()->create();
    FcmToken::create(['user_id' => $user->id, 'token' => 'stale-token']);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')->andThrow(NotFound::becauseTokenNotFound('stale-token'));

    (new SendPushNotificationJob('stale-token', 'Title', 'Body'))
        ->handle($messaging, app(FcmTokenRepositoryInterface::class));

    expect(FcmToken::where('token', 'stale-token')->exists())->toBeFalse();
});

it('rethrows other messaging errors so the job retries', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')->andThrow(new ServerError('temporary outage'));

    $repository = Mockery::mock(FcmTokenRepositoryInterface::class);
    $repository->shouldNotReceive('deleteToken');

    expect(fn () => (new SendPushNotificationJob('tok-1', 'Title', 'Body'))->handle($messaging, $repository))
        ->toThrow(ServerError::class);
});
