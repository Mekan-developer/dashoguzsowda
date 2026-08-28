<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'     => 'required|string|max:255',
            'name_tk'     => 'nullable|string|max:255',
            'parent_id'   => 'nullable|integer|exists:categories,id',
            'is_active'   => 'boolean',
            'icon'        => 'nullable|file|mimes:svg|max:1024',
            'icon_path'   => 'nullable|string|exists:category_icons,path',
            // Изображение — только для корневых категорий (нет parent_id)
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360', Rule::prohibitedIf(fn () => $this->filled('parent_id'))],
            'crop_x'      => 'nullable|numeric|between:0,100',
            'crop_y'      => 'nullable|numeric|between:0,100',
        ];
    }

    public function messages(): array
    {
        return [
            'image.prohibited' => __('messages.category_image_root_only'),
        ];
    }
}
