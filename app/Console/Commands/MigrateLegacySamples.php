<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CatalogProduct; // Asegúrate de que la ruta a tu modelo sea correcta
use App\Models\NewProductProposal; // Asegúrate de que la ruta a tu modelo sea correcta
use App\Models\Product;
use Throwable;

class MigrateLegacySamples extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-samples';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de seguimiento de muestras desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Seguimiento de Muestras...");

        // Confirmación para limpiar las tablas nuevas
        $tablesToTruncate = ['sample_trackings', 'sample_tracking_items', 'new_product_proposals'];
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "' . implode('", "', $tablesToTruncate) . '" antes de empezar?')) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($tablesToTruncate as $table) {
                    DB::table($table)->truncate();
                }
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

            // Pre-cargamos las sucursales y contactos para mapear por nombre
            $this->line('');
            $this->info('Cargando mapeos...');
            $oldBranches = $oldDb->table('company_branches')->pluck('name', 'id');
            $newBranches = $newDb->table('branches')->pluck('id', 'name');
            $oldContacts = $oldDb->table('contacts')->pluck('name', 'id');
            $newContacts = $newDb->table('contacts')->pluck('id', 'name');
            $this->info('Mapeos de sucursales y contactos cargados.');


            $newDb->transaction(function () use ($oldDb, $newDb, $oldBranches, $newBranches, $oldContacts, $newContacts) {
                $this->line('');
                $this->info('Migrando muestras...');
                $old_samples = $oldDb->table('samples')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_samples->count());

                foreach ($old_samples as $sample) {
                    // Mapear el ID de la sucursal por nombre
                    $oldBranchName = $oldBranches->get($sample->company_branch_id);
                    $newBranchId = $oldBranchName ? $newBranches->get($oldBranchName) : null;

                    if (!$newBranchId) {
                        $this->warn(" Saltando muestra ID {$sample->id} porque la sucursal '{$oldBranchName}' no fue encontrada en la nueva BD.");
                        $progressBar->advance();
                        continue;
                    }
                    
                    // Mapear el ID del contacto por nombre
                    $oldContactName = $oldContacts->get($sample->contact_id);
                    $newContactId = $oldContactName ? $newContacts->get($oldContactName) : null;
                    
                    if (!$newContactId) {
                        $this->warn(" Saltando muestra ID {$sample->id} porque el contacto '{$oldContactName}' no fue encontrado en la nueva BD.");
                        $progressBar->advance();
                        continue;
                    }

                    // 1. Crear el registro principal en `sample_trackings` conservando el ID
                    $newDb->table('sample_trackings')->insert([
                        'id' => $sample->id,
                        'name' => $sample->name,
                        'status' => $this->mapStatus($sample),
                        'branch_id' => $newBranchId, // Usamos el ID nuevo encontrado
                        'contact_id' => $newContactId, // Usamos el ID nuevo encontrado
                        'requester_user_id' => $sample->user_id,
                        'authorized_by_user_id' => null, // No hay un ID de usuario claro en la tabla antigua
                        'sale_id' => null, // No hay un ID de venta claro en la tabla antigua
                        'will_be_returned' => $sample->will_back,
                        'expected_devolution_date' => $sample->devolution_date,
                        'comments' => $sample->comments,
                        'authorized_at' => $sample->authorized_at,
                        'approved_at' => $sample->sale_order_at, // Asumimos que si se vendió, se aprobó
                        'denied_at' => $sample->denied_at,
                        'sent_at' => $sample->sent_at,
                        'returned_at' => $sample->returned_at,
                        'completed_at' => $sample->sale_order_at, // Asumimos que se completa cuando se genera la venta
                        'created_at' => $sample->created_at,
                        'updated_at' => $sample->updated_at,
                    ]);

                    // 2. Procesar los items de la muestra
                    
                    // a) Si hay un producto del catálogo vinculado
                    if ($sample->catalog_product_id) {
                        $newDb->table('sample_tracking_items')->insert([
                            'sample_tracking_id' => $sample->id,
                            'itemable_id' => $sample->catalog_product_id,
                            'itemable_type' => Product::class, // O la ruta a tu modelo de producto
                            'quantity' => $sample->quantity,
                            'requires_modification' => $sample->requires_modification,
                            'created_at' => $sample->created_at,
                            'updated_at' => $sample->updated_at,
                        ]);
                    }

                    // b) Si hay productos no registrados en el campo JSON
                    if (!empty($sample->products)) {
                        $unregistered_products = json_decode($sample->products);
                        if (is_array($unregistered_products)) {
                            foreach ($unregistered_products as $product_name) {
                                // Primero, creamos la propuesta de nuevo producto
                                $proposal_id = $newDb->table('new_product_proposals')->insertGetId([
                                    'name' => $product_name,
                                    'status' => 'Propuesta',
                                    'created_at' => $sample->created_at,
                                    'updated_at' => $sample->updated_at,
                                ]);

                                // Luego, creamos el item de la muestra que se vincula a esa propuesta
                                $newDb->table('sample_tracking_items')->insert([
                                    'sample_tracking_id' => $sample->id,
                                    'itemable_id' => $proposal_id,
                                    'itemable_type' => NewProductProposal::class, // O la ruta a tu modelo de propuesta
                                    'quantity' => 1, // Asumimos 1 ya que no hay cantidad específica en el JSON antiguo
                                    'requires_modification' => false,
                                    'created_at' => $sample->created_at,
                                    'updated_at' => $sample->updated_at,
                                ]);
                            }
                        }
                    }
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->info(' -> Muestras migradas con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE MUESTRAS COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de muestras: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Mapea el estado de la muestra basado en las fechas y banderas del registro antiguo.
     * @param object $old_sample
     * @return string
     */
    private function mapStatus(object $old_sample): string
    {
        if ($old_sample->sale_order_at) return 'Completado';
        if ($old_sample->returned_at) return 'Devuelto';
        if ($old_sample->denied_at) return 'Rechazado';
        if ($old_sample->sent_at) return 'Enviado';
        if ($old_sample->authorized_at) return 'Autorizado';
        if ($old_sample->requires_modification) return 'Modificación';

        return 'Pendiente';
    }
}

