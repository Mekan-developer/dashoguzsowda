<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Текущий пользователь в props каждой страницы админки.
 *
 * Раньше сюда уходила сырая модель: password и remember_token скрывал $hidden,
 * но note, blocked_reason, fcm_token, tariff_ends_at и phone попадали в HTML
 * любой страницы. Фронту нужны только эти четыре поля
 * (AppLayout.vue — name/role/locale, Videos/Index.vue — role).
 */
class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'role'   => $this->role,
            'locale' => $this->locale,
        ];
    }
}
