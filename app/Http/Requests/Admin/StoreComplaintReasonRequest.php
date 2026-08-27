<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Справочник причин жалоб. Один класс на create и update — на update
 * валидации раньше не было, пустые названия проходили в БД.
 */
class StoreComplaintReasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'   => ['required', 'string', 'max:255'],
            'name_tk'   => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
