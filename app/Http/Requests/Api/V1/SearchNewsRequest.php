<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SearchNewsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'  => ['nullable', 'in:regular,ad'],
            // Потолок обязателен: без него ?limit=1000000 выгружает всю таблицу
            // новостей вместе с картинками — и это публичный роут без авторизации.
            'limit' => ['nullable', 'integer', 'between:1,50'],
            'page'  => ['nullable', 'integer', 'min:1'],
        ];
    }
}
