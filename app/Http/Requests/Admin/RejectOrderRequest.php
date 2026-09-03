<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Отказ по заказу. Причина — свободный текст, а не справочник: это не модерация
 * контента, а результат обзвона («товара нет», «покупатель недоступен», «адрес
 * вне зоны доставки»), формулировки заранее не перечислить.
 *
 * Причина уходит покупателю в push и остаётся в карточке заказа.
 */
class RejectOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:500'],
        ];
    }
}
