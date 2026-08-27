<?php

use App\Models\User;

// К-2: заготовка Breeze позволяла любому создать аккаунт с email и паролем.
// Регистрация в системе одна — по SMS (POST /api/v1/auth/verify).

test('registration routes do not exist', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('register route is not registered by name', function () {
    expect(app('router')->getRoutes()->hasNamedRoute('register'))->toBeFalse();
});
