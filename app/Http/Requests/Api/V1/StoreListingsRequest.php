<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreListingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'page'  => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}
