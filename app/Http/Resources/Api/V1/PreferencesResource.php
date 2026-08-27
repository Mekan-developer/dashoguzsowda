<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreferencesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'onboarding_completed' => (bool) $this->onboarding_completed,
        ];
    }
}
