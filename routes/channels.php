<?php

use Illuminate\Support\Facades\Broadcast;

// Мобильные клиенты (Sanctum bearer-токен) авторизуются на /api/broadcasting/auth;
// админка (сессия) — на дефолтный /broadcasting/auth, который регистрирует withRouting().
Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Один диалог пользователь ↔ админ: сам пользователь либо любой admin/manager.
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return in_array($user->role, ['admin', 'manager'], true) || (int) $user->id === (int) $userId;
});

// Канал админки: новые объявления на модерацию и прочие события для персонала.
Broadcast::channel('admin', function ($user) {
    return in_array($user->role, ['admin', 'manager'], true);
});
