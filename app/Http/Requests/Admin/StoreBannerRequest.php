<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $isUpdate = $this->route('banner') !== null;

        return [
            'title_ru'   => 'required|string|max:255',
            'title_tk'   => 'nullable|string|max:255',
            'image'      => [$isUpdate ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'crop_x'     => 'nullable|numeric|between:0,100',
            'crop_y'     => 'nullable|numeric|between:0,100',
            'link_type'  => 'nullable|in:url,listing',
            'link_url'   => 'required_if:link_type,url|nullable|url|max:2048',
            'listing_id' => 'required_if:link_type,listing|nullable|integer|exists:listings,id',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
            'is_active'  => 'boolean',
        ];
    }
}
