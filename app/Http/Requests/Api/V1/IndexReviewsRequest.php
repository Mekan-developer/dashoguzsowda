<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** GET /v1/listings/{id}/reviews и /v1/users/{id}/reviews — публичная лента отзывов. */
class IndexReviewsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sort'  => ['nullable', 'in:latest,rating_desc,rating_asc'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
            'page'  => ['nullable', 'integer', 'min:1'],
        ];
    }
}
