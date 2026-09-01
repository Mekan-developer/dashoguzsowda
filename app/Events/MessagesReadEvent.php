<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Собеседник открыл диалог и прочитал сообщения.
 *
 * Без этого события галочка «прочитано» в админке оставалась бы враньём до
 * перезагрузки страницы: сам факт прочтения происходит на другом клиенте.
 */
class MessagesReadEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $userId  владелец диалога (он же имя канала)
     * @param  string  $sender  чьи сообщения прочитаны: 'admin' — их прочитал
     *                          пользователь мобилки, 'user' — их прочитал оператор
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $sender,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'messages-read';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'sender'  => $this->sender,
        ];
    }
}
