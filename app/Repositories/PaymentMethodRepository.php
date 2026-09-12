<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function all(): Collection
    {
        return PaymentMethod::orderBy('sort_order')->orderBy('id')->get();
    }

    public function active(): Collection
    {
        return PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function defaultId(): ?int
    {
        return PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
    }

    public function create(array $data): PaymentMethod
    {
        // Новый способ встаёт в конец списка: порядок задан только первым
        // набором, дальше админ добавляет по мере появления договорённостей
        $data['sort_order'] ??= (int) PaymentMethod::max('sort_order') + 1;

        return PaymentMethod::create($data);
    }

    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        $method->update($data);

        return $method->fresh();
    }

    public function delete(PaymentMethod $method): void
    {
        $method->delete();
    }

    public function countOrders(PaymentMethod $method): int
    {
        return Order::where('payment_method_id', $method->id)->count();
    }
}
