<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_ru' => $this->title_ru,
            'title_tk' => $this->title_tk,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'link_type' => $this->link_type,
            'link_url' => $this->link_url,
            'listing_id' => $this->listing_id,
            // 'starts_at' => $this->starts_at?->toIso8601String(),
            // 'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }
}
