<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MyListingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,approved,rejected,suspended'],
            'limit'  => ['nullable', 'integer', 'between:1,50'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}
