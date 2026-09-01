<?php

namespace App\Repositories\Interfaces;

use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ChatRepositoryInterface
{
    public function getDialogs(int $perPage = 25): LengthAwarePaginator;
    public function getMessages(int $userId): Collection;
    public function createMessage(array $data): Message;
    public function markAsRead(int $userId, string $sender): int;
    public function countUnread(): int;
}
