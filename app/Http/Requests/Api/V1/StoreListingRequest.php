<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTradeFields;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreListingRequest extends FormRequest
{
    use ValidatesTradeFields;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            ...$this->tradeFieldRules(),
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['required', 'string', 'max:5000'],
            'type'         => ['required', 'in:goods,services'],
            // 0/1 вместо false/true: sqlite биндит false как '' и не матчит 0
            'category_id'  => ['required', Rule::exists('categories', 'id')->where('is_active', 1)],
            'region_id'    => ['required', Rule::exists('regions', 'id')->where('is_hidden', 0)],
            'city_id'      => ['required', Rule::exists('cities', 'id')->where('is_hidden', 0)->where('region_id', $this->input('region_id'))],
            // Район опционален и есть не во всех городах; у объявления магазина
            // адрес всё равно перезапишется адресом магазина (ListingService)
            'district_id'  => ['nullable', Rule::exists('districts', 'id')->where('city_id', $this->input('city_id'))],
            'price'        => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            // Если не передан — подставляется телефон автора (в сервисе)
            'phone'        => ['nullable', 'string', 'regex:/^\+993\d{8}$/'],
            'tags'         => ['nullable', 'array', 'max:10'],
            'tags.*'       => ['string', 'max:30'],
            // Геолокация — только если пользователь включил её в приложении (ТЗ 5.9)
            'location'     => ['nullable', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'photos'       => ['required', 'array', 'min:1', 'max:8'],
            'photos.*'     => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('messages.phone_format_invalid'),
        ];
    }

    /** Категория должна быть конечной: у неё нет активных подкатегорий (ТЗ 5.2) */
    public function withValidator(Validator $validator): void
    {
        $this->validateWholesaleAllowed($validator);

        $validator->after(function (Validator $v) {
            if ($v->errors()->has('category_id')) {
                return;
            }

            $categoryId = (int) $this->input('category_id');

            if ($categoryId && Category::where('parent_id', $categoryId)->where('is_active', true)->exists()) {
                $v->errors()->add('category_id', __('messages.category_must_be_leaf'));
            }
        });
    }
}
