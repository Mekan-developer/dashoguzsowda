<?php

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Validation\Validator;

/**
 * Торговые поля объявления: оптовая цена, минимальная партия и остаток.
 * Общие для создания и правки объявления.
 *
 * Оптовая цена — привилегия магазина с включённым оптом: у обычного
 * пользователя её негде показать, а у розничного магазина она противоречит
 * его же карточке.
 */
trait ValidatesTradeFields
{
    protected function tradeFieldRules(): array
    {
        return [
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            // Минимальная партия обязательна вместе с оптовой ценой: «опт» без
            // объёма покупателю ничего не говорит
            'min_order_qty'   => ['nullable', 'integer', 'min:1', 'max:1000000', 'required_with:wholesale_price'],
            // null = учёт не ведётся («в наличии»), 0 = нет в наличии, N = N шт
            'stock_qty'       => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function validateWholesaleAllowed(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('wholesale_price')) {
                return;
            }

            $store = $this->user()?->store;

            if (! $store || ! $store->sells_wholesale) {
                $v->errors()->add('wholesale_price', __('messages.wholesale_requires_wholesale_store'));
            }
        });
    }
}
