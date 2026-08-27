<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTariffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name_ru'        => 'required|string|max:255',
            'name_tk'        => 'required|string|max:255',
            // Англ. slug для мобильного каталога (GET /v1/tariffs) — необязателен:
            // без него тариф просто не попадёт в мобильный каталог (см. миграцию 2026_08_27_000001).
            'name'           => ['nullable', 'string', 'alpha_dash', 'max:50', Rule::unique('tariffs', 'name')->ignore($this->route('tariff'))],
            'can_have_store' => 'boolean',
            'listings_limit' => 'required|integer|min:0',
            'videos_limit'   => 'required|integer|min:0',
            'boost_limit'    => 'required|integer|min:0',
            'duration_days'  => 'required|integer|min:1',
            'is_active'      => 'boolean',
            'is_free'        => 'boolean',
        ];
    }
}
