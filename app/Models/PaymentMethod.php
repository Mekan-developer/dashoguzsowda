<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Способ оплаты заказа: чем покупатель рассчитается с продавцом на месте.
 *
 * Справочник ведёт админ (как причины отклонения и жалоб), магазин отмечает
 * из него свои, покупатель выбирает при оформлении из набора магазина.
 * Онлайн-оплаты за этим нет — деньги идут мимо системы.
 */
class PaymentMethod extends Model
{
    protected $fillable = ['name_ru', 'name_tk', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stores() { return $this->belongsToMany(Store::class); }
}
