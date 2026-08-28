<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Справочник причин отклонения. Один класс на create и update: набор полей
 * одинаковый. На update валидации раньше не было вовсе — можно было записать
 * пустые названия и type вне enum.
 */
class StoreRejectionReasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'   => ['required', 'string', 'max:255'],
            'name_tk'   => ['required', 'string', 'max:255'],
            'type'      => ['required', 'in:listing,video,review,store'],
            'is_active' => ['boolean'],
        ];
    }
}
