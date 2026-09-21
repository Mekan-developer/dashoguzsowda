<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStoreRequest extends FormRequest
{
    private const MAX_PHOTOS = 6;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('commission_percent')) {
            $this->merge(['commission_percent' => 0]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id'     => ['required', Rule::exists('users', 'id')->where('role', 'user')],
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'phone'       => 'nullable|string|max:32',
            'address'     => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'region_id'   => 'nullable|exists:regions,id',
            'city_id'     => 'nullable|exists:cities,id',
            'district_id' => 'nullable|exists:districts,id',
            'sells_retail'    => 'boolean',
            'sells_wholesale' => 'boolean',
            'has_delivery'    => 'boolean',
            'payment_method_ids'   => ['sometimes', 'array', 'min:1'],
            'payment_method_ids.*' => [Rule::exists('payment_methods', 'id')->where('is_active', 1)],
            'commission_percent' => 'required|numeric|between:0,100',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
            'crop_x'      => 'nullable|numeric|between:0,100',
            'crop_y'      => 'nullable|numeric|between:0,100',
            'photos'      => 'nullable|array|max:'.self::MAX_PHOTOS,
            'photos.*'    => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $retail = $this->boolean('sells_retail', true);
            $wholesale = $this->boolean('sells_wholesale', false);

            if (! $retail && ! $wholesale) {
                $v->errors()->add('sells_retail', __('messages.store_trade_type_required'));
            }
        });
    }
}
