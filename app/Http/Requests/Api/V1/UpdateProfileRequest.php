<?php

namespace App\Http\Requests\Api\V1;

use App\Models\City;
use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Частичное обновление профиля: клиент шлёт только изменяемые поля.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender'      => ['sometimes', 'nullable', 'in:male,female'],
            'birth_date'  => ['sometimes', 'nullable', 'date', 'before:today'],
            // 0/1 вместо false/true: sqlite биндит false как '' и не матчит 0
            'region_id'   => ['sometimes', 'nullable', Rule::exists('regions', 'id')->where('is_hidden', 0)],
            'city_id'     => ['sometimes', 'nullable', Rule::exists('cities', 'id')->where('is_hidden', 0)],
            'district_id' => ['sometimes', 'nullable', Rule::exists('districts', 'id')->where('is_hidden', 0)],
            // Вложенный магазин (mobile_docs/BACKEND_API.md §2) — только для тарифов
            // с can_have_store, проверяется ниже в withValidator().
            //
            // Оставлен для обратной совместимости с текущей мобилкой; логотип,
            // галерея и полный набор торговых настроек — на /v1/my/store.
            // Телефон и адрес здесь остаются мягкими: старый клиент шлёт этот
            // блок без них, и ронять ему сохранение профиля нельзя.
            'store'                 => ['sometimes', 'array'],
            'store.name'            => ['required_with:store', 'string', 'max:255'],
            'store.description'     => ['sometimes', 'nullable', 'string', 'max:2000'],
            'store.phone'           => ['sometimes', 'nullable', 'string', 'max:32'],
            'store.address'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'store.category_id'     => ['sometimes', 'nullable', Rule::exists('categories', 'id')->where('is_active', 1)],
            'store.region_id'       => ['sometimes', 'nullable', Rule::exists('regions', 'id')->where('is_hidden', 0)],
            'store.city_id'         => ['sometimes', 'nullable', Rule::exists('cities', 'id')->where('is_hidden', 0)],
            'store.district_id'     => ['sometimes', 'nullable', 'exists:districts,id'],
            'store.sells_retail'    => ['sometimes', 'boolean'],
            'store.sells_wholesale' => ['sometimes', 'boolean'],
            'store.has_delivery'    => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Регион → город → район должны быть согласованы между собой.
     *
     * Обновление частичное, поэтому недостающие звенья берутся из текущего
     * профиля: смена одного только region_id обязана сделать невалидным город,
     * оставшийся от прежнего региона.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            $regionId   = $this->has('region_id')   ? $this->input('region_id')   : $user->region_id;
            $cityId     = $this->has('city_id')     ? $this->input('city_id')     : $user->city_id;
            $districtId = $this->has('district_id') ? $this->input('district_id') : $user->district_id;

            if ($cityId && ! City::whereKey($cityId)->where('region_id', $regionId)->exists()) {
                $v->errors()->add('city_id', __('messages.city_not_in_region'));

                return;
            }

            if ($districtId && ! District::whereKey($districtId)->where('city_id', $cityId)->exists()) {
                $v->errors()->add('district_id', __('messages.district_not_in_city'));

                return;
            }

            if ($this->has('store') && ! $user->activeTariff()?->canHaveStore()) {
                $v->errors()->add('store', __('messages.store_requires_premium_tariff'));
            }
        });
    }
}
