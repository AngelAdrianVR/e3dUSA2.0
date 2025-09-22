<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacySuppliers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-suppliers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de proveedores desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Proveedores...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "suppliers", "supplier_bank_accounts" y "supplier_contacts" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_key_CHECKS=0;');
                DB::table('supplier_contacts')->truncate();
                DB::table('supplier_bank_accounts')->truncate();
                DB::table('suppliers')->truncate();
                DB::statement('SET FOREIGN_key_CHECKS=1;');
                $this->warn('Las tablas de proveedores, cuentas bancarias y contactos han sido limpiadas.');
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
                
                // --- 1. Migrar la tabla `suppliers` ---
                $this->line('');
                $this->info('Migrando proveedores...');
                $old_suppliers = $oldDb->table('suppliers')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_suppliers->count());

                foreach ($old_suppliers as $supplier) {
                    // Verificamos si ya existe un proveedor con este nombre para evitar el error de duplicado.
                    $isDuplicate = $newDb->table('suppliers')->where('name', $supplier->name)->exists();

                    if ($isDuplicate) {
                        $this->warn(" Proveedor duplicado en la BD de origen. Omitiendo la inserción de '{$supplier->name}' (ID Antiguo: {$supplier->id}).");
                        $progressBar->advance();
                        continue; // Saltamos al siguiente proveedor
                    }

                    // Mapeo de campos directos
                    $newSupplierData = [
                        'id'         => $supplier->id,
                        'name'       => $supplier->name,
                        'nickname'   => $supplier->nickname,
                        'address'    => $supplier->address,
                        'post_code'  => $supplier->post_code,
                        'phone'      => $supplier->phone,
                        'created_at' => $supplier->created_at,
                        'updated_at' => $supplier->updated_at,
                    ];

                    $newDb->table('suppliers')->insert($newSupplierData);

                    // --- 2. Migrar las cuentas bancarias (desde JSON) ---
                    if (!empty($supplier->banks)) {
                        $banks = json_decode($supplier->banks, true);
                        
                        if (is_array($banks)) {
                            foreach ($banks as $bank) {
                                // Limpiamos la CLABE para remover espacios y otros caracteres no numéricos.
                                $rawClabe = $bank['clabe'] ?? null;
                                $cleanClabe = $rawClabe ? preg_replace('/[^0-9]/', '', $rawClabe) : null;
                                
                                // Como seguridad extra, truncamos la clabe a 18 caracteres.
                                if ($cleanClabe) {
                                    $cleanClabe = substr($cleanClabe, 0, 18);
                                }

                                // Mapeo flexible de campos del JSON para manejar inconsistencias.
                                $accountHolder = $bank['account_holder'] ?? $bank['beneficiary_name'] ?? $supplier->name;
                                $accountNumber = $bank['account_number'] ?? $bank['accountNumber'] ?? 'N/A';

                                // Aseguramos que los campos existan antes de insertarlos
                                $newDb->table('supplier_bank_accounts')->insert([
                                    'supplier_id'      => $supplier->id,
                                    'bank_name'        => $bank['bank_name'] ?? 'N/A',
                                    'account_holder'   => $accountHolder,
                                    'account_number'   => $accountNumber,
                                    'clabe'            => $cleanClabe,
                                    'currency'         => $bank['currency'] ?? 'MXN',
                                    'created_at'       => $supplier->created_at,
                                    'updated_at'       => $supplier->updated_at,
                                ]);
                            }
                        }
                    }
                    
                    // --- 3. Migrar el contacto principal ---
                    // Asumimos que existe una tabla 'contacts' en la BD antigua
                    if (!empty($supplier->contact_id)) {
                        $contact = $oldDb->table('contacts')->where('id', $supplier->contact_id)->first();
                        if ($contact) {
                            $newDb->table('supplier_contacts')->insert([
                                'supplier_id' => $supplier->id,
                                'name'        => $contact->name,
                                'position'    => $contact->position ?? null,
                                'email'       => $contact->email ?? null,
                                'phone'       => $contact->phone ?? 'N/A',
                                'is_primary'  => true,
                                'created_at'  => $contact->created_at ?? $supplier->created_at,
                                'updated_at'  => $contact->updated_at ?? $supplier->updated_at,
                            ]);
                        }
                    }

                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Proveedores y sus datos relacionados migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE PROVEEDORES COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los datos han sido transferidos a la nueva base de datos.");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de proveedores: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

