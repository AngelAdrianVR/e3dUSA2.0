<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\User;
use App\Notifications\BillingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class NotifyBillingDepartmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $sale;
    public $type;

    public function __construct(Sale $sale, string $type)
    {
        $this->sale = $sale;
        $this->type = $type;
    }

    public function handle(): void
    {
        // Usando Spatie Permissions para obtener todos los usuarios de cobranza
        $billingUsers = User::role('Cobranza')->get();

        if ($billingUsers->isEmpty()) return;

        $message = '';
        
        // Cambiamos a switch para que sea más fácil agregar futuros casos
        switch ($this->type) {
            case 'pre_invoice_required':
                $message = "La OV-{$this->sale->id} ha sido creada y requiere Pre-factura.";
                break;
            case 'stamping_required':
                $message = "La OV-{$this->sale->id} pasó a 'En Proceso' y requiere Timbrado de Factura.";
                break;
            case 'production_pending_stamping':
                $message = "La OV-{$this->sale->id} entró a Producción y está pendiente de timbrado (ya cuenta con pre-factura).";
                break;
            case 'production_no_pre_invoice':
                $message = "¡Atención! La OV-{$this->sale->id} ya está en Producción y NO se ha registrado un folio de pre-factura.";
                break;
        }

        // Si el tipo no coincide con ninguno o no hay mensaje, no enviamos nada
        if (empty($message)) return;

        // Envía la notificación
        Notification::send($billingUsers, new BillingNotification($this->sale, $message));
    }
}