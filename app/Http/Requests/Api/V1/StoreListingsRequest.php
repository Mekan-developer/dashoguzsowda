<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreListingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'page'        => ['nullable', 'integer', 'min:1'],
            'limit'       => ['nullable', 'integer', 'between:1,50'],
            'search'      => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            // trade=wholesale — только позиции с оптовой ценой, retail — с розничной
            'trade'       => ['nullable', 'in:retail,wholesale'],
            // in_stock=1 прячет товары, помеченные владельцем как закончившиеся
            'in_stock'    => ['nullable', 'boolean'],
            'sort'        => ['nullable', 'in:latest,price_asc,price_desc'],
        ];
    }
}
