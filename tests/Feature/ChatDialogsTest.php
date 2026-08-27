<?php

use App\Models\Message;
use App\Models\User;
use App\Repositories\Interfaces\ChatRepositoryInterface;

/** Диалог с самым свежим сообщением должен быть первым (регрессия В-8). */
it('sorts dialogs by the latest message, not by user updated_at', function () {
    $old   = User::factory()->create();
    $fresh = User::factory()->create();

    Message::create([
        'user_id' => $fresh->id, 'sender' => 'user',
        'text' => 'Свежее', 'is_read' => false,
        'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute(),
    ]);

    Message::create([
        'user_id' => $old->id, 'sender' => 'user',
        'text' => 'Старое', 'is_read' => false,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    // Профиль «старого» собеседника трогали позже всех — по прежней сортировке
    // (users.updated_at) он бы всплыл наверх, хотя писал сутки назад.
    $old->forceFill(['updated_at' => now()])->saveQuietly();

    $dialogs = app(ChatRepositoryInterface::class)->getDialogs();

    expect($dialogs->pluck('id')->all())->toBe([$fresh->id, $old->id]);
});

it('counts only unread messages from the user side', function () {
    $user = User::factory()->create();

    Message::create(['user_id' => $user->id, 'sender' => 'user',  'text' => 'a', 'is_read' => false]);
    Message::create(['user_id' => $user->id, 'sender' => 'user',  'text' => 'b', 'is_read' => true]);
    Message::create(['user_id' => $user->id, 'sender' => 'admin', 'text' => 'c', 'is_read' => false]);

    $dialog = app(ChatRepositoryInterface::class)->getDialogs()->first();

    expect($dialog->unread_count)->toBe(1);
});
