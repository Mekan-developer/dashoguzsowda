<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'text'           => $this->text,
            // null = отзыв без оценки, звёзды рисовать не нужно
            'rating'         => $this->rating !== null ? (int) $this->rating : null,
            'status'         => $this->status,
            'listing_id'     => $this->listing_id,
            'target_user_id' => $this->target_user_id,
            // Автор отзыва — в публичной ленте грузится всегда
            'author'         => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar ? Storage::disk('public')->url($this->user->avatar) : null,
            ]),
            // Объект отзыва — нужен в /v1/reviews/my, чтобы подписать карточку
            'listing'        => $this->whenLoaded('listing', fn () => $this->listing ? [
                'id'    => $this->listing->id,
                'title' => $this->listing->title,
            ] : null),
            'target_user'    => $this->whenLoaded('targetUser', fn () => $this->targetUser ? [
                'id'     => $this->targetUser->id,
                'name'   => $this->targetUser->name,
                'avatar' => $this->targetUser->avatar ? Storage::disk('public')->url($this->targetUser->avatar) : null,
            ] : null),
            'rejection_reason' => $this->when(
                $this->status === 'rejected' && $this->relationLoaded('rejectionReason') && $this->rejectionReason,
                fn () => [
                    'id'      => $this->rejectionReason->id,
                    'name_tk' => $this->rejectionReason->name_tk,
                    'name_ru' => $this->rejectionReason->name_ru,
                ]
            ),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
