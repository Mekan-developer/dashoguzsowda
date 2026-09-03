<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Позиция заказа. title и unit_price — снимок на момент оформления, а не
 * текущие значения объявления: цена могла измениться, объявление — исчезнуть.
 * listing_id остаётся ссылкой на карточку, если товар ещё жив.
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'listing_id'   => $this->listing_id,
            'title'        => $this->title,
            'unit_price'   => (float) $this->unit_price,
            'is_wholesale' => (bool) $this->is_wholesale,
            'qty'          => (int) $this->qty,
            'total'        => (float) $this->total,
            // Обложка товара; null — объявление удалено, показываем заглушку
            'photo'        => $this->whenLoaded('listing', function () {
                $media = $this->listing?->relationLoaded('media') ? $this->listing->media->first() : null;

                return $media
                    ? Storage::disk('public')->url($media->thumb_path ?? $media->path)
                    : null;
            }),
        ];
    }
}
