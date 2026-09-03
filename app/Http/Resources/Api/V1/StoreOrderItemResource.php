<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Позиция заказа глазами владельца магазина — то же, что видит покупатель,
 * плюс комиссия платформы с этой позиции и остаток, который получит магазин.
 *
 * Отдельный ресурс, а не поля в OrderItemResource: тот же ресурс отдаётся
 * покупателю, а комиссия — дело платформы и магазина, покупателя она не
 * касается (он платит ту сумму, которую видел в корзине).
 */
class StoreOrderItemResource extends OrderItemResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),

            'commission' => (float) $this->commission_amount,
            'payout'     => round((float) $this->total - (float) $this->commission_amount, 2),
        ];
    }
}
