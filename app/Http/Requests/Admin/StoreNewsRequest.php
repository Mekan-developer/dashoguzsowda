<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title_ru'     => 'required|string|max:500',
            'title_tk'     => 'nullable|string|max:500',
            'content_ru'   => 'nullable|string',
            'content_tk'   => 'nullable|string',
            'type'         => 'required|in:regular,ad',
            'ad_link_type' => 'required_if:type,ad|nullable|in:profile,listing,product',
            'ad_link_id'   => 'required_if:type,ad|nullable|integer',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
            'crop_x'       => 'nullable|numeric|between:0,100',
            'crop_y'       => 'nullable|numeric|between:0,100',
            'remove_image' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
