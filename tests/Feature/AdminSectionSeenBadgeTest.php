<?php

use App\Models\User;

/**
 * Бейдж «новые пользователи» в сайдбаре: горит до тех пор, пока админ не
 * откроет раздел Пользователи, дальше считает только тех, кто
 * зарегистрировался ПОСЛЕ этого просмотра (admin_section_views).
 */
it('shows the new users badge until the admin opens the users section', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['role' => 'user']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('counts.newUsers', 1));

    // Открыл раздел — бейдж гаснет для этого админа
    $this->actingAs($admin)->get(route('users.index'));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('counts.newUsers', 0));

    // Новая регистрация после просмотра — бейдж снова загорается
    User::factory()->create(['role' => 'user']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('counts.newUsers', 1));
});

it('scopes the seen state per admin', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    User::factory()->create(['role' => 'user']);

    $this->actingAs($adminA)->get(route('users.index'));

    $this->actingAs($adminA)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('counts.newUsers', 0));

    $this->actingAs($adminB)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('counts.newUsers', 1));
});
