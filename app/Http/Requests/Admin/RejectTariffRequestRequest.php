<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Отказ по заявке на тариф. Причина — свободный текст, а не справочник:
 * тут не модерация контента, а расчёты («деньги не поступили», «внесена
 * другая сумма»), формулировки заранее не перечислить.
 */
class RejectTariffRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:500'],
        ];
    }
}
