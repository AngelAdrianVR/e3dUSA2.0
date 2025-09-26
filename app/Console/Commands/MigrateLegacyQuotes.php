<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyQuotes extends Command
{
    /**
     * The name and signature of the console command.
     * N°11 todo bien!
     * @var string
     */
    protected $signature = 'app:migrate-legacy-quotes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra las cotizaciones y sus productos desde la base de datos antigua a la nueva.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Cotizaciones y sus productos...");

        // Listas para guardar advertencias
        $notFoundProducts = [];
        $missingForeignKeys = [];

        // Confirmación para limpiar las tablas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "quotes" y "quote_products" antes de empezar?', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('quote_products')->truncate();
                DB::table('quotes')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas "quotes" y "quote_products" han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // --- Cargar mapeos de sucursales ---
            $this->info('Cargando mapeo de sucursales...');
            $oldBranches = $oldDb->table('company_branches')->pluck('name', 'id');
            $newBranches = $newDb->table('branches')->pluck('id', 'name');
            $this->info('Mapeo de sucursales cargado.');

            $newDb->transaction(function () use ($oldDb, $newDb, $oldBranches, $newBranches, &$notFoundProducts, &$missingForeignKeys) {
                
                $this->line('');
                $this->info('Migrando cotizaciones...');
                
                $oldQuotes = $oldDb->table('quotes')->orderBy('id', 'asc')->get();
                $progressBar = $this->output->createProgressBar($oldQuotes->count());
                
                $this->info("Se encontraron {$oldQuotes->count()} cotizaciones para migrar.");
                $progressBar->start();

                foreach ($oldQuotes as $oldQuote) {
                    // --- Mapeo de Sucursal por nombre ---
                    $oldBranchName = $oldBranches->get($oldQuote->company_branch_id);
                    $newBranchId = $oldBranchName ? $newBranches->get($oldBranchName) : null;
                    
                    if (!$newBranchId) {
                        $missingForeignKeys['branch'][] = "Cotización ID {$oldQuote->id} -> Sucursal '{$oldBranchName}' no encontrada en la nueva BD.";
                    }

                    // --- Verificación de usuario ---
                    $userId = $oldQuote->user_id;
                    if ($userId && !$newDb->table('users')->where('id', $userId)->exists()) {
                        $missingForeignKeys['user'][] = "Cotización ID {$oldQuote->id} -> Usuario ID {$userId}";
                        $userId = null;
                    }

                    // --- 1. Migrar la cotización principal ---
                    $newQuoteId = $newDb->table('quotes')->insertGetId([
                        'id' => $oldQuote->id,
                        'status' => $this->mapStatus($oldQuote->status, $oldQuote->quote_acepted),
                        'receiver' => $oldQuote->receiver,
                        'department' => $oldQuote->department,
                        'currency' => ltrim($oldQuote->currency, '$'),
                        'tooling_cost' => floatval($oldQuote->tooling_cost),
                        'is_tooling_cost_stroked' => $oldQuote->tooling_cost_stroked,
                        'freight_option' => $this->mapFreightOption($oldQuote),
                        'freight_cost' => is_numeric($oldQuote->freight_cost) ? $oldQuote->freight_cost : 0,
                        'is_freight_cost_stroked' => $oldQuote->freight_cost_stroked,
                        'first_production_days' => $oldQuote->first_production_days,
                        'notes' => $oldQuote->notes,
                        'rejection_reason' => $oldQuote->rejected_razon,
                        'customer_responded_at' => $oldQuote->responded_at,
                        'authorized_by_user_id' => null, // Se establece como nulo
                        'authorized_at' => now(), // Se marcan todas como autorizadas ahora
                        'is_spanish_template' => $oldQuote->is_spanish_template,
                        'show_breakdown' => $oldQuote->show_breakdown,
                        'created_by_customer' => $oldQuote->created_by_customer,
                        'has_early_payment_discount' => $oldQuote->early_payment_discount,
                        'early_payment_discount_amount' => $oldQuote->discount ?? 0,
                        'early_paid_at' => $oldQuote->early_paid_at,
                        'branch_id' => $newBranchId, // Usamos el ID nuevo encontrado
                        'user_id' => $userId,
                        'sale_id' => $oldQuote->sale_id, // Se migra directamente
                        'created_at' => $oldQuote->created_at,
                        'updated_at' => $oldQuote->updated_at,
                    ]);

                    // --- 2. Migrar los productos de la cotización ---
                    $oldQuoteProducts = $oldDb->table('catalog_product_quote')
                        ->where('quote_id', $oldQuote->id)
                        ->orderBy('id', 'asc')
                        ->get();

                    foreach ($oldQuoteProducts as $oldQuoteProduct) {
                        $oldCatalogProduct = $oldDb->table('catalog_products')
                            ->find($oldQuoteProduct->catalog_product_id);

                        if (!$oldCatalogProduct) {
                            $this->warn("\nNo se encontró el CatalogProduct antiguo con ID: {$oldQuoteProduct->catalog_product_id}. Saltando...");
                            continue;
                        }

                        $newProduct = $newDb->table('products')->where('name', $oldCatalogProduct->name)->first();

                        if (!$newProduct) {
                            $notFoundProducts[] = $oldCatalogProduct->name;
                            continue;
                        }

                        $newDb->table('quote_products')->insert([
                            'quote_id' => $newQuoteId,
                            'product_id' => $newProduct->id,
                            'quantity' => $oldQuoteProduct->quantity,
                            'unit_price' => $oldQuoteProduct->price,
                            'notes' => $oldQuoteProduct->notes,
                            'show_image' => $oldQuoteProduct->show_image,
                            'customer_approval_status' => 'Aprobado', // Se establece como Aprobado
                            'created_at' => $oldQuoteProduct->created_at,
                            'updated_at' => $oldQuoteProduct->updated_at,
                        ]);
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE COTIZACIONES COMPLETADA EXITOSAMENTE!");

            if (!empty($missingForeignKeys)) {
                $this->warn("\nADVERTENCIA: Se encontraron IDs de relaciones que no existen en la nueva base de datos. Se asignó 'null' en los siguientes casos:");
                foreach ($missingForeignKeys as $key => $messages) {
                    $this->line("Para la relación: " . ucfirst($key));
                    foreach (array_unique($messages) as $message) {
                        $this->line("- " . $message);
                    }
                }
            }

            if (!empty($notFoundProducts)) {
                $this->warn("\nADVERTENCIA: No se pudieron asociar los siguientes productos a sus cotizaciones porque no se encontraron por nombre en la nueva base de datos:");
                foreach (array_unique($notFoundProducts) as $productName) {
                    $this->line("- " . $productName);
                }
            }

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de cotizaciones: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Mapea el estado de la cotización antigua al nuevo.
     */
    private function mapStatus(?string $status, ?bool $isAcepted): string
    {
        // El campo booleano tiene la máxima prioridad.
        if ($isAcepted === true) {
            return 'Aceptada';
        }
        if ($isAcepted === false) {
            return 'Rechazada';
        }

        // Si el booleano es nulo, se revisa el string de status.
        if ($status === 'Aceptada') {
            return 'Aceptada';
        }
        if ($status === 'Rechazada') {
            return 'Rechazada';
        }

        // Para cualquier otro caso, el estado será 'Esperando respuesta'.
        return 'Esperando respuesta';
    }

    /**
     * Mapea la opción de flete.
     */
    private function mapFreightOption(object $oldQuote): string
    {
        if ($oldQuote->freight_cost_charged_in_product) {
            return 'Cargo de flete prorrateado en productos';
        }
        return $oldQuote->freight_option ?? 'Por cuenta del cliente';
    }
}
