<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Только промодерированное: exists:listings,id принимал любой id,
            // и через GET /favorites наружу уходило чужое pending-объявление
            // целиком — с описанием, статусом и телефоном продавца,
            // хотя GET /listings/{id} на него отдаёт 404.
            'listing_id' => [
                'required', 'integer',
                Rule::exists('listings', 'id')->where('status', 'approved'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'listing_id.exists' => __('messages.favorite_listing_unavailable'),
        ];
    }
}
