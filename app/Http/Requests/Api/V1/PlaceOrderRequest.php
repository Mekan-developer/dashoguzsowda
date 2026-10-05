<?php

namespace App\Http\Requests\Api\V1;

use App\Actions\PlaceOrderAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /v1/orders — корзина уезжает на сервер целиком.
 *
 * Здесь только форма запроса: доступность товара, доставку, остатки и цены
 * проверяет PlaceOrderAction — это правила предметной области, а не формы.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1', 'max:'.PlaceOrderAction::MAX_ITEMS],
            'items.*.listing_id'   => ['required', 'integer', 'exists:listings,id'],
            'items.*.qty'          => ['required', 'integer', 'min:1', 'max:9999'],

            // Контакты получателя: пусто — берём из профиля покупателя
            'contact_name'         => ['nullable', 'string', 'max:255'],
            'phone'                => ['nullable', 'string', 'max:32'],
            'region_id'            => ['nullable', 'integer', 'exists:regions,id'],
            'city_id'              => ['nullable', 'integer', 'exists:cities,id'],
            'district_id'          => ['nullable', 'integer', 'exists:districts,id'],
            // Адрес обязателен всегда: заказ существует только с доставкой
            'address'              => ['required', 'string', 'max:500'],
            // Геолокация точки доставки с телефона — продавцу, чтобы найти,
            // куда везти. Необязательна (доступ к GPS могут не дать), но
            // приходит только парой: одна координата без второй бесполезна
            'lat'                  => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng'                  => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'comment'              => ['nullable', 'string', 'max:1000'],

            // Чем покупатель рассчитается. Необязательно: пусто — договорятся
            // по телефону, как было до появления справочника. Принимает ли
            // магазин этот способ, проверяет PlaceOrderAction: это правило
            // предметной области, а не формы
            'payment_method_id'    => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', 1)],
        ];
    }
}
