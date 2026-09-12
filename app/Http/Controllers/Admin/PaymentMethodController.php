<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DeletePaymentMethodAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;

/**
 * Чем покупатель может рассчитаться с продавцом. Онлайн-оплаты в проекте нет —
 * это список договорённостей, из которого магазин отмечает свои.
 *
 * Собственной страницы у справочника нет: список приезжает в пропах
 * Settings/Index.vue, отсюда — только запись (как у справочников причин).
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethods,
    ) {}

    public function store(StorePaymentMethodRequest $request)
    {
        $this->paymentMethods->create($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $this->paymentMethods->update($paymentMethod, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(PaymentMethod $paymentMethod, DeletePaymentMethodAction $deletePaymentMethod)
    {
        $deletePaymentMethod->execute($paymentMethod);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
