<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyDesigns extends Command
{
    /**
     * The name and signature of the console command.
     * N°7 - todo bien! hecho.
     * @var string
     */
    protected $signature = 'app:migrate-legacy-designs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra las órdenes de diseño y sus modificaciones desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Órdenes de Diseño...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "design_orders", "designs" y "design_assignment_logs" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('design_assignment_logs')->truncate();
                DB::table('design_orders')->truncate();
                DB::table('designs')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas de destino han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            // Conexiones a las bases de datos
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // Caché para los IDs de sucursales para no consultar la BD repetidamente por el mismo nombre
            $branchCache = [];

            // Usamos una única transacción para asegurar la integridad de todos los datos.
            $newDb->transaction(function () use ($oldDb, $newDb, &$branchCache) {

                // --- 1. Migrar diseños base y crear entradas en la nueva tabla `designs` ---
                $this->line('');
                $this->info('Migrando diseños originales como órdenes de diseño...');
                $old_designs = $oldDb->table('designs')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_designs->count());

                foreach ($old_designs as $old_design) {
                    // a) Creamos una entrada en la nueva tabla `designs` conservando el ID.
                    $newDb->table('designs')->insert([
                        'id' => $old_design->id,
                        'name' => $old_design->name,
                        'description' => 'Registro migrado desde el sistema antiguo.',
                        'design_category_id' => $old_design->design_type_id,
                        'original_design_id' => null, // Se puede actualizar en un paso posterior si es necesario
                        'created_at' => $old_design->created_at,
                        'updated_at' => $old_design->updated_at,
                    ]);

                    // --- INICIO: LÓGICA AGREGADA PARA BUSCAR BRANCH_ID ---
                    $branch_id = null;
                    $branchName = $old_design->company_branch_name;
                    if (!empty($branchName)) {
                        if (isset($branchCache[$branchName])) {
                            $branch_id = $branchCache[$branchName];
                        } else {
                            $branch = $newDb->table('branches')->where('name', $branchName)->first();
                            if ($branch) {
                                $branch_id = $branch->id;
                                $branchCache[$branchName] = $branch_id; // Guardar en caché
                            } else {
                                $branchCache[$branchName] = null; // Guardar el resultado nulo para no volver a buscar
                                $this->warn("\n  - Advertencia: No se encontró sucursal para '{$branchName}' (ID de diseño antiguo: {$old_design->id}). Se dejará nulo.");
                            }
                        }
                    }
                    // --- FIN: LÓGICA AGREGADA ---

                    // b) Creamos la orden de diseño principal conservando el ID.
                    $newDb->table('design_orders')->insert([
                        'id' => $old_design->id,
                        'branch_id' => $branch_id, // <-- CAMPO AGREGADO
                        'order_title' => $old_design->name,
                        'specifications' => $this->combineSpecifications($old_design),
                        'status' => $this->mapStatus($old_design),
                        'is_hight_priority' => $old_design->has_priority,
                        'requester_id' => $old_design->user_id,
                        'designer_id' => $old_design->designer_id,
                        'design_category_id' => $old_design->design_type_id,
                        'design_id' => $old_design->finished_at ? $old_design->id : null,
                        'modifies_design_id' => null, // Las órdenes base no modifican a otras
                        'authorized_user_name' => $old_design->authorized_user_name,
                        'authorized_at' => $old_design->authorized_at,
                        'assigned_at' => $old_design->created_at, // Usamos la fecha de creación como la de asignación inicial
                        'started_at' => $old_design->started_at,
                        'finished_at' => $old_design->finished_at,
                        'due_date' => $old_design->expected_end_at,
                        'created_at' => $old_design->created_at,
                        'updated_at' => $old_design->updated_at,
                    ]);

                    // c) Creamos el log de asignación inicial
                    if ($old_design->designer_id) {
                         $newDb->table('design_assignment_logs')->insert([
                            'design_order_id' => $old_design->id, // Usamos el ID conservado
                            'previous_designer_id' => null,
                            'new_designer_id' => $old_design->designer_id,
                            'changed_by_user_id' => $old_design->user_id, // El solicitante original hizo la "asignación"
                            'reason' => 'Asignación inicial al crear la orden.',
                            'changed_at' => $old_design->created_at,
                        ]);
                    }
                   
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Órdenes de diseño originales migradas con éxito.');

                // --- 2. Migrar las modificaciones como nuevas órdenes de diseño ---
                $this->line('');
                $this->info('Migrando modificaciones como nuevas órdenes de diseño...');
                $old_modifications = $oldDb->table('design_modifications')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_modifications->count());

                foreach ($old_modifications as $modification) {
                    $original_old_design = $oldDb->table('designs')->find($modification->design_id);
                    if (!$original_old_design) {
                        $this->warn(" Saltando modificación ID {$modification->id} porque el diseño original ID {$modification->design_id} no fue encontrado.");
                        $progressBar->advance();
                        continue;
                    }

                    // --- INICIO: LÓGICA AGREGADA PARA BUSCAR BRANCH_ID EN MODIFICACIONES ---
                    $branch_id_mod = null;
                    $branchNameMod = $original_old_design->company_branch_name;
                     if (!empty($branchNameMod)) {
                        if (isset($branchCache[$branchNameMod])) {
                            $branch_id_mod = $branchCache[$branchNameMod];
                        } else {
                            $branch_mod = $newDb->table('branches')->where('name', $branchNameMod)->first();
                            if ($branch_mod) {
                                $branch_id_mod = $branch_mod->id;
                                $branchCache[$branchNameMod] = $branch_id_mod; // Guardar en caché
                            } else {
                                $branchCache[$branchNameMod] = null; // Guardar el resultado nulo
                                $this->warn("\n  - Advertencia: No se encontró sucursal para '{$branchNameMod}' (Modificación del diseño antiguo ID: {$original_old_design->id}). Se dejará nulo.");
                            }
                        }
                    }
                    // --- FIN: LÓGICA AGREGADA ---

                    // a) Creamos la nueva orden que representa la modificación.
                    // Nota: Usamos insertGetId aquí porque no podemos garantizar que el ID de la modificación no colisione.
                    $new_mod_order_id = $newDb->table('design_orders')->insertGetId([
                        'branch_id' => $branch_id_mod, // <-- CAMPO AGREGADO
                        'order_title' => '[MODIFICACIÓN] ' . $original_old_design->name,
                        'specifications' => $modification->modifications,
                        'status' => 'Pendiente', // Las modificaciones se crean como pendientes
                        'is_hight_priority' => $original_old_design->has_priority,
                        'requester_id' => $original_old_design->user_id,
                        'designer_id' => $original_old_design->designer_id,
                        'design_category_id' => $original_old_design->design_type_id,
                        'design_id' => null, // Las órdenes de modificación no tienen un diseño final al migrarse
                        'modifies_design_id' => $modification->design_id,
                        'authorized_user_name' => $original_old_design->authorized_user_name,
                        'authorized_at' => $original_old_design->authorized_at,
                        'assigned_at' => $modification->created_at,
                        'due_date' => null,
                        'created_at' => $modification->created_at,
                        'updated_at' => $modification->updated_at,
                    ]);

                    // b) Creamos su log de asignación
                     if ($original_old_design->designer_id) {
                        $newDb->table('design_assignment_logs')->insert([
                            'design_order_id' => $new_mod_order_id,
                            'previous_designer_id' => null,
                            'new_designer_id' => $original_old_design->designer_id,
                            'changed_by_user_id' => $original_old_design->user_id,
                            'reason' => 'Asignación inicial al crear la orden de modificación.',
                            'changed_at' => $modification->created_at,
                        ]);
                    }

                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Modificaciones migradas con éxito.');

            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE DISEÑOS COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de diseños: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Combina varios campos de texto del diseño antiguo en uno solo.
     * @param object $old_design
     * @return string
     */
    private function combineSpecifications(object $old_design): string
    {
        $specs = [];
        if (!empty($old_design->specifications)) {
            $specs[] = "ESPECIFICACIONES GENERALES:\n" . $old_design->specifications;
        }
        if (!empty($old_design->design_data)) {
            $specs[] = "DATOS DEL DISEÑO:\n" . $old_design->design_data;
        }
        if (!empty($old_design->dimensions)) {
            $specs[] = "DIMENSIONES:\n" . $old_design->dimensions;
        }
        if (!empty($old_design->pantones)) {
            $specs[] = "PANTONES:\n" . $old_design->pantones;
        }

        return implode("\n\n---\n\n", $specs);
    }

    /**
     * Mapea el estado de la orden basado en las fechas del registro antiguo.
     * @param object $old_design
     * @return string
     */
    private function mapStatus(object $old_design): string
    {
        if ($old_design->finished_at) {
            return 'Terminada';
        }
        if ($old_design->started_at) {
            return 'En proceso';
        }
        if ($old_design->authorized_at) {
            return 'Autorizada';
        }
        return 'Pendiente';
    }
}
