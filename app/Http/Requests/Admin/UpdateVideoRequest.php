<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesRootCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка ролика модератором: заголовок и категория 1-го уровня.
 * Без валидации пустой title уходил в NOT NULL-колонку и давал 500.
 */
class UpdateVideoRequest extends FormRequest
{
    use ValidatesRootCategory;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return array_merge([
            'title'  => ['required', 'string', 'max:255'],
            'tags'   => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
        ], $this->rootCategoryRules('sometimes'));
    }

    public function messages(): array
    {
        return $this->rootCategoryMessages();
    }
}
