<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyMachines extends Command
{
    /**
     * The name and signature of the console command.
     * Puede ir en cualquier orden
     * @var string
     */
    protected $signature = 'app:migrate-legacy-machines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de máquinas, refacciones y mantenimientos desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Máquinas, Refacciones y Mantenimientos...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "machines", "spare_parts" y "maintenances" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('maintenances')->truncate();
                DB::table('spare_parts')->truncate();
                DB::table('machines')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas "machines", "spare_parts" y "maintenances" han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            // Conexiones a las bases de datos definidas en config/database.php
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql'); // Conexión por defecto

            // Usamos una única transacción para asegurar la integridad de todos los datos.
            // Si algo falla, se revierte toda la migración.
            $newDb->transaction(function () use ($oldDb, $newDb) {
                
                // --- 1. Migrar la tabla `machines` ---
                $this->line('');
                $this->info('Migrando máquinas...');
                $old_machines = $oldDb->table('machines')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_machines->count());

                foreach ($old_machines as $machine) {
                    $newDb->table('machines')->insert([
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'serial_number' => $machine->serial_number,
                        'weight' => $machine->weight,
                        'width' => $machine->width,
                        'large' => $machine->large,
                        'height' => $machine->height,
                        'cost' => $machine->cost,
                        'supplier' => $machine->supplier,
                        'adquisition_date' => $machine->aquisition_date, // Corregido el typo de la columna
                        'days_next_maintenance' => $machine->days_next_maintenance,
                        'created_at' => $machine->created_at,
                        'updated_at' => $machine->updated_at,
                    ]);
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Máquinas migradas con éxito.');

                // --- 2. Migrar la tabla `spare_parts` ---
                $this->line('');
                $this->info('Migrando refacciones...');
                $old_spare_parts = $oldDb->table('spare_parts')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_spare_parts->count());

                foreach ($old_spare_parts as $part) {
                    $newDb->table('spare_parts')->insert([
                        'id' => $part->id,
                        'name' => $part->name,
                        'quantity' => $part->quantity,
                        'supplier' => $part->supplier,
                        'cost' => $part->cost,
                        'description' => $part->description,
                        'location' => $part->location,
                        'machine_id' => $part->machine_id,
                        'created_at' => $part->created_at,
                        'updated_at' => $part->updated_at,
                    ]);
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Refacciones migradas con éxito.');

                // --- 3. Migrar la tabla `maintenances` ---
                $this->line('');
                $this->info('Migrando mantenimientos...');
                $old_maintenances = $oldDb->table('maintenances')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_maintenances->count());
                
                foreach ($old_maintenances as $maintenance) {
                    $newDb->table('maintenances')->insert([
                        'id' => $maintenance->id,
                        'problems' => $maintenance->problems,
                        'actions' => $maintenance->actions,
                        'cost' => $maintenance->cost,
                        'maintenance_type' => $this->mapMaintenanceType($maintenance->maintenance_type_id),
                        'responsible' => $maintenance->responsible,
                        'maintenance_date' => $maintenance->start_date, // Mapeo de start_date a maintenance_date
                        'validated_by' => $maintenance->validated_by,
                        'spare_parts_used' => null, // Campo nuevo, se establece como nulo
                        'validated_at' => $maintenance->validated_at,
                        'machine_id' => $maintenance->machine_id,
                        'created_at' => $maintenance->created_at,
                        'updated_at' => $maintenance->updated_at,
                    ]);
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Mantenimientos migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los datos han sido transferidos a la nueva base de datos.");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de máquinas y mantenimientos: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Mapea el tipo de mantenimiento del valor antiguo (probablemente un ID) al nuevo (string).
     *
     * @param mixed $oldTypeId
     * @return string
     */
    private function mapMaintenanceType(mixed $oldTypeId): string
    {
        // --- IMPORTANTE ---
        // Asumo que los valores en la base de datos antigua son 1, 2 y 3.
        // Si usabas strings como 'preventive', 'corrective', etc.,
        // ajusta los casos del 'match' a esos strings.
        // Ejemplo: 'preventive' => 'Preventive',
        return match ((string) $oldTypeId) {
            '1' => 'Preventive',
            '2' => 'Corrective',
            '3' => 'Cleaning',
            // También puedes mapear si los valores son strings
            'preventive', 'PREVENTIVE' => 'Preventive',
            'corrective', 'CORRECTIVE' => 'Corrective',
            'cleaning', 'CLEANING' => 'Cleaning',
            default => 'Unknown', // Un valor por defecto por si hay datos inesperados
        };
    }
}
