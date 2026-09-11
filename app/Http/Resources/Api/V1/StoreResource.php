<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Публичная карточка магазина (mobile_docs/BACKEND_API.md §1).
 *
 * Отдаём только флаги и значения — «Розница», «Опт», «Самовывоз, оплата на
 * месте» и иконки рисует мобильное приложение из своих словарей, иначе смена
 * формулировки потребует релиза бэкенда.
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photos = $this->whenLoaded('photos', fn () => $this->photos
            ->map(fn ($photo) => Storage::disk('public')->url($photo->path))
            ->values());

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'phone'       => $this->phone,
            'subtitle_tk' => $this->whenLoaded('category', fn () => $this->category?->name_tk),
            'subtitle_ru' => $this->whenLoaded('category', fn () => $this->category?->name_ru),
            'subtitle'    => $this->whenLoaded('category', fn () => app()->getLocale() === 'tk'
                ? $this->category?->name_tk
                : $this->category?->name_ru),
            'category_id' => $this->category_id,

            // Вид торговли: флаги независимы, оба true = «оптом и в розницу».
            // Опт видит только розничный продавец: клиенту флаг приходит false
            'sells_retail'    => (bool) $this->sells_retail,
            'sells_wholesale' => (bool) $this->sells_wholesale && $this->resource->showsWholesaleTo($request->user('sanctum')),
            // false → покупатель забирает сам и платит на месте; цену и сроки
            // доставки стороны обсуждают по телефону, система их не считает
            'has_delivery'    => (bool) $this->has_delivery,

            'address'  => $this->address,
            'region'   => $this->whenLoaded('region', fn () => $this->region ? [
                'id'      => $this->region->id,
                'name_tk' => $this->region->name_tk,
                'name_ru' => $this->region->name_ru,
            ] : null),
            'city'     => $this->whenLoaded('city', fn () => $this->city ? [
                'id'      => $this->city->id,
                'name_tk' => $this->city->name_tk,
                'name_ru' => $this->city->name_ru,
            ] : null),
            'district' => $this->whenLoaded('district', fn () => $this->district ? [
                'id'      => $this->district->id,
                'name_tk' => $this->district->name_tk,
                'name_ru' => $this->district->name_ru,
            ] : null),

            'logo_url'   => $this->logo ? Storage::disk('public')->url($this->logo) : null,
            'photos'     => $photos,
            'photo_urls' => $photos,
        ];
    }
}
