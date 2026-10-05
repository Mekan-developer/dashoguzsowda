<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Новое объявление ждёт модерации — админка обновляет колокольчик в реальном
 * времени и проигрывает звук. Только после коммита: иначе админ откроет
 * карточку раньше, чем объявление окажется в БД.
 */
class ListingSubmitted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Listing $listing) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin')];
    }

    public function broadcastAs(): string
    {
        return 'listing.submitted';
    }

    public function broadcastQueue(): string
    {
        return 'notifications';
    }

    public function broadcastWith(): array
    {
        return [
            'id'    => $this->listing->id,
            'title' => $this->listing->title,
        ];
    }
}
