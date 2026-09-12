<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Страница «О нас» — текст на двух языках из визуального редактора.
 *
 * Оба поля nullable: язык можно оставить незаполненным, мобилка в этом случае
 * показывает вторую версию. Ограничение по длине — с запасом на разметку:
 * в БД это колонка TEXT (64 КБ), и упереться в неё формой нельзя.
 */
class UpdateAboutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'about_ru' => ['nullable', 'string', 'max:30000'],
            'about_tk' => ['nullable', 'string', 'max:30000'],
        ];
    }
}
