<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * forceCreate, а не create: role и status исключены из User::$fillable,
     * иначе оба аккаунта тихо создались бы с ролью 'user' по умолчанию.
     * Значения здесь захардкожены и не приходят из запроса.
     */
    public function run(): void
    {
        User::forceCreate([
            'phone'             => '+99312345678',
            'email'             => 'admin@gmail.com',
            'name'              => 'Администратор',
            'role'              => 'admin',
            'status'            => 'active',
            'phone_verified_at' => now(),
            'password'          => Hash::make('password'),
        ]);
        User::forceCreate([
            'phone'             => '+99361234567',
            'email'             => 'manager@gmail.com',
            'name'              => 'Менеджер',
            'role'              => 'manager',
            'status'            => 'active',
            'phone_verified_at' => now(),
            'password'          => Hash::make('password'),
        ]);
    }
}
