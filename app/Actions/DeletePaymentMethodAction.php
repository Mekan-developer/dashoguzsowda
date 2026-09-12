<?php

namespace App\Actions;

use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Удаление способа оплаты из справочника.
 *
 * Способ, на который уже сослались заказы, не удаляется: в заказе он —
 * договорённость покупателя с продавцом, и стереть её задним числом нельзя.
 * Вывести способ из обращения нужно выключателем is_active — новые магазины
 * его не выберут, а старые заказы останутся читаемыми.
 */
class DeletePaymentMethodAction
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethods,
    ) {}

    public function execute(PaymentMethod $method): void
    {
        if ($this->paymentMethods->countOrders($method) > 0) {
            throw ValidationException::withMessages([
                'payment_method' => __('messages.payment_method_in_use'),
            ]);
        }

        $this->paymentMethods->delete($method);
    }
}
