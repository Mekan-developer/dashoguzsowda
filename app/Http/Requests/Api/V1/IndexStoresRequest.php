<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** GET /v1/stores — публичный список магазинов с фильтрами. */
class IndexStoresRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search'       => ['nullable', 'string', 'max:100'],
            'region_id'    => ['nullable', 'integer', 'exists:regions,id'],
            'city_id'      => ['nullable', 'integer', 'exists:cities,id'],
            'district_id'  => ['nullable', 'integer', 'exists:districts,id'],
            'category_id'  => ['nullable', 'integer', 'exists:categories,id'],
            // retail показывает и магазины «оптом и в розницу» — флаги независимы
            'type'         => ['nullable', 'in:retail,wholesale'],
            'has_delivery' => ['nullable', 'boolean'],
            'limit'        => ['nullable', 'integer', 'between:1,50'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ];
    }
}
