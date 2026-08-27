<?php

namespace App\Http\Requests\Admin;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Название района уникально в пределах города (unique-индекс city_id + name_ru).
 * Город берётся из route-параметра: на store — {city}, на update — из самого района.
 */
class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $district = $this->route('district');
        $cityId   = $district instanceof District
            ? $district->city_id
            : $this->route('city')?->id;

        return [
            'name_ru' => [
                'required', 'string', 'max:255',
                Rule::unique('districts')->where('city_id', $cityId)->ignore($district?->id),
            ],
            'name_tk' => ['required', 'string', 'max:255'],
        ];
    }
}
