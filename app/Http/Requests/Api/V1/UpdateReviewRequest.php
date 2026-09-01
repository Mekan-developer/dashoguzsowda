<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Объект отзыва (listing_id / target_user_id) не меняется — иначе отзыв
     * переехал бы на чужую карточку. Правится только то, что написал автор.
     */
    public function rules(): array
    {
        return [
            'text'   => ['required', 'string', 'max:2000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ];
    }
}
