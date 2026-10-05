<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTariffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Бесплатный тариф бессрочен — срок к нему не применяется, поэтому форма
     * его и не показывает, а пришедшее значение отбрасывается.
     * Он же всегда активен: на нём каждый клиент с регистрации и после
     * истечения платного (UserObserver, ExpireTariffsAction).
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_free')) {
            $this->merge(['duration_days' => null, 'is_active' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'name_ru'        => 'required|string|max:255',
            'name_tk'        => 'required|string|max:255',
            // Англ. slug для мобильного каталога (GET /v1/tariffs) — необязателен:
            // без него тариф просто не попадёт в мобильный каталог (см. миграцию 2026_08_27_000001).
            'name'           => ['nullable', 'string', 'alpha_dash', 'max:50', Rule::unique('tariffs', 'name')->ignore($this->route('tariff'))],
            'can_have_store'    => 'boolean',
            // Цена, которую админ принимает наличными; бесплатный тариф — 0
            'price'             => 'required|numeric|min:0|max:9999999',
            'listings_limit'    => 'required|integer|min:0',
            'videos_limit'      => 'required|integer|min:0',
            'boost_limit'       => 'required|integer|min:0',
            // null = бессрочно; допустимо только для бесплатного тарифа
            'duration_days'     => [Rule::requiredIf(fn () => ! $this->boolean('is_free')), 'nullable', 'integer', 'min:1'],
            'is_active'         => 'boolean',
            // Снять флаг с бесплатного нельзя — иначе клиентов некуда переводить.
            // Перенести можно: отметить бесплатным другой тариф (TariffService::update)
            'is_free'           => ['boolean', function (string $attribute, mixed $value, \Closure $fail) {
                if ($this->route('tariff')?->is_free && ! $this->boolean('is_free')) {
                    $fail(__('messages.tariff_free_flag_locked'));
                }
            }],
            'can_see_wholesale' => 'boolean',
        ];
    }
}
