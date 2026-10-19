<?php

use App\Events\MessagesReadEvent;
use App\Events\NewMessageEvent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires auth to read chat history', function () {
    $this->getJson('/api/v1/chat')->assertUnauthorized();
});

it('returns the message history for the authenticated user only', function () {
    Message::create(['user_id' => $this->user->id, 'sender' => 'user', 'text' => 'Здравствуйте', 'is_read' => false]);
    Message::create(['user_id' => $this->user->id, 'sender' => 'admin', 'text' => 'Здравствуйте, чем помочь?', 'is_read' => false]);

    $other = User::factory()->create();
    Message::create(['user_id' => $other->id, 'sender' => 'user', 'text' => 'Чужой диалог', 'is_read' => false]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/chat')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.text'))->toBe('Здравствуйте');
});

it('lets the user send a message and broadcasts it', function () {
    Event::fake([NewMessageEvent::class]);
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/chat', ['text' => 'Нужна помощь с объявлением'])
        ->assertCreated();

    expect($response->json('data.sender'))->toBe('user');
    expect($response->json('data.text'))->toBe('Нужна помощь с объявлением');

    $this->assertDatabaseHas('messages', [
        'user_id' => $this->user->id,
        'sender'  => 'user',
        'text'    => 'Нужна помощь с объявлением',
        'is_read' => false,
    ]);

    Event::assertDispatched(NewMessageEvent::class, fn ($event) => $event->message->user_id === $this->user->id);
});

it('validates the message text', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/chat', ['text' => ''])->assertUnprocessable();
});

it('lets the user mark admin replies as read', function () {
    Message::create(['user_id' => $this->user->id, 'sender' => 'admin', 'text' => 'Ответ поддержки', 'is_read' => false]);

    Sanctum::actingAs($this->user);

    $this->patchJson('/api/v1/chat/read')->assertOk();

    expect(Message::where('user_id', $this->user->id)->where('sender', 'admin')->where('is_read', false)->count())->toBe(0);
});

it('broadcasts the read receipt so the admin sees the tick without a reload', function () {
    Event::fake([MessagesReadEvent::class]);

    Message::create(['user_id' => $this->user->id, 'sender' => 'admin', 'text' => 'Ответ поддержки', 'is_read' => false]);

    Sanctum::actingAs($this->user);

    $this->patchJson('/api/v1/chat/read')->assertOk();

    Event::assertDispatched(
        MessagesReadEvent::class,
        fn ($event) => $event->userId === $this->user->id && $event->sender === 'admin',
    );
});

it('does not broadcast a read receipt when there was nothing unread', function () {
    // Мобилка дёргает /chat/read при каждом открытии экрана: без этой проверки
    // канал засыпало бы отметками, ничего не меняющими на другой стороне.
    Event::fake([MessagesReadEvent::class]);

    Message::create(['user_id' => $this->user->id, 'sender' => 'admin', 'text' => 'Ответ поддержки', 'is_read' => true]);

    Sanctum::actingAs($this->user);

    $this->patchJson('/api/v1/chat/read')->assertOk();

    Event::assertNotDispatched(MessagesReadEvent::class);
});

/** Брокер WebSocket лёг: broadcast() бросает на любом событии */
function breakBroadcaster(): void
{
    test()->mock(BroadcastFactory::class)
        ->shouldReceive('event')
        ->andThrow(new RuntimeException('Reverb недоступен'));
}

it('keeps the message and answers 201 when the websocket broker is down', function () {
    breakBroadcaster();
    Log::spy();
    Sanctum::actingAs($this->user);

    // Сообщение уже в БД — без этого отправитель увидел бы 500 и написал повторно
    $this->postJson('/api/v1/chat', ['text' => 'Есть кто?'])->assertCreated();

    $this->assertDatabaseHas('messages', ['user_id' => $this->user->id, 'text' => 'Есть кто?']);
    Log::shouldHaveReceived('warning')->once();
});

it('marks replies as read even when the read receipt cannot be broadcast', function () {
    breakBroadcaster();
    Log::spy();
    Message::create(['user_id' => $this->user->id, 'sender' => 'admin', 'text' => 'Ответ поддержки', 'is_read' => false]);

    Sanctum::actingAs($this->user);

    $this->patchJson('/api/v1/chat/read')->assertOk();

    expect(Message::where('user_id', $this->user->id)->where('is_read', false)->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
});
