<?php

namespace App\Actions;

use App\Models\User;
use App\Repositories\Interfaces\FcmTokenRepositoryInterface;

class RegisterFcmTokenAction
{
    public function __construct(
        private readonly FcmTokenRepositoryInterface $fcmTokenRepository,
    ) {}

    public function execute(User $user, string $token, ?string $platform = null): void
    {
        $this->fcmTokenRepository->upsert($user->id, $token, $platform);
    }
}
