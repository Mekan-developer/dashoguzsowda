<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Категория 1-го уровня (корень дерева): parent_id IS NULL и активна.
 * Используется для роликов — в отличие от объявлений, где нужна конечная.
 */
trait ValidatesRootCategory
{
    /**
     * @param  string  $presence  'required' | 'sometimes' | 'nullable'
     * @return array<string, list<\Illuminate\Validation\Rules\Exists|string>>
     */
    protected function rootCategoryRules(string $presence = 'required'): array
    {
        $rules = [
            'integer',
            Rule::exists('categories', 'id')->where('is_active', 1)->whereNull('parent_id'),
        ];

        if ($presence === 'nullable') {
            array_unshift($rules, 'nullable');
        } elseif ($presence === 'sometimes') {
            array_unshift($rules, 'sometimes', 'required');
        } else {
            array_unshift($rules, 'required');
        }

        return ['category_id' => $rules];
    }

    /** @return array<string, string> */
    protected function rootCategoryMessages(): array
    {
        return [
            'category_id.exists' => __('messages.category_must_be_root'),
        ];
    }
}
