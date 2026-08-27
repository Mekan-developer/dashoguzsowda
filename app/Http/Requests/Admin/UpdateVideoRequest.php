<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка ролика модератором: доступен только заголовок.
 * Без валидации пустой title уходил в NOT NULL-колонку и давал 500.
 */
class UpdateVideoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
