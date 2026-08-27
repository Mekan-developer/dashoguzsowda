<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** Публичная карточка магазина (mobile_docs/BACKEND_API.md §1). */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photos = $this->whenLoaded('photos', fn () => $this->photos
            ->map(fn ($photo) => Storage::disk('public')->url($photo->path))
            ->values());

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'subtitle_tk' => $this->whenLoaded('category', fn () => $this->category?->name_tk),
            'subtitle_ru' => $this->whenLoaded('category', fn () => $this->category?->name_ru),
            'subtitle'    => $this->whenLoaded('category', fn () => app()->getLocale() === 'tk'
                ? $this->category?->name_tk
                : $this->category?->name_ru),
            'logo_url'    => $this->logo ? Storage::disk('public')->url($this->logo) : null,
            'photos'      => $photos,
            'photo_urls'  => $photos,
        ];
    }
}
