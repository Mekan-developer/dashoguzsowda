<?php

namespace App\Jobs;

use App\Repositories\Interfaces\FcmTokenRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

/**
 * Отправка одного push-уведомления на один FCM-токен через HTTP v1 API.
 * Контракт payload — prompt_fcm_backend.md §3 (data.type/data.id, строковые значения).
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly string $token,
        private readonly string $title,
        private readonly string $body,
        private readonly array $data = [],
    ) {
        $this->onQueue('notifications');
    }

    public function handle(Messaging $messaging, FcmTokenRepositoryInterface $fcmTokenRepository): void
    {
        $message = CloudMessage::new()
            ->withToken($this->token)
            ->withNotification(FcmNotification::create($this->title, $this->body))
            ->withData($this->data)
            // background delivery на iOS — см. §3 prompt_fcm_backend.md
            ->withApnsConfig(ApnsConfig::new()->withApsField('content-available', 1));

        try {
            $messaging->send($message);
        } catch (NotFound|InvalidArgument $e) {
            // Токен больше не действителен (переустановка, logout на устройстве) — чистим из БД
            $fcmTokenRepository->deleteToken($this->token);
            Log::info('FCM token removed as invalid', ['token' => $this->token, 'reason' => $e->getMessage()]);
        } catch (MessagingException $e) {
            Log::error('FCM push failed', ['token' => $this->token, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
