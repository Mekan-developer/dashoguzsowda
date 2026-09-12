<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка способа оплаты. `sometimes` — как у причин: страница настроек
 * переключает is_active отдельным запросом, без остальных полей.
 */
class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'   => ['sometimes', 'required', 'string', 'max:255'],
            'name_tk'   => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
