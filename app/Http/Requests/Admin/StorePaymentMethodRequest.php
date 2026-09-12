<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Справочник способов оплаты — создание. Тот же набор полей, что у причин
 * отклонения и жалоб: название на двух языках плюс выключатель.
 */
class StorePaymentMethodRequest extends FormRequest
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
