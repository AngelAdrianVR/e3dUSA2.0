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
        // Crea 15 usuarios y asigna a cada uno sus detalles de empleado
        
        $this->call([
            UserSeeder::class,
        ]);

        // User::factory(4)->create()->each(function ($user) {
        //     $user->employeeDetail()->save(\App\Models\EmployeeDetail::factory()->make());
        // });

        $this->call([
            // UserSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            ProductFamilySeeder::class,
            IncidentTypeSeeder::class,
            PayrollSeeder::class,
            // AttendanceSeeder::class,
            // IncidentSeeder::class,
        ]);
    }
}
