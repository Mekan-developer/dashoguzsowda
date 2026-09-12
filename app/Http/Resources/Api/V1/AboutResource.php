<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Страница «О нас» для мобильного приложения.
 *
 * Содержимое — HTML из визуального редактора админки, уже очищенный при
 * сохранении: остаются только p, br, strong/em/s/u, h2/h3, списки, blockquote
 * и ссылки. Рисовать его нужно html-виджетом, обычный Text покажет теги.
 *
 * Отдаются обе версии плюс `content` — та, что соответствует Accept-Language:
 * ровно как subtitle у магазина. Если на языке пользователя текста нет,
 * в `content` подставляется вторая версия, иначе экран был бы пустым.
 */
class AboutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tk = $this->resource['content_tk'] ?? null;
        $ru = $this->resource['content_ru'] ?? null;

        return [
            'content_tk' => $tk,
            'content_ru' => $ru,
            'content'    => app()->getLocale() === 'tk' ? ($tk ?: $ru) : ($ru ?: $tk),
        ];
    }
}
