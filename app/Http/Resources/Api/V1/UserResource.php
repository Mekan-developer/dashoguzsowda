<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * @param array{is_premium: bool, tariff: array|null, store: array|null, stats: array}|null $summary
     *        Сводка store/tariff/stats из UserService::profileSummary() (mobile_docs/BACKEND_API.md §2).
     *        Опциональна: там, где сводка не нужна (например AuthController::verify),
     *        передавать её незачем — тогда поля уходят с безопасными дефолтами.
     */
    public function __construct($resource, private readonly ?array $summary = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'phone'      => $this->phone,
            'name'       => $this->name,
            'gender'     => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'avatar'     => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            // Плоские ID — их читает мобильное приложение (mobile_docs/BACKEND_API.md);
            // вложенные объекты ниже нужны, чтобы показать названия без второго запроса.
            'region_id'   => $this->region_id,
            'city_id'     => $this->city_id,
            'district_id' => $this->district_id,
            // Роутинг splash/OTP в мобильном приложении: false → /register
            'is_profile_complete' => $this->resource->isProfileComplete(),
            'is_premium'   => $this->summary['is_premium'] ?? false,
            'store'        => $this->summary['store'] ?? null,
            'tariff'       => $this->summary['tariff'] ?? null,
            'subscription' => $this->summary['tariff'] ?? null, // алиас — мобилка читает любое из двух
            'stats'        => $this->summary['stats'] ?? null,
            'region'     => $this->whenLoaded('region', fn () => [
                'id'      => $this->region->id,
                'name_tk' => $this->region->name_tk,
                'name_ru' => $this->region->name_ru,
            ]),
            'city'       => $this->whenLoaded('city', fn () => [
                'id'      => $this->city->id,
                'name_tk' => $this->city->name_tk,
                'name_ru' => $this->city->name_ru,
            ]),
            'district'   => $this->whenLoaded('district', fn () => $this->district ? [
                'id'      => $this->district->id,
                'name_tk' => $this->district->name_tk,
                'name_ru' => $this->district->name_ru,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
