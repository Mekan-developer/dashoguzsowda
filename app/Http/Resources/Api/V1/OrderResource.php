<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ покупателя (mobile_docs/BACKEND_API.md §15).
 *
 * Тексты статусов рисует мобилка из своих словарей — здесь только коды,
 * как и в остальном API.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            // Номер для разговора с продавцом: «заказ 000123»
            'number' => $this->number,
            // pending | approved | completed | rejected | canceled
            'status' => $this->status,
            'total'  => (float) $this->total,

            'contact_name' => $this->contact_name,
            'phone'        => $this->phone,
            'address'      => $this->address,
            'region'       => $this->whenLoaded('region', fn () => $this->region ? [
                'id'      => $this->region->id,
                'name_tk' => $this->region->name_tk,
                'name_ru' => $this->region->name_ru,
            ] : null),
            'city'         => $this->whenLoaded('city', fn () => $this->city ? [
                'id'      => $this->city->id,
                'name_tk' => $this->city->name_tk,
                'name_ru' => $this->city->name_ru,
            ] : null),
            'district'     => $this->whenLoaded('district', fn () => $this->district ? [
                'id'      => $this->district->id,
                'name_tk' => $this->district->name_tk,
                'name_ru' => $this->district->name_ru,
            ] : null),

            'comment' => $this->comment,
            // Причина отказа от продавца: решение по заказу принимает он
            'decision_comment' => $this->decision_comment,

            // Магазин заказа: его контакты, сумма и ответ. Всегда один элемент —
            // заказ на нескольких продавцов сразу не оформляется
            'stores' => SuborderResource::collection($this->whenLoaded('suborders')),

            'can_cancel' => $this->isCancelableByBuyer(),
            'created_at' => $this->created_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}
