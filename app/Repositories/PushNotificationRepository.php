<?php

namespace App\Repositories;

use App\Models\PushNotification;
use App\Repositories\Interfaces\PushNotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PushNotificationRepository implements PushNotificationRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return PushNotification::with('creator')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): PushNotification
    {
        return PushNotification::create($data);
    }
}
