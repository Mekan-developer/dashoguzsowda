<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Частичное обновление профиля: клиент шлёт только изменяемые поля.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender'     => ['sometimes', 'nullable', 'in:male,female'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'region_id'  => ['sometimes', 'nullable', 'exists:regions,id'],
            'city_id'    => ['sometimes', 'nullable', 'exists:cities,id'],
        ];
    }
}
