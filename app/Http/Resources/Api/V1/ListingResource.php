<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type,
            'price'       => $this->price !== null ? (float) $this->price : null,
            // Опт: цена за единицу при заказе от min_order_qty. Оба ценника
            // независимы — товар может продаваться и в розницу, и оптом,
            // мобилка показывает те плашки, у которых цена не null.
            'wholesale_price' => $this->wholesale_price !== null ? (float) $this->wholesale_price : null,
            'min_order_qty'   => $this->min_order_qty !== null ? (int) $this->min_order_qty : null,
            // null = «в наличии» (владелец не ведёт учёт), 0 = нет в наличии, N = N шт
            'stock_qty'       => $this->stock_qty !== null ? (int) $this->stock_qty : null,
            'phone'       => $this->phone,
            'tags'        => $this->tags ?? [],
            'location'    => $this->location,
            'status'      => $this->status,
            'rejection_reason' => $this->when(
                $this->status === 'rejected' && $this->relationLoaded('rejectionReason') && $this->rejectionReason,
                fn () => [
                    'id'      => $this->rejectionReason->id,
                    'name_tk' => $this->rejectionReason->name_tk,
                    'name_ru' => $this->rejectionReason->name_ru,
                ]
            ),
            // Сводка по одобренным отзывам: average = null, пока никто не поставил оценку.
            // Сами отзывы — отдельной страницей GET /v1/listings/{id}/reviews
            'rating'     => $this->when(
                array_key_exists('reviews_count', $this->getAttributes()),
                fn () => [
                    'average' => $this->getAttributes()['reviews_avg_rating'] !== null
                        ? round((float) $this->getAttributes()['reviews_avg_rating'], 2)
                        : null,
                    'count'   => (int) $this->getAttributes()['reviews_count'],
                ],
            ),
            'views'      => $this->views,
            'is_boosted' => (bool) $this->is_boosted,
            'boosted_at' => $this->boosted_at,
            // Присутствует только когда запрос сделан с Bearer-токеном (флаг считает репозиторий)
            'is_favorite' => $this->when(
                array_key_exists('is_favorite', $this->getAttributes()),
                fn () => (bool) $this->getAttributes()['is_favorite'],
            ),
            'category'   => $this->whenLoaded('category', fn () => [
                'id'        => $this->category->id,
                'parent_id' => $this->category->parent_id,
                'name_tk'   => $this->category->name_tk,
                'name_ru'   => $this->category->name_ru,
                // Путь от корня до выбранной подкатегории, напр. Оптом → Продукты питания → test
                'path'      => $this->categoryPath(),
            ]),
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
            // Товар магазина: мобилка по этому блоку рисует условия покупки
            // (опт/розница, доставка или самовывоз с оплатой на месте)
            'store'      => $this->whenLoaded('store', fn () => $this->store ? [
                'id'              => $this->store->id,
                'name'            => $this->store->name,
                'logo_url'        => $this->store->logo ? Storage::disk('public')->url($this->store->logo) : null,
                'sells_retail'    => (bool) $this->store->sells_retail,
                'sells_wholesale' => (bool) $this->store->sells_wholesale,
                'has_delivery'    => (bool) $this->store->has_delivery,
            ] : null),
            // Показывать ли кнопку «В корзину»: товар опубликованного магазина
            // с доставкой, в наличии и с ценой. Правило считает бэкенд, чтобы
            // мобилка не повторяла его у себя (CLAUDE.md → «Заказы»)
            'is_orderable' => $this->isOrderable(),
            'user'       => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar ? Storage::disk('public')->url($this->user->avatar) : null,
                // Рейтинг продавца грузится только для карточки объявления, не для ленты
                ...$this->sellerRating(),
            ]),
            // Конвертация в WebP идёт в фоновой очереди `media` (обычно 1-3 сек):
            // пока processing=true, все три ссылки указывают на загруженный оригинал
            'photos'     => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id'         => $m->id,
                'order'      => $m->order,
                'processing' => $m->medium_path === null,
                'original'   => Storage::disk('public')->url($m->path),
                'medium'     => Storage::disk('public')->url($m->medium_path ?? $m->path),
                'thumb'      => Storage::disk('public')->url($m->thumb_path ?? $m->path),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Рейтинг продавца по одобренным отзывам о нём (reviews.target_user_id).
     * Пустой массив, когда агрегаты не загружены — ключа `rating` тогда нет.
     */
    private function sellerRating(): array
    {
        $attributes = $this->user->getAttributes();

        if (! array_key_exists('reviews_count', $attributes)) {
            return [];
        }

        return ['rating' => [
            'average' => $attributes['reviews_avg_rating'] !== null
                ? round((float) $attributes['reviews_avg_rating'], 2)
                : null,
            'count'   => (int) $attributes['reviews_count'],
        ]];
    }

    /** Цепочка категорий от корня до листа (макс. 3 уровня — Category::MAX_LEVEL) */
    private function categoryPath(): array
    {
        $chain = [];
        $category = $this->category;

        while ($category) {
            $chain[] = [
                'id'      => $category->id,
                'name_tk' => $category->name_tk,
                'name_ru' => $category->name_ru,
            ];
            $category = $category->parent;
        }

        return array_reverse($chain);
    }
}
