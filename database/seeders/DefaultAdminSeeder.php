<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => env('ADMIN_EMAIL', 'admin@teste.com')], [
            'name' => 'DevQuest Admin',
            'password' => env('ADMIN_PASSWORD', 'password'),
            'role' => UserType::ADMIN,
        ]);
    }
}
