<?php

namespace App\Repositories;

use App\Models\FcmToken;
use App\Models\User;
use App\Repositories\Interfaces\FcmTokenRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FcmTokenRepository implements FcmTokenRepositoryInterface
{
    public function upsert(int $userId, string $token, ?string $platform): FcmToken
    {
        return FcmToken::updateOrCreate(
            ['token' => $token],
            ['user_id' => $userId, 'platform' => $platform, 'last_used_at' => now()],
        );
    }

    public function deleteToken(string $token): void
    {
        FcmToken::where('token', $token)->delete();
    }

    public function deleteForUser(User $user, string $token): void
    {
        FcmToken::where('user_id', $user->id)->where('token', $token)->delete();
    }

    public function tokensForUser(User $user): Collection
    {
        return FcmToken::where('user_id', $user->id)->get();
    }

    public function tokensForUserIds(array $userIds): Collection
    {
        return FcmToken::whereIn('user_id', $userIds)->get();
    }
}
