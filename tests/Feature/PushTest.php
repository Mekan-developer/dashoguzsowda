<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\FcmToken;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->admin()->create();
});

it('queues a push job per device token for all active users', function () {
    $userA = User::factory()->create(['status' => 'active']);
    $userB = User::factory()->create(['status' => 'active']);
    FcmToken::create(['user_id' => $userA->id, 'token' => 'token-a1']);
    FcmToken::create(['user_id' => $userA->id, 'token' => 'token-a2']);
    FcmToken::create(['user_id' => $userB->id, 'token' => 'token-b1']);

    $this->actingAs($this->admin)
        ->post(route('push.send'), [
            'title'  => 'Заголовок',
            'body'   => 'Текст',
            'target' => 'all',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendPushNotificationJob::class, 3);

    $log = PushNotification::first();
    expect($log->sent_count)->toBe(2); // 2 пользователя дошли (у обоих есть токен)
});

it('does not count users without any fcm token as reached', function () {
    User::factory()->create(['status' => 'active']); // без токена

    $this->actingAs($this->admin)
        ->post(route('push.send'), [
            'title'  => 'Заголовок',
            'body'   => 'Текст',
            'target' => 'all',
        ])
        ->assertRedirect();

    Queue::assertNotPushed(SendPushNotificationJob::class);
    expect(PushNotification::first()->sent_count)->toBe(0);
});

it('targets only selected users', function () {
    $target = User::factory()->create(['status' => 'active']);
    $other = User::factory()->create(['status' => 'active']);
    FcmToken::create(['user_id' => $target->id, 'token' => 'token-target']);
    FcmToken::create(['user_id' => $other->id, 'token' => 'token-other']);

    $this->actingAs($this->admin)
        ->post(route('push.send'), [
            'title'    => 'Заголовок',
            'body'     => 'Текст',
            'target'   => 'selected',
            'user_ids' => [$target->id],
        ])
        ->assertRedirect();

    // У обоих пользователей есть токен, но выбран только один — значит и job должна уйти только одна
    Queue::assertPushed(SendPushNotificationJob::class, 1);
    expect(PushNotification::first()->sent_count)->toBe(1);
});

it('requires title and body', function () {
    $this->actingAs($this->admin)
        ->post(route('push.send'), ['target' => 'all'])
        ->assertSessionHasErrors(['title', 'body']);
});

it('is forbidden for managers', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->post(route('push.send'), ['title' => 'x', 'body' => 'y', 'target' => 'all'])
        ->assertForbidden();
});
