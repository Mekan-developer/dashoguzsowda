<?php

namespace App\Repositories;

use App\Models\Message;
use App\Models\User;
use App\Repositories\Interfaces\ChatRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ChatRepository implements ChatRepositoryInterface
{
    public function getDialogs(int $perPage = 25): LengthAwarePaginator
    {
        return User::where('role', 'user')
            ->whereHas('messages')
            ->withCount(['messages as unread_count' => fn($q) => $q->where('is_read', false)->where('sender', 'user')])
            ->withMax('messages as last_message_at', 'created_at')
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            // Сортировка по последнему сообщению, а не по users.updated_at:
            // новое сообщение не трогает запись пользователя, поэтому раньше
            // диалог с только что написавшим не поднимался наверх списка.
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getMessages(int $userId): Collection
    {
        // orderBy('id') — не косметика: timestamps хранятся с точностью до секунды,
        // и два сообщения, отправленных подряд, иначе возвращаются в произвольном
        // порядке (какой именно — зависит от плана запроса).
        return Message::where('user_id', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function createMessage(array $data): Message
    {
        return Message::create($data);
    }

    /**
     * @return int сколько сообщений реально перешло в «прочитано» — по нулю
     *             видно, что рассылать отметку собеседнику не нужно
     */
    public function markAsRead(int $userId, string $sender): int
    {
        return Message::where('user_id', $userId)
            ->where('sender', $sender)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function countUnread(): int
    {
        return Message::where('sender', 'user')->where('is_read', false)
            ->distinct('user_id')->count('user_id');
    }
}
