<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title_ru'     => 'required|string|max:500',
            'title_tk'     => 'nullable|string|max:500',
            'content_ru'   => 'nullable|string',
            'content_tk'   => 'nullable|string',
            'type'         => 'required|in:regular,ad',
            'ad_link_type' => 'required_if:type,ad|nullable|in:store,listing,product',
            'ad_link_id'   => ['required_if:type,ad', 'nullable', 'integer', ...$this->adLinkTargetRules()],
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
            'crop_x'       => 'nullable|numeric|between:0,100',
            'crop_y'       => 'nullable|numeric|between:0,100',
            'remove_image' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Цель рекламной ссылки проверяется по фактической таблице: мобилка
     * открывает её теми же публичными эндпоинтами (`GET /v1/stores/{id}`,
     * `GET /v1/listings/{id}`), а они отдают 404 на непромодерированную или
     * погашенную сущность — без этой проверки админ сохранит ссылку в никуда.
     */
    private function adLinkTargetRules(): array
    {
        if ($this->input('type') !== 'ad') {
            return [];
        }

        return match ($this->input('ad_link_type')) {
            'store'   => [Rule::exists('stores', 'id')->where('status', 'approved')->where('is_active', true)],
            'listing' => [Rule::exists('listings', 'id')->where('status', 'approved')],
            // Товар — это объявление магазина (listings.store_id), таблицы products нет
            'product' => [Rule::exists('listings', 'id')->where('status', 'approved')->whereNotNull('store_id')],
            default   => [],
        };
    }

    public function messages(): array
    {
        return [
            'ad_link_id.exists' => match ($this->input('ad_link_type')) {
                'store'   => __('messages.news_ad_link_store_missing'),
                'product' => __('messages.news_ad_link_product_missing'),
                default   => __('messages.news_ad_link_listing_missing'),
            },
        ];
    }
}
