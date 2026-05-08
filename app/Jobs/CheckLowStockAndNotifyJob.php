<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\User;
use App\Mail\LowStockNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CheckLowStockAndNotifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $sale;

    /**
     * Create a new job instance.
     */
    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Iniciando revisión de stock bajo para la Orden de Venta #{$this->sale->id}");
        
        $lowStockProducts = collect();

        // Cargamos los productos y componentes de la venta para revisar el stock
        $this->sale->load(['saleProducts.product.storages', 'saleProducts.product.components.storages']);

        foreach ($this->sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;
            if (!$product) continue;

            // 1. Revisar stock del producto principal (usamos sum() igual que en el comando)
            $stockFinishedProduct = $product->storages->sum('quantity');

            // Ignoramos si el minimo es 0 (como en tu comando)
            if (!is_null($product->min_quantity) && $product->min_quantity > 0 && $stockFinishedProduct <= $product->min_quantity) {
                $product->current_stock = $stockFinishedProduct; // Agregamos un atributo temporal para el correo
                $lowStockProducts->put($product->id, $product);
            }

            // 2. Revisar stock de los componentes
            if ($product->components->isNotEmpty()) {
                foreach ($product->components as $component) {
                    // Usamos sum() igual que en el comando
                    $stockComponent = $component->storages->sum('quantity');

                    if (!is_null($component->min_quantity) && $component->min_quantity > 0 && $stockComponent <= $component->min_quantity) {
                        $component->current_stock = $stockComponent;
                        $lowStockProducts->put($component->id, $component);
                    }
                }
            }
        }

        // Si hay productos con stock bajo, enviamos el correo
        if ($lowStockProducts->isNotEmpty()) {
            try {
                // Obtenemos los correos por rol (Corregido: "Super Administrador" con mayúscula)
                $users = User::role(['Super Administrador', 'Almacenista', 'Vendedor', 'Compras'])->where('is_active', true)->get();
                $emails = $users->pluck('email')->toArray();
                
                // Agregamos el correo de prueba específico
                $emails[] = 'angelvazquez470@gmail.com';
                
                // Eliminamos duplicados por si un usuario tiene más de un rol o es el correo de prueba
                $emails = array_unique($emails);

                if (!empty($emails)) {
                    Mail::to($emails)->send(new LowStockNotificationMail($this->sale, $lowStockProducts));
                    Log::info("Notificación de stock bajo enviada correctamente a: " . implode(', ', $emails));
                }
            } catch (\Exception $e) {
                Log::error("Error crítico al enviar notificación de stock bajo en la orden {$this->sale->id}: " . $e->getMessage());
            }
        } else {
            Log::info("La orden #{$this->sale->id} fue procesada pero ningún producto llegó a su límite de stock mínimo.");
        }
    }
}