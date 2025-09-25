<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Product; // Asegúrate de importar tu modelo Product
use App\Models\SupplierContact; // Importar el modelo de contacto del proveedor
use Throwable;

class MigrateLegacyPurchases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-purchases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de compras y sus items desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Compras...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "purchases" y "purchase_items" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_key_CHECKS=0;');
                DB::table('purchase_items')->truncate();
                DB::table('purchases')->truncate();
                DB::statement('SET FOREIGN_key_CHECKS=1;');
                $this->warn('Las tablas "purchases" y "purchase_items" han sido limpiadas.');
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
            $newDb->transaction(function () use ($oldDb, $newDb) {
                
                // --- 1. Migrar la tabla `purchases` ---
                $this->line('');
                $this->info('Migrando compras...');
                $old_purchases = $oldDb->table('purchases')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_purchases->count());

                foreach ($old_purchases as $purchase) {
                    // Mapeo del ID de contacto
                    $supplierContactId = null;
                    if ($purchase->contact_id) {
                        // 1. Buscar el contacto en la BD antigua por ID para obtener el nombre
                        $oldContact = $oldDb->table('contacts')->find($purchase->contact_id);

                        if ($oldContact) {
                            // 2. Buscar el contacto en la BD nueva por nombre y proveedor para obtener el nuevo ID
                            $newContact = SupplierContact::where('name', $oldContact->name)
                                                         ->where('supplier_id', $purchase->supplier_id)
                                                         ->first();
                            if ($newContact) {
                                $supplierContactId = $newContact->id;
                            } else {
                                $this->warn("\nContacto '{$oldContact->name}' no encontrado para el proveedor ID {$purchase->supplier_id} en la nueva BD. Se omitirá en la compra ID {$purchase->id}.");
                            }
                        }
                    }

                    // Inserción en la nueva tabla de compras, manteniendo el ID
                    $newDb->table('purchases')->insert([
                        'id' => $purchase->id,
                        'currency' => ltrim($purchase->currency, '$'),
                        'is_spanish_template' => $purchase->is_spanish_template,
                        'status' => $this->mapPurchaseStatus($purchase->status),
                        'supplier_id' => $purchase->supplier_id,
                        'user_id' => $purchase->user_id,
                        'supplier_contact_id' => $supplierContactId,
                        'notes' => $purchase->notes,
                        'shipping_details' => $purchase->carrier, 
                        'rating' => $purchase->rating,
                        'emited_at' => $purchase->emited_at,
                        'authorizer_id' => 20,
                        'authorized_at' => $purchase->authorized_at,
                        'expected_delivery_date' => $purchase->expected_delivery_date,
                        'recieved_at' => $purchase->recieved_at,
                        'invoice_folio' => $purchase->invoice_folio,
                        'created_at' => $purchase->created_at,
                        'updated_at' => $purchase->updated_at,
                    ]);

                    // --- 2. Migrar los `products` del JSON a `purchase_items` ---
                    if ($purchase->products) {
                        $products = json_decode($purchase->products);
                        if (is_array($products)) {
                            foreach ($products as $product_item) {
                                // Buscamos el producto en la nueva BD por su NOMBRE
                                $newProduct = Product::where('name', $product_item->name)->first();

                                if ($newProduct) {
                                    $newDb->table('purchase_items')->insert([
                                        'purchase_id' => $purchase->id,
                                        'product_id' => $newProduct->id,
                                        'description' => $product_item->name,
                                        'quantity' => $product_item->quantity,
                                        'additional_stock' => $product_item->additional_stock ?? null,
                                        'plane_stock' => $product_item->plane_stock ?? null,
                                        'ship_stock' => $product_item->ship_stock ?? null,
                                        'unit_price' => 0, 
                                        'total_price' => 0,
                                        'created_at' => $purchase->created_at,
                                        'updated_at' => $purchase->updated_at,
                                    ]);
                                } else {
                                    $this->warn("\nProducto con nombre '{$product_item->name}' no encontrado en la nueva BD. Se omitirá para la compra ID {$purchase->id}.");
                                }
                            }
                        }
                    }
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Compras y sus items migradas con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE COMPRAS COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los datos han sido transferidos a la nueva base de datos.");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de compras: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Mapea el estado de la compra del valor antiguo al nuevo (string).
     *
     * @param mixed $oldStatus
     * @return string
     */
    private function mapPurchaseStatus(mixed $oldStatus): string
    {
        // NOTA: Asumo que los status en la BD antigua son strings.
        // Si fueran los integers del schema (0,1,2,3), necesitarías ajustar este match.
        return match ((string) $oldStatus) {
            'Compra realizada' => 'Compra realizada',
            'Producto/servicio recibido', '2', '3' => 'Compra recibida',
            'Autorizado.Compra no realizada', '0', '1' => 'Autorizada',
            default => 'Autorizada',
        };
    }
}

