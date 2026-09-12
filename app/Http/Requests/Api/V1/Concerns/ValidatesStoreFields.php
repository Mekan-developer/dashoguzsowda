<?php

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Общие правила магазина для создания (POST /v1/my/store) и правки
 * (PUT /v1/my/store). Разница между ними только в required/sometimes,
 * поэтому набор полей описан один раз.
 */
trait ValidatesStoreFields
{
    /** Суммарно (существующие + новые) фото галереи на один магазин. */
    protected const MAX_PHOTOS = 6;

    /** @param string $presence 'required' при создании, 'sometimes' при правке */
    protected function storeFieldRules(string $presence): array
    {
        $required = $presence === 'required' ? 'required' : 'sometimes';

        return [
            'name'        => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Телефон магазина обязателен: это единственный способ связи покупателя с владельцем
            'phone'       => [$required, 'string', 'regex:/^\+993\d{8}$/'],
            'address'     => ['sometimes', 'nullable', 'string', 'max:255'],

            // Регион и город обязательны, район — нет (есть не везде)
            'region_id'   => [$required, Rule::exists('regions', 'id')->where('is_hidden', 0)],
            'city_id'     => [$required, Rule::exists('cities', 'id')->where('is_hidden', 0)
                ->where('region_id', $this->input('region_id'))],
            'district_id' => ['sometimes', 'nullable', Rule::exists('districts', 'id')
                ->where('city_id', $this->input('city_id'))],

            'category_id' => ['sometimes', 'nullable', Rule::exists('categories', 'id')->where('is_active', 1)],

            'sells_retail'    => ['sometimes', 'boolean'],
            'sells_wholesale' => ['sometimes', 'boolean'],
            'has_delivery'    => ['sometimes', 'boolean'],

            // Чем у магазина можно расплатиться: набор из справочника админа,
            // покупатель выбирает при заказе только из него. Поле не required:
            // при создании без него магазин получает способ по умолчанию —
            // иначе старая версия мобилки перестала бы создавать магазины
            'payment_method_ids'   => ['sometimes', 'array'],
            'payment_method_ids.*' => [Rule::exists('payment_methods', 'id')->where('is_active', 1)],

            'logo'     => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'crop_x'   => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'crop_y'   => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'photos'   => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('messages.phone_format_invalid'),
        ];
    }

    /**
     * Магазин обязан торговать хотя бы как-то: если оба флага сняты, товар
     * негде показать — ни с розничной ценой, ни с оптовой.
     */
    protected function validateTradeFlags(Validator $validator, bool $retailDefault, bool $wholesaleDefault): void
    {
        $validator->after(function (Validator $v) use ($retailDefault, $wholesaleDefault) {
            $retail = $this->has('sells_retail')
                ? $this->boolean('sells_retail')
                : $retailDefault;

            $wholesale = $this->has('sells_wholesale')
                ? $this->boolean('sells_wholesale')
                : $wholesaleDefault;

            if (! $retail && ! $wholesale) {
                $v->errors()->add('sells_retail', __('messages.store_trade_type_required'));
            }
        });
    }

    /**
     * Способ оплаты у магазина обязан остаться хотя бы один: иначе покупателю
     * при оформлении не из чего выбрать. Пустой массив — именно попытка снять
     * все, её и отбиваем; не присланное поле означает «не трогаем набор».
     */
    protected function validatePaymentMethods(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->has('payment_method_ids') && $this->input('payment_method_ids') === []) {
                $v->errors()->add('payment_method_ids', __('messages.store_payment_method_required'));
            }
        });
    }
}
