<?php

namespace App\Console\Commands;

use App\Models\Supplier; // Importar el modelo para la relación polimórfica
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacySuppliers extends Command
{
    /**
     * The name and signature of the console command.
     * N°8. Todo bien solo que algunos de los contactos no se muestran, es cosa de la migracion de contactos.
     * @var string
     */
    protected $signature = 'app:migrate-legacy-suppliers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de proveedores y productos, y asigna los contactos polimórficos ya existentes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Proveedores...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "suppliers", "supplier_bank_accounts" y "product_supplier" antes de empezar? Los contactos NO serán eliminados.', true)) {
            try {
                DB::statement('SET FOREIGN_key_CHECKS=0;');
                DB::table('product_supplier')->truncate();
                DB::table('supplier_bank_accounts')->truncate();
                DB::table('suppliers')->truncate();
                // NOTA: No truncamos 'contacts' ni 'contact_details'
                DB::statement('SET FOREIGN_key_CHECKS=1;');
                $this->warn('Las tablas de destino (excepto contactos) han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            // Conexiones a las bases de datos
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // Caché para los IDs de productos para no consultar la BD repetidamente
            $productCache = [];

            $newDb->transaction(function () use ($oldDb, $newDb, &$productCache) {
                
                $this->line('');
                $this->info('Migrando proveedores, asignando contactos y migrando productos...');
                $old_suppliers = $oldDb->table('suppliers')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_suppliers->count());

                foreach ($old_suppliers as $supplier) {
                    if ($newDb->table('suppliers')->where('name', $supplier->name)->exists()) {
                        $this->warn("\n  - Omitiendo proveedor duplicado: '{$supplier->name}' (ID Antiguo: {$supplier->id}).");
                        $progressBar->advance();
                        continue;
                    }

                    // --- 1. Migrar datos del proveedor ---
                    $newDb->table('suppliers')->insert([
                        'id'         => $supplier->id,
                        'name'       => $supplier->name,
                        'nickname'   => $supplier->nickname,
                        'address'    => $supplier->address,
                        'post_code'  => $supplier->post_code,
                        'phone'      => $supplier->phone,
                        'email'      => null, // El email ahora está en el contacto
                        'notes'      => null, // Añadir si existe en la tabla antigua
                        'created_at' => $supplier->created_at,
                        'updated_at' => $supplier->updated_at,
                    ]);

                    // --- 2. Migrar cuentas bancarias (desde JSON) ---
                    if (!empty($supplier->banks)) {
                        $banks = json_decode($supplier->banks, true);
                        if (is_array($banks)) {
                            foreach ($banks as $bank) {
                                $cleanClabe = isset($bank['clabe']) ? preg_replace('/[^0-9]/', '', $bank['clabe']) : null;
                                $newDb->table('supplier_bank_accounts')->insert([
                                    'supplier_id'      => $supplier->id,
                                    'bank_name'        => $bank['bank_name'] ?? 'N/A',
                                    'account_holder'   => $bank['account_holder'] ?? $bank['beneficiary_name'] ?? $supplier->name,
                                    'account_number'   => $bank['account_number'] ?? 'N/A',
                                    'clabe'            => $cleanClabe ? substr($cleanClabe, 0, 18) : null,
                                    'currency'         => $bank['currency'] ?? 'MXN',
                                    'created_at'       => $supplier->created_at,
                                    'updated_at'       => $supplier->updated_at,
                                ]);
                            }
                        }
                    }
                    
                    // // --- 3. Asignar Contactos existentes a la tabla polimórfica ---
                    // if (!empty($supplier->contact_id)) {
                    //     $old_contact = $oldDb->table('contacts')->where('id', $supplier->contact_id)->first();
                    //     if ($old_contact && !empty($old_contact->name)) {
                    //         $contact_name = $old_contact->name;
                            
                    //         // Buscamos el contacto en la nueva BD por nombre que aún no esté asignado
                    //         $new_contact = $newDb->table('contacts')
                    //             ->where('name', $contact_name)
                    //             ->whereNull('contactable_id')
                    //             ->first();

                    //         if ($new_contact) {
                    //             // Si lo encontramos, actualizamos la relación polimórfica
                    //             $newDb->table('contacts')->where('id', $new_contact->id)->update([
                    //                 'contactable_type' => Supplier::class,
                    //                 'contactable_id'   => $supplier->id,
                    //             ]);
                    //         } else {
                    //             $this->warn("\n  - Advertencia: Contacto '{$contact_name}' no fue encontrado o ya estaba asignado en la nueva BD. No se pudo asociar al proveedor '{$supplier->name}'.");
                    //         }
                    //     }
                    // }

                    // --- 4. Migrar productos relacionados ---
                    if (!empty($supplier->raw_materials_id)) {
                        $old_product_ids = json_decode($supplier->raw_materials_id, true);
                        if (is_array($old_product_ids)) {
                            foreach ($old_product_ids as $old_id) {
                                // CORRECCIÓN: Buscar solo en la tabla 'rawm_aterials'
                                $old_product = $oldDb->table('raw_materials')->where('id', $old_id)->first();

                                if ($old_product && !empty($old_product->name)) {
                                    $product_name = $old_product->name;
                                    $new_product_id = null;

                                    if (isset($productCache[$product_name])) {
                                        $new_product_id = $productCache[$product_name];
                                    } else {
                                        $new_product = $newDb->table('products')->where('name', $product_name)->first();
                                        if ($new_product) {
                                            $new_product_id = $new_product->id;
                                            $productCache[$product_name] = $new_product_id;
                                        } else {
                                            $productCache[$product_name] = null;
                                            $this->warn("\n  - Advertencia: Producto '{$product_name}' no encontrado en la nueva BD. No se pudo asociar al proveedor '{$supplier->name}'.");
                                        }
                                    }

                                    if ($new_product_id) {
                                        // CORRECCIÓN: Verificar si la relación ya existe antes de insertar
                                        $exists = $newDb->table('product_supplier')
                                            ->where('supplier_id', $supplier->id)
                                            ->where('product_id', $new_product_id)
                                            ->exists();

                                        if (!$exists) {
                                            $newDb->table('product_supplier')->insert([
                                                'supplier_id' => $supplier->id,
                                                'product_id'  => $new_product_id,
                                                'created_at'  => now(),
                                                'updated_at'  => now(),
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Proveedores y sus datos relacionados migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE PROVEEDORES COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de proveedores: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

