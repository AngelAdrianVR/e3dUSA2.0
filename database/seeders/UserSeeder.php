<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usamos updateOrCreate para evitar crear duplicados si el seeder se ejecuta varias veces.
            // Busca un usuario por email y lo actualiza, o lo crea si no existe.
            
            User::updateOrCreate(
                ['email' => 'desarrollo@emblemas3d.com'],
                [
                    'name' => 'Super admin',
                    'password' => Hash::make('321321321'), // ¡Cambia 'password' por una contraseña segura!
                ]
            );

            User::updateOrCreate(
                ['email' => 'j.sherman@emblemas3d.com'],
                [
                    'name' => 'Jorge Sherman',
                    'password' => Hash::make('e3d'),
                ]
            );

            User::updateOrCreate(
                ['email' => 'maribel@emblemas3d.com'],
                [
                    'name' => 'Maribel',
                    'password' => Hash::make('e3d'),
                ]
            );
    }
}
