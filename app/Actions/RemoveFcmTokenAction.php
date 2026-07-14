<?php

namespace App\Actions;

use App\Models\User;
use App\Repositories\Interfaces\FcmTokenRepositoryInterface;

class RemoveFcmTokenAction
{
    public function __construct(
        private readonly FcmTokenRepositoryInterface $fcmTokenRepository,
    ) {}

    public function execute(User $user, string $token): void
    {
        $this->fcmTokenRepository->deleteForUser($user, $token);
    }
}
