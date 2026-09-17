<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Частичная правка объявления модератором (PATCH).
 * Текст, цена и категория — фото и геолокация правятся автором в мобилке.
 */
class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** Пустая цена из формы — «договорная», а не отсутствие поля. */
    protected function prepareForValidation(): void
    {
        if ($this->exists('price') && $this->input('price') === '') {
            $this->merge(['price' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price'       => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'category_id' => ['sometimes', 'required', Rule::exists('categories', 'id')->where('is_active', 1)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('category_id') || ! $this->filled('category_id')) {
                return;
            }

            $categoryId = (int) $this->input('category_id');
            if (Category::where('parent_id', $categoryId)->where('is_active', true)->exists()) {
                $v->errors()->add('category_id', __('messages.category_must_be_leaf'));
            }
        });
    }
}
