<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка объявления модератором. Раньше контроллер делал
 * $listing->update($request->only(...)) вообще без валидации: пустой title
 * упирался в NOT NULL и отдавал 500 с PDOException, а в price можно было
 * записать строку.
 *
 * Модератор правит только текст и цену — категория, регион и фотографии
 * меняются автором через мобильное приложение.
 */
class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price'       => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
