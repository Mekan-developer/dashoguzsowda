<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesRootCategory;
use Illuminate\Foundation\Http\FormRequest;

class SearchVideosRequest extends FormRequest
{
    use ValidatesRootCategory;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return array_merge([
            'search' => ['nullable', 'string', 'max:100'],
            'tag'    => ['nullable', 'string', 'max:30'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ], $this->rootCategoryRules('nullable'));
    }

    public function messages(): array
    {
        return $this->rootCategoryMessages();
    }
}
