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
     * N°12 Todo correcto! Hecho.
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

        // Listas para guardar advertencias
        $missingForeignKeys = [];
        $notFoundProducts = [];

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

            // --- Cargar mapeos ---
            $this->info('Cargando mapeos de sucursales y productos...');
            // Mapeo de Sucursales
            $oldBranches = $oldDb->table('company_branches')->pluck('name', 'id');
            $newBranches = $newDb->table('branches')->pluck('id', 'name');

            // Mapeo de Productos (en varios pasos)
            $oldCatalogProducts = $oldDb->table('catalog_products')->pluck('name', 'id');
            $oldCompanyProductToCatalog = $oldDb->table('catalog_product_company')->pluck('catalog_product_id', 'id');
            $newProducts = $newDb->table('products')->pluck('id', 'name');

            // --- NUEVO: Mapeo de Precios de Productos ---
            $this->info('Cargando mapeo de precios...');
            $oldCompanyProductPrices = $oldDb->table('catalog_product_company')->pluck('new_price', 'id');
            $this->info('Mapeo de precios cargado.');


            // Obtenemos los IDs de las últimas 500 ventas para procesar sus envíos después.
            $last500SaleIds = $oldDb->table('sales')->orderBy('id', 'desc')->limit(500)->pluck('id');

            $newDb->transaction(function () use ($oldDb, $newDb, $last500SaleIds, $oldBranches, $newBranches, $oldCatalogProducts, $oldCompanyProductToCatalog, $newProducts, $oldCompanyProductPrices, &$missingForeignKeys, &$notFoundProducts) {

                $this->line('');
                $this->info('Migrando TODAS las ventas...');
                
                $old_sales = $oldDb->table('sales')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_sales->count());

                foreach ($old_sales as $sale) {
                    // --- Mapeo de Sucursal por nombre ---
                    $oldBranchName = $oldBranches->get($sale->company_branch_id);
                    $newBranchId = $oldBranchName ? $newBranches->get($oldBranchName) : null;
                    if (!$newBranchId && $sale->company_branch_id) {
                        $missingForeignKeys['branch'][] = "Venta ID {$sale->id} -> Sucursal '{$oldBranchName}' no encontrada en la nueva BD.";
                    }

                    // --- Verificación de usuario y contacto (asumiendo que los IDs coinciden) ---
                    $contactId = $sale->contact_id; // Asignamos el ID original por defecto
                    if ($sale->user_id && !$newDb->table('users')->where('id', $sale->user_id)->exists()) {
                        $missingForeignKeys['user'][] = "Venta ID {$sale->id} -> Usuario ID {$sale->user_id}";
                    }
                     if ($sale->contact_id && !$newDb->table('contacts')->where('id', $sale->contact_id)->exists()) {
                        $missingForeignKeys['contact'][] = "Venta ID {$sale->id} -> Contacto ID {$sale->contact_id}";
                        $contactId = null; // Si no existe, lo establecemos como null
                    }

                    // --- LÓGICA DE PRODUCTOS Y CÁLCULO DE TOTAL ---
                    $old_products_for_sale = $oldDb->table('catalog_product_company_sale')->where('sale_id', $sale->id)->get();
                    $calculatedTotalAmount = 0;
                    $newProductsToInsert = [];

                    foreach ($old_products_for_sale as $product) {
                        // Obtener precio unitario del mapeo
                        $unitPrice = $oldCompanyProductPrices->get($product->catalog_product_company_id) ?? 0;
                        
                        // Sumar al total de la venta
                        $calculatedTotalAmount += $product->quantity * $unitPrice;

                        // Lógica para encontrar el nuevo ID del producto
                        $oldCatalogProductId = $oldCompanyProductToCatalog->get($product->catalog_product_company_id);
                        $oldProductName = $oldCatalogProductId ? $oldCatalogProducts->get($oldCatalogProductId) : null;
                        $newProductId = $oldProductName ? $newProducts->get($oldProductName) : null;

                        if ($newProductId) {
                             $newProductsToInsert[] = [
                                'sale_id' => $sale->id,
                                'product_id' => $newProductId,
                                'quantity' => $product->quantity,
                                'quantity_to_produce' => $product->quantity,
                                'price' => $unitPrice, // Guardamos el precio unitario
                                'is_new_design' => $product->is_new_design,
                                'notes' => $product->notes,
                                'created_at' => $product->created_at,
                                'updated_at' => $product->updated_at,
                            ];
                        } else {
                            $notFoundProducts[] = $oldProductName ?? "ID Antiguo (company): {$product->catalog_product_company_id}";
                        }
                    }

                    // --- 1. Inserción en la nueva tabla `sales` ---
                    $newDb->table('sales')->insert([
                        'id' => $sale->id,
                        'branch_id' => $newBranchId,
                        'quote_id' => $sale->oportunity_id,
                        'contact_id' => $contactId, // Usamos la variable que puede ser null
                        'user_id' => $sale->user_id,
                        'status' => $this->mapSaleStatus($sale->status),
                        'oce_name' => $sale->oce_name,
                        'order_via' => $sale->order_via,
                        'shipping_option' => $sale->shipping_option,
                        'promise_date' => null,
                        'notes' => $sale->notes,
                        'is_high_priority' => $sale->is_high_priority,
                        'total_amount' => $calculatedTotalAmount, // Usamos el total calculado
                        'freight_option' => $sale->freight_option,
                        'freight_cost' => $sale->freight_cost,
                        'authorized_user_name' => $sale->authorized_user_name,
                        'authorized_at' => $sale->authorized_at,
                        'created_at' => $sale->created_at,
                        'updated_at' => $sale->updated_at,
                    ]);

                    // --- 2. Migrar productos asociados a la venta ---
                    if (!empty($newProductsToInsert)) {
                        $newDb->table('sale_products')->insert($newProductsToInsert);
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

            if (!empty($missingForeignKeys)) {
                $this->warn("\nADVERTENCIA: Se encontraron IDs de relaciones que no existen en la nueva base de datos:");
                foreach ($missingForeignKeys as $key => $messages) {
                    $this->line("Para la relación: " . ucfirst($key));
                    foreach (array_unique($messages) as $message) {
                        $this->line("- " . $message);
                    }
                }
            }

            if (!empty($notFoundProducts)) {
                $this->warn("\nADVERTENCIA: Los siguientes productos no se asociaron a sus ventas porque no se encontraron por nombre en la nueva BD:");
                foreach (array_unique($notFoundProducts) as $productName) {
                    $this->line("- " . $productName);
                }
            }

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

        return match (trim($oldStatus)) {
            'Autorizado. Sin orden de producción' => 'Autorizada',
            'Producción sin iniciar' => 'En Proceso',
            'Producción en proceso' => 'En Producción',
            'Producción terminada' => 'Preparando Envío',
            'Parcialmente enviado' => 'Preparando Envío',
            'Enviado' => 'Enviada',
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
