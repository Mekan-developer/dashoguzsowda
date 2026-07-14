<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Repositories\Interfaces\FcmTokenRepositoryInterface;

class PushNotificationService
{
    public function __construct(
        private readonly FcmTokenRepositoryInterface $fcmTokenRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $data  deep-link payload, см. prompt_fcm_backend.md §3
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $this->fcmTokenRepository->tokensForUser($user)->pluck('token');

        $this->dispatchToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $data
     * @return int количество пользователей, у которых нашёлся хотя бы один токен устройства
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): int
    {
        $userIds = collect($users)->pluck('id')->all();

        if (empty($userIds)) {
            return 0;
        }

        $tokensByUser = $this->fcmTokenRepository->tokensForUserIds($userIds)->groupBy('user_id');

        foreach ($tokensByUser as $tokens) {
            $this->dispatchToTokens($tokens->pluck('token'), $title, $body, $data);
        }

        return $tokensByUser->count();
    }

    private function dispatchToTokens(iterable $tokens, string $title, string $body, array $data): void
    {
        $stringData = $this->stringifyData($data);

        foreach ($tokens as $token) {
            SendPushNotificationJob::dispatch($token, $title, $body, $stringData);
        }
    }

    /**
     * FCM требует, чтобы все значения в data payload были строками.
     */
    private function stringifyData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
            ->all();
    }
}
