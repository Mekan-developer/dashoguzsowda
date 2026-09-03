<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** GET /v1/orders — «Мои заказы» покупателя. */
class MyOrdersRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,approved,completed,rejected,canceled'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
