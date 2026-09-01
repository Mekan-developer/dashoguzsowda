<?php

use App\Events\MessagesReadEvent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('marks a user dialog as read when an admin opens the chat', function () {
    $admin = User::factory()->admin()->create();
    $chatUser = User::factory()->create(['role' => 'user']);

    Message::create(['user_id' => $chatUser->id, 'sender' => 'user', 'text' => 'Привет', 'is_read' => false]);

    $this->actingAs($admin)->get(route('chat.show', $chatUser))->assertOk();

    expect(Message::where('user_id', $chatUser->id)->where('is_read', false)->count())->toBe(0);
});

it('broadcasts the read receipt when the admin opens a dialog with unread messages', function () {
    Event::fake([MessagesReadEvent::class]);

    $admin = User::factory()->admin()->create();
    $chatUser = User::factory()->create(['role' => 'user']);

    Message::create(['user_id' => $chatUser->id, 'sender' => 'user', 'text' => 'Привет', 'is_read' => false]);

    $this->actingAs($admin)->get(route('chat.show', $chatUser))->assertOk();

    Event::assertDispatched(
        MessagesReadEvent::class,
        fn ($event) => $event->userId === $chatUser->id && $event->sender === 'user',
    );
});

it('does not broadcast a read receipt when the dialog had nothing unread', function () {
    Event::fake([MessagesReadEvent::class]);

    $admin = User::factory()->admin()->create();
    $chatUser = User::factory()->create(['role' => 'user']);

    Message::create(['user_id' => $chatUser->id, 'sender' => 'user', 'text' => 'Привет', 'is_read' => true]);

    $this->actingAs($admin)->get(route('chat.show', $chatUser))->assertOk();

    Event::assertNotDispatched(MessagesReadEvent::class);
});
