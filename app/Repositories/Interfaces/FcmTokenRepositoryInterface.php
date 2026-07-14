<?php

namespace App\Repositories\Interfaces;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface FcmTokenRepositoryInterface
{
    public function upsert(int $userId, string $token, ?string $platform): FcmToken;
    public function deleteToken(string $token): void;
    public function deleteForUser(User $user, string $token): void;
    public function tokensForUser(User $user): Collection;

    /**
     * @param  array<int>  $userIds
     */
    public function tokensForUserIds(array $userIds): Collection;
}
