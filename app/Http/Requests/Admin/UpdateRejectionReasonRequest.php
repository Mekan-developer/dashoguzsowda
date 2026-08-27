<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка причины отклонения. Правила с `sometimes`, потому что страница
 * настроек шлёт тумблер активности одним полем:
 * router.put(route('rejection-reasons.update', id), { is_active: !item.is_active })
 * — требовать здесь name_ru/name_tk/type значило бы сломать переключатель.
 *
 * При этом присланное пустым значение всё равно не пройдёт: `sometimes`
 * пропускает только отсутствующий ключ, а не пустую строку.
 */
class UpdateRejectionReasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'   => ['sometimes', 'required', 'string', 'max:255'],
            'name_tk'   => ['sometimes', 'required', 'string', 'max:255'],
            'type'      => ['sometimes', 'required', 'in:listing,video,review'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
