<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Фильтры раздела «Заказы»: одни и те же для списка заказов, сводки сверху и
 * вкладки «Покупатели» — цифры в них всегда про один и тот же набор заказов.
 */
class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'view'     => ['nullable', 'in:orders,buyers'],
            'status'   => ['nullable', 'in:pending,approved,completed,rejected,canceled'],
            'search'   => ['nullable', 'string', 'max:100'],
            'store_id' => ['nullable', 'integer'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'sort'     => ['nullable', 'in:desc,asc'],
        ];
    }
}
