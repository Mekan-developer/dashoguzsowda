<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Свой магазин (GET/POST/PUT /v1/my/store) — публичная карточка плюс то, что
 * видит только владелец: статус модерации, причина отказа и признак того, что
 * витрина погашена из-за истёкшего тарифа.
 */
class MyStoreResource extends StoreResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),

            'status'    => $this->status,
            // false — тариф с can_have_store кончился: магазин сохранён,
            // но покупателям не показывается
            'is_active' => (bool) $this->is_active,
            'rejection_reason' => $this->when(
                $this->status === 'rejected',
                fn () => $this->rejectionReason ? [
                    'id'      => $this->rejectionReason->id,
                    'name_tk' => $this->rejectionReason->name_tk,
                    'name_ru' => $this->rejectionReason->name_ru,
                ] : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
