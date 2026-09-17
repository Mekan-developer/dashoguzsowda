<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'tags'             => $this->tags ?? [],
            'status'           => $this->status,
            'category'         => $this->whenLoaded('category', fn () => $this->category ? [
                'id'      => $this->category->id,
                'name_tk' => $this->category->name_tk,
                'name_ru' => $this->category->name_ru,
            ] : null),
            'rejection_reason' => $this->when(
                $this->status === 'rejected' && $this->relationLoaded('rejectionReason') && $this->rejectionReason,
                fn () => [
                    'id'      => $this->rejectionReason->id,
                    'name_tk' => $this->rejectionReason->name_tk,
                    'name_ru' => $this->rejectionReason->name_ru,
                ]
            ),
            'duration_seconds' => (int) $this->duration_seconds,
            'likes_count'      => (int) $this->likes_count,
            'views'            => (int) $this->views,
            // Присутствует только когда запрос сделан с Bearer-токеном (флаг считает репозиторий)
            'is_liked'         => $this->when(
                array_key_exists('is_liked', $this->getAttributes()),
                fn () => (bool) $this->getAttributes()['is_liked'],
            ),
            // Сжатие идёт в фоновой очереди `media`: пока processing=true,
            // video указывает на загруженный оригинал, preview ещё отсутствует
            'processing'       => ! $this->is_processed,
            'video'            => Storage::disk('public')->url($this->processed_path ?? $this->path),
            'preview'          => $this->preview_path ? Storage::disk('public')->url($this->preview_path) : null,
            'user'             => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar ? Storage::disk('public')->url($this->user->avatar) : null,
            ]),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
