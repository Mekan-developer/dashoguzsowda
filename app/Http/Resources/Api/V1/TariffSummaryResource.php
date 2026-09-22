<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Плоский Resource для каталога тарифов (GET /v1/tariffs) — заворачивает не Eloquent-модель,
 * а массив из TariffService::catalogEntries() (mobile_docs/BACKEND_API.md §3).
 */
class TariffSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'         => $this->resource['name'],
            'price'        => (float) $this->resource['price'],
            'can_have_store'    => (bool) $this->resource['can_have_store'],
            'can_see_wholesale' => (bool) ($this->resource['can_see_wholesale'] ?? false),
            'ads_limit'         => (int) $this->resource['ads_limit'],
            'ads_used'     => (int) $this->resource['ads_used'],
            'videos_limit' => (int) $this->resource['videos_limit'],
            'videos_used'  => (int) $this->resource['videos_used'],
            'boosts_limit' => (int) $this->resource['boosts_limit'],
            'days_left'    => (int) $this->resource['days_left'],
        ];
    }
}
