<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ глазами владельца магазина.
 *
 * Заказ приходит прямо ему и ведёт его он: подтверждает наличие, собирает,
 * везёт сам и получает деньги на месте. Поэтому здесь отдаются и контакты
 * покупателя с адресом доставки — без них заказ не выполнить
 * (см. CLAUDE.md → «Заказы и корзина»).
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
            // Свой ответ владельца: причина отказа
            'comment'      => $this->comment,
            // Принять или отказаться можно один раз, отметить доставленным —
            // только принятый заказ
            'can_respond'  => $this->isAwaitingOwner(),
            'can_complete' => $this->isDeliverable(),

            // Кому и куда везти. Пока заказ ждёт ответа, продавец видит их
            // тоже: по ним он и решает, берётся ли за доставку
            'buyer'    => $this->whenLoaded('order', fn () => $this->order ? [
                'name'  => $this->order->contact_name ?: $this->order->user?->name,
                'phone' => $this->order->phone,
            ] : null),
            'delivery' => $this->whenLoaded('order', fn () => $this->order ? [
                'address'  => $this->order->address,
                'region'   => $this->place($this->order->region),
                'city'     => $this->place($this->order->city),
                'district' => $this->place($this->order->district),
                // Пожелание покупателя к заказу: подъезд, время, «позвонить»
                'comment'  => $this->order->comment,
            ] : null),

            // Чем покупатель собирается рассчитаться — продавец везёт заказ
            // сам и деньги получает на месте, значит должен приехать готовым
            // (сдача, терминал). null — способ не выбран, решат по телефону
            'payment_method' => $this->whenLoaded('order', fn () => $this->order?->paymentMethod ? [
                'id'      => $this->order->paymentMethod->id,
                'name_tk' => $this->order->paymentMethod->name_tk,
                'name_ru' => $this->order->paymentMethod->name_ru,
            ] : null),

            'items'        => StoreOrderItemResource::collection($this->whenLoaded('items')),
            'created_at'   => $this->created_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
        ];
    }

    /** @return array{id: int, name_tk: string, name_ru: string}|null */
    private function place(mixed $place): ?array
    {
        return $place ? [
            'id'      => $place->id,
            'name_tk' => $place->name_tk,
            'name_ru' => $place->name_ru,
        ] : null;
    }
}
