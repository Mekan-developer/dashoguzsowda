<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+993' . fake()->unique()->numerify('6#######'),
            // Пользователи приложения входят по SMS и email не имеют, но вход в
            // админку идёт по email+паролю — без него не протестировать /login.
            'email' => fake()->unique()->safeEmail(),
            'role' => 'user',
            'status' => 'active',
            // Пользователь в БД появляется только после успешного ввода SMS-кода
            // (AuthService::verify), так что «по умолчанию» он подтверждён.
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'admin']);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'manager']);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'blocked']);
    }
}
