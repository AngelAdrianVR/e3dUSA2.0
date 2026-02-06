<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\StockRepositionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StockReposition extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:stock-reposition';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica a usuarios con roles específicos sobre productos con stock bajo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Iniciando comando app:stock-reposition');

        // 1. Obtener productos candidatos:
        // - Tipo "Materia prima"
        // - O Tipo "Catálogo" y is_purchasable = true
        $candidates = Product::with('storages')
            ->where(function ($query) {
                $query->where('product_type', 'Materia prima')
                      ->orWhere(function ($q) {
                          $q->where('product_type', 'Catálogo')
                            ->where('is_purchasable', true);
                      });
            })
            ->get();

        // 2. Filtrar aquellos cuyo stock total sea menor o igual al mínimo
        $lowStockProducts = $candidates->filter(function ($product) {
            // Sumamos el stock de todos los almacenes (relación morphMany)
            $currentStock = $product->storages->sum('quantity');
            
            // Comparamos contra el mínimo establecido en el producto
            return $currentStock < $product->min_quantity;
        });

        if ($lowStockProducts->isEmpty()) {
            Log::info('No se encontraron productos con bajo stock.');
            $this->info('No hay productos con bajo stock.');
            return;
        }

        $count = $lowStockProducts->count();
        Log::info("Se encontraron {$count} productos con bajo stock.");

        // 3. Obtener los usuarios a notificar por ROL
        // Roles solicitados: Super Administrador, Almacenista, Asistente de director, Samuel, Compras.
        $targetRoles = [
            'Super Administrador',
            'Almacenista',
            'Asistente de director',
            'Samuel', // Se busca como Rol, no como usuario
            'Compras'
        ];

        // Buscamos usuarios activos que tengan CUALQUIERA de estos roles
        // Asume uso de Spatie Laravel Permission (relación 'roles' y columna 'name')
        $usersToNotify = User::where('is_active', true)
            ->whereHas('roles', function ($query) use ($targetRoles) {
                $query->whereIn('name', $targetRoles);
            })
            ->get();

        // 4. Enviar notificaciones
        if ($usersToNotify->isNotEmpty()) {
            $notification = new StockRepositionNotification($lowStockProducts);

            foreach ($usersToNotify as $user) {
                try {
                    $user->notify($notification);
                    // Logueamos a quién se envió para tener trazabilidad
                    $this->info("Notificación enviada a: {$user->name}");
                } catch (\Exception $e) {
                    Log::error("Error enviando notificación a {$user->name}: " . $e->getMessage());
                }
            }
        } else {
            Log::warning('Hay productos con bajo stock, pero no se encontraron usuarios con los roles requeridos para notificar.');
            $this->warn('No se encontraron usuarios con los roles especificados.');
        }

        Log::info('Comando app:stock-reposition finalizado exitosamente.');
    }
}