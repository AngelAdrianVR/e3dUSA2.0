<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IncidentType;

class IncidentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $incidentTypes = [
            ['name' => 'Falta injustificada', 'code' => 'F_INJ'],
            ['name' => 'Falta justificada', 'code' => 'F_JUS'],
            ['name' => 'Permiso con goce', 'code' => 'P_C_G'],
            ['name' => 'Permiso sin goce', 'code' => 'P_S_G'],
            ['name' => 'Incapacidad general', 'code' => 'INC_GEN'],
            ['name' => 'Incapacidad por trabajo', 'code' => 'INC_TRA'],
            ['name' => 'Vacaciones', 'code' => 'VAC'],
            ['name' => 'Día festivo', 'code' => 'D_FES'],
            ['name' => 'Descanso', 'code' => 'DESC'],
        ];

        foreach ($incidentTypes as $type) {
            IncidentType::create($type);
        }
    }
}