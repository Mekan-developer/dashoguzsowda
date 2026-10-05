<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MyVideosRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,approved,rejected,suspended'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}
