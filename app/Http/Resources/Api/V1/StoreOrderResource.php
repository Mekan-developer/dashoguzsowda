<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ глазами владельца магазина: только его позиции и его сумма.
 *
 * Приходит сразу после оформления: владелец подтверждает наличие первым,
 * админ подтверждает заказ уже по его ответу.
 *
 * Контакты и адрес покупателя здесь намеренно не отдаются — доставкой
 * занимается платформа, владелец собирает товар и передаёт его админу.
 */
class StoreOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->number),
            // Статус всего заказа: pending | approved | completed | rejected | canceled
            'order_status' => $this->whenLoaded('order', fn () => $this->order?->status),
            // Ответ владельца: pending — ждём его, accepted | declined — уже ответил
            'status'       => $this->status,
            'subtotal'     => (float) $this->subtotal,
            // Комиссия платформы: ставка магазина на момент заказа, её сумма
            // и то, что остаётся магазину. Покупатель платит subtotal целиком —
            // комиссия удерживается с магазина, а не добавляется к цене
            'commission_percent' => (float) $this->commission_percent,
            'commission'         => (float) $this->commission_total,
            'payout'             => $this->payout,
            'comment'      => $this->comment,
            'can_respond'  => $this->isAwaitingOwner(),
            'items'        => StoreOrderItemResource::collection($this->whenLoaded('items')),
            'created_at'   => $this->created_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
        ];
    }
}
