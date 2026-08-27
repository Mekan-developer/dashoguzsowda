<?php

namespace App\Repositories\Interfaces;

use App\Models\PushNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PushNotificationRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;
    public function create(array $data): PushNotification;
}
