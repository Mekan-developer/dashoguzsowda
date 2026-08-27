<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name_ru' => $this->name_ru,
            'name_tk' => $this->name_tk,
            'icon' => $this->icon_url,
            'image' => $this->image_url,
            'level' => $this->level,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
