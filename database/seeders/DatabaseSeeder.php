<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Llamar al seeder de usuarios
        $this->call(UserSeeder::class);
        
        // Llamar al seeder de permisos
        $this->call(PermissionSeeder::class);

        // Llamar al seeder de famila de productos
        $this->call(ProductFamilySeeder::class);
    }
}
