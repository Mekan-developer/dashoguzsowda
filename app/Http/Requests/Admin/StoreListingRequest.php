<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\V1\Concerns\ValidatesTradeFields;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreListingRequest extends FormRequest
{
    use ValidatesTradeFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('price') && $this->input('price') === '') {
            $this->merge(['price' => null]);
        }

        foreach (['wholesale_price', 'min_order_qty', 'stock_qty'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            ...$this->tradeFieldRules(),
            'user_id'      => ['required', Rule::exists('users', 'id')->where('role', 'user')],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['required', 'string', 'max:5000'],
            'type'         => ['required', 'in:goods,services'],
            'category_id'  => ['required', Rule::exists('categories', 'id')->where('is_active', 1)],
            'region_id'    => ['required', Rule::exists('regions', 'id')->where('is_hidden', 0)],
            'city_id'      => ['required', Rule::exists('cities', 'id')->where('is_hidden', 0)->where('region_id', $this->input('region_id'))],
            'district_id'  => ['nullable', Rule::exists('districts', 'id')->where('city_id', $this->input('city_id'))],
            'price'        => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'phone'        => ['nullable', 'string', 'regex:/^\+993\d{8}$/'],
            'tags'         => ['nullable', 'array', 'max:10'],
            'tags.*'       => ['string', 'max:30'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $v->errors()->has('category_id') && $this->filled('category_id')) {
                $categoryId = (int) $this->input('category_id');
                if (Category::where('parent_id', $categoryId)->where('is_active', true)->exists()) {
                    $v->errors()->add('category_id', __('messages.category_must_be_leaf'));
                }
            }

            if ($this->filled('wholesale_price') && ! $v->errors()->has('user_id')) {
                $owner = User::query()->find((int) $this->input('user_id'));
                $store = $owner?->store;
                if (! $store || ! $store->sells_wholesale) {
                    $v->errors()->add('wholesale_price', __('messages.wholesale_requires_wholesale_store'));
                }
            }
        });
    }
}
