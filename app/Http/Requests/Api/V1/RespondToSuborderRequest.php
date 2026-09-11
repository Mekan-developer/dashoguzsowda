<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /v1/my/store/orders/{suborder}/accept|decline — ответ владельца магазина.
 * Комментарий необязателен, но при отказе его увидит покупатель: он уходит
 * в decision_comment заказа и в push.
 */
class RespondToSuborderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
