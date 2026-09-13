<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Usuario admin inicial. La contraseña se lee de ADMIN_PASSWORD para no fijar credenciales. */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'cambia-esto-ya')),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        )->assignRole('Admin');
    }
}
