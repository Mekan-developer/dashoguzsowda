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
            }
        });
    }
}
