<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Магазин заказа глазами покупателя: чей это товар, на какую сумму и что
 * продавец ответил. Телефон магазина отдаётся намеренно — доставку делает сам
 * продавец, и все вопросы по заказу решаются с ним.
 */
class SuborderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            // pending — ждём ответа магазина, accepted/declined — он ответил,
            // canceled — покупатель отменил заказ раньше, чем продавец ответил
            'status'   => $this->status,
            'subtotal' => (float) $this->subtotal,
            'comment'  => $this->comment,
            'store'    => $this->whenLoaded('store', fn () => $this->store ? [
                'id'       => $this->store->id,
                'name'     => $this->store->name,
                'phone'    => $this->store->phone,
                'logo_url' => $this->store->logo ? Storage::disk('public')->url($this->store->logo) : null,
            ] : null),
            'items'    => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
