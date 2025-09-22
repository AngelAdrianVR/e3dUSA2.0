<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacySales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra todas las ventas y productos. Los envíos (partialities) se migran solo para las últimas 500 ventas.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Ventas y Envíos...");

        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "sales", "sale_products" y "shipments" antes de empezar?', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('shipments')->truncate();
                DB::table('sale_products')->truncate();
                DB::table('sales')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas "sales", "sale_products" y "shipments" han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // Obtenemos los IDs de las últimas 500 ventas para procesar sus envíos después.
            $last500SaleIds = $oldDb->table('sales')->orderBy('id', 'desc')->limit(500)->pluck('id');

            $newDb->transaction(function () use ($oldDb, $newDb, $last500SaleIds) {

                $this->line('');
                $this->info('Migrando TODAS las ventas...');
                
                // Procesamos todas las ventas de la base de datos antigua por ID.
                // Para bases de datos muy grandes, considera usar ->chunkById()
                $old_sales = $oldDb->table('sales')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_sales->count());

                foreach ($old_sales as $sale) {
                    // --- VERIFICACIÓN DE CLAVES FORÁNEAS ---
                    // Si alguna de estas entidades no existe en la nueva BD, omitimos la venta.
                    if ($sale->user_id && !$newDb->table('users')->where('id', $sale->user_id)->exists()) {
                        $this->warn("\nUsuario con ID {$sale->user_id} no encontrado. Omitiendo venta ID {$sale->id}.");
                        $progressBar->advance();
                        continue;
                    }
                    if ($sale->company_branch_id && !$newDb->table('branches')->where('id', $sale->company_branch_id)->exists()) {
                        $this->warn("\nSucursal con ID {$sale->company_branch_id} no encontrada. Omitiendo venta ID {$sale->id}.");
                        $progressBar->advance();
                        continue;
                    }
                    if ($sale->contact_id && !$newDb->table('contacts')->where('id', $sale->contact_id)->exists()) {
                        $this->warn("\nContacto con ID {$sale->contact_id} no encontrado. Omitiendo venta ID {$sale->id}.");
                        $progressBar->advance();
                        continue;
                    }

                    // --- 1. Inserción en la nueva tabla `sales` ---
                    $newDb->table('sales')->insert([
                        'id' => $sale->id,
                        'branch_id' => $sale->company_branch_id,
                        'quote_id' => $sale->oportunity_id,
                        'contact_id' => $sale->contact_id,
                        'user_id' => $sale->user_id,
                        'status' => $this->mapSaleStatus($sale->status),
                        'oce_name' => $sale->oce_name,
                        'order_via' => $sale->order_via,
                        'shipping_option' => $sale->shipping_option,
                        'promise_date' => null,
                        'notes' => $sale->notes,
                        'is_high_priority' => $sale->is_high_priority,
                        'total_amount' => $sale->total_amount,
                        'freight_option' => $sale->freight_option,
                        'freight_cost' => $sale->freight_cost,
                        'authorized_user_name' => $sale->authorized_user_name,
                        'authorized_at' => $sale->authorized_at,
                        'created_at' => $sale->created_at,
                        'updated_at' => $sale->updated_at,
                    ]);

                    // --- 2. Migrar productos asociados a la venta ---
                    $old_products = $oldDb->table('catalog_product_company_sale')->where('sale_id', $sale->id)->get();
                    foreach ($old_products as $product) {
                        $newDb->table('sale_products')->insert([
                            'sale_id' => $product->sale_id,
                            'product_id' => $product->catalog_product_company_id,
                            'quantity' => $product->quantity,
                            'price' => 0, // Ajustar si es necesario
                            'is_new_design' => $product->is_new_design,
                            'notes' => $product->notes,
                            'created_at' => $product->created_at,
                            'updated_at' => $product->updated_at,
                        ]);
                    }

                    // --- 3. Migrar parcialidades (JSON) a `shipments` SÓLO para las últimas 500 ventas ---
                    if ($last500SaleIds->contains($sale->id) && !empty($sale->partialities)) {
                        $partialities = json_decode($sale->partialities);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($partialities)) {
                            foreach ($partialities as $partiality) {
                                $newDb->table('shipments')->insert([
                                    'sale_id' => $sale->id,
                                    'status' => $this->mapShipmentStatus($partiality->status ?? null),
                                    'shipping_company' => $partiality->shipping_company ?? null,
                                    'promise_date' => $partiality->promise_date ?? null,
                                    'tracking_guide' => $partiality->tracking_guide ?? null,
                                    'number_of_packages' => $partiality->number_of_packages ?? null,
                                    'shipping_cost' => $partiality->shipping_cost ?? null,
                                    'sent_by' => $partiality->sent_by ?? null,
                                    'sent_at' => $partiality->sent_at ?? null,
                                    'created_at' => $sale->created_at,
                                    'updated_at' => $sale->updated_at,
                                ]);
                            }
                        }
                    }
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->info(' -> Ventas y productos migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE VENTAS COMPLETADA EXITOSAMENTE!");
            $this->info("Se han transferido todas las ventas. Los envíos se crearon para los últimos 500 registros.");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de ventas: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Mapea el estado de la venta antiguo al nuevo.
     */
    private function mapSaleStatus(?string $oldStatus): string
    {
        if ($oldStatus === null) {
            return 'Pendiente';
        }

        return match (strtolower(trim($oldStatus))) {
            'esperando autorización' => 'Pendiente',
            'autorizada' => 'Autorizada',
            'en proceso' => 'En Proceso',
            'completada' => 'Completada',
            'enviada' => 'Enviada',
            default => 'Pendiente',
        };
    }

    /**
     * Mapea el estado del envío (parcialidad) antiguo al nuevo.
     */
    private function mapShipmentStatus(?string $oldStatus): string
    {
        if ($oldStatus === null) {
            return 'Empacado';
        }
        
        return match (strtolower(trim($oldStatus))) {
            'enviado' => 'Enviado',
            'entregado' => 'Entregado',
            default => 'Empacado',
        };
    }
}

