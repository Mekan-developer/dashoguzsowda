<?php

namespace App\Listeners;

use App\Events\AdminReplied;
use App\Services\PushNotificationService;

class SendChatReplyPush
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function handle(AdminReplied $event): void
    {
        $user = $event->message->user;

        if (! $user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            __('messages.push_chat_reply_title'),
            __('messages.push_chat_reply_body'),
            ['type' => 'chat'],
        );
    }
}
