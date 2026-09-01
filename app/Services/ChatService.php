<?php

namespace App\Services;

use App\Events\AdminReplied;
use App\Events\MessagesReadEvent;
use App\Events\NewMessageEvent;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Interfaces\ChatRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
    ) {}

    public function dialogs(): LengthAwarePaginator
    {
        return $this->chatRepository->getDialogs();
    }

    /**
     * Плоский список диалогов для сайдбара страницы Chat/Show (без пагинации).
     */
    public function dialogsList(): Collection
    {
        return $this->chatRepository->getDialogs()->getCollection()->values();
    }

    public function messages(int $userId): Collection
    {
        return $this->chatRepository->getMessages($userId);
    }

    public function reply(User $user, User $admin, string $text): Message
    {
        $message = $this->chatRepository->createMessage([
            'user_id'  => $user->id,
            'sender'   => 'admin',
            'admin_id' => $admin->id,
            'text'     => $text,
            'is_read'  => false,
        ]);

        $this->broadcastMessage($message);
        event(new AdminReplied($message));

        return $message;
    }

    public function sendFromUser(User $user, string $text): Message
    {
        $message = $this->chatRepository->createMessage([
            'user_id' => $user->id,
            'sender'  => 'user',
            'text'    => $text,
            'is_read' => false,
        ]);

        $this->broadcastMessage($message);

        return $message;
    }

    /**
     * Сообщение уже сохранено в БД, поэтому недоступный брокер не должен
     * превращаться в 500 для отправителя: собеседник получит его при следующем
     * GET /chat, а оператор иначе решит, что ответ не ушёл, и напишет повторно.
     */
    private function broadcastMessage(Message $message): void
    {
        try {
            broadcast(new NewMessageEvent($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Не удалось разослать сообщение чата через WebSocket', [
                'message_id' => $message->id,
                'user_id'    => $message->user_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Оператор открыл диалог — сообщения пользователя прочитаны.
     */
    public function markRead(int $userId): void
    {
        if ($this->chatRepository->markAsRead($userId, 'user') > 0) {
            $this->broadcastRead($userId, 'user');
        }
    }

    /**
     * Пользователь открыл чат в мобилке — ответы оператора прочитаны.
     */
    public function markReadByUser(int $userId): void
    {
        if ($this->chatRepository->markAsRead($userId, 'admin') > 0) {
            $this->broadcastRead($userId, 'admin');
        }
    }

    /**
     * Отметка о прочтении — вещь необязательная: если брокер недоступен,
     * галочка обновится при следующей загрузке диалога, а ронять из-за неё
     * открытие страницы или ответ 200 мобилке нельзя.
     */
    private function broadcastRead(int $userId, string $sender): void
    {
        try {
            broadcast(new MessagesReadEvent($userId, $sender));
        } catch (\Throwable $e) {
            Log::warning('Не удалось разослать отметку о прочтении через WebSocket', [
                'user_id' => $userId,
                'sender'  => $sender,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function countUnread(): int
    {
        return $this->chatRepository->countUnread();
    }
}
