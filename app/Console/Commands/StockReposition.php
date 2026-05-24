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
        // - Que no estén obsoletos (archived_at es null)
        // - Que su stock mínimo sea mayor a 0 (Ignora los que tienen 0)
        // - Que se puedan comprar (is_purchasable = true)
        // - Que sean productos padre (parent_id is null)
        $candidates = Product::with('storages')
            ->whereNull('archived_at')      
            ->where('min_quantity', '>', 0) 
            ->where('is_purchasable', true) // <-- Solo productos comprables
            ->whereNull('parent_id')        // <-- Solo productos padre
            ->get();

        // 2. Filtrar aquellos cuyo stock total sea menor o igual al mínimo
        $lowStockProducts = $candidates->filter(function ($product) {
            // Sumamos el stock de todos los almacenes (relación morphMany)
            $currentStock = $product->storages->sum('quantity');
            
            // Comparamos contra el mínimo establecido en el producto.
            return $currentStock <= $product->min_quantity;
        });

        if ($lowStockProducts->isEmpty()) {
            Log::info('No se encontraron productos con bajo stock.');
            $this->info('No hay productos con bajo stock.');
            return;
        }

        $count = $lowStockProducts->count();
        Log::info("Se encontraron {$count} productos con bajo stock.");

        // =========================================================
        // 3. Obtener los usuarios a notificar
        // =========================================================

        // ---> MODO PRUEBAS <---
        // (Descomenta esto para pruebas y asegúrate de que el bloque de Producción esté comentado)
        // $usersToNotify = User::where('email', 'angelvazquez470@gmail.com')->get();

        // ---> MODO PRODUCCIÓN <---
        // (Descomenta este bloque en producción y comenta el de pruebas)
        
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
      
        // =========================================================

        // 4. Enviar notificaciones
        if ($usersToNotify->isNotEmpty()) {
            $notification = new StockRepositionNotification($lowStockProducts);

            foreach ($usersToNotify as $user) {
                try {
                    $user->notify($notification);
                    // Logueamos a quién se envió para tener trazabilidad
                    $this->info("Notificación enviada a: {$user->name} ({$user->email})");
                } catch (\Exception $e) {
                    Log::error("Error enviando notificación a {$user->name}: " . $e->getMessage());
                }
            }
        } else {
            Log::warning('Hay productos con bajo stock, pero no se encontraron usuarios requeridos para notificar (o el correo de prueba no existe como User).');
            $this->warn('No se encontraron usuarios para notificar.');
        }

        Log::info('Comando app:stock-reposition finalizado exitosamente.');
    }
}