<?php

namespace App\Http\Requests\Api\V1;

use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SearchListingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:100'],
            // Раздел, в котором находится клиент (вся ветка)
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            // Отмеченные подкатегории внутри раздела category_id, каждая — с поддеревом
            'category_ids'   => ['nullable', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'region_id'   => ['nullable', 'integer', 'exists:regions,id'],
            'city_id'     => ['nullable', 'integer', 'exists:cities,id'],
            'type'        => ['nullable', 'in:goods,services'],
            'price_min'   => ['nullable', 'numeric', 'min:0'],
            'price_max'   => ['nullable', 'numeric', 'min:0'],
            'sort'        => ['nullable', 'in:latest,price_asc,price_desc,nearest'],
            'lat'         => ['required_if:sort,nearest', 'nullable', 'numeric', 'between:-90,90'],
            'lng'         => ['required_if:sort,nearest', 'nullable', 'numeric', 'between:-180,180'],
            'limit'       => ['nullable', 'integer', 'between:1,50'],
            'page'        => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('category_id') || ! is_array($this->input('category_ids'))
                || $v->errors()->has('category_id') || $v->errors()->has('category_ids') || $v->errors()->has('category_ids.*')) {
                return;
            }

            // Подкатегория из чужого раздела — ошибка мобилки, а не пустая выдача
            $section = app(CategoryRepositoryInterface::class)->subtreeIds([(int) $this->input('category_id')]);

            foreach ($this->input('category_ids') as $i => $id) {
                if (! in_array((int) $id, $section, true)) {
                    $v->errors()->add("category_ids.$i", __('messages.category_outside_section'));
                }
            }
        });
    }
}
