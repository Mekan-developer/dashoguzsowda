<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** GET /v1/my/store/orders — заказы, пришедшие в магазин владельца. */
class StoreOrdersRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // to_deliver / completed — по статусу заказа: accepted+approved и уже отвезённые
            'status' => ['nullable', 'in:pending,accepted,declined,canceled,to_deliver,completed'],
            'q'      => ['nullable', 'string', 'max:100'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
