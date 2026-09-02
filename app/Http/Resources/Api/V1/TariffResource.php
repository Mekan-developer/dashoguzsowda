<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TariffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name_tk'        => $this->name_tk,
            'name_ru'        => $this->name_ru,
            // Сумма, которую нужно передать админу наличными
            'price'          => (float) $this->price,
            'can_have_store' => (bool) $this->can_have_store,
            'listings_limit' => (int) $this->listings_limit,
            'videos_limit'   => (int) $this->videos_limit,
            'boost_limit'    => (int) $this->boost_limit,
            // null — бессрочный (бесплатный) тариф
            'duration_days'  => $this->duration_days !== null ? (int) $this->duration_days : null,
            'is_free'        => (bool) $this->is_free,
        ];
    }
}
