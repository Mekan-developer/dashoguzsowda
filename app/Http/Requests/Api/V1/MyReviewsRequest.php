<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** GET /v1/reviews/my — свои отзывы во всех статусах модерации. */
class MyReviewsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'limit'  => ['nullable', 'integer', 'between:1,50'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}
