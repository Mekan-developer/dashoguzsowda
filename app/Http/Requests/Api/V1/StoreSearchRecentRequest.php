<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSearchRecentRequest extends FormRequest
{
    /** Пустая строка и строка из одних пробелов отклоняются (422). */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('query'))) {
            $this->merge(['query' => trim($this->input('query'))]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:191'],
        ];
    }
}
