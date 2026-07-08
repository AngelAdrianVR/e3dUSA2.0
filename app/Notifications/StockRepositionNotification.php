<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class StockRepositionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Collection $products)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('⚠️ Productos con stock bajo')
            ->greeting('¡Hola!')
            ->line("Se encontraron {$this->products->count()} productos con stock mínimo o insuficiente.")
            ->line('Listado de productos a reponer:');

        // Listamos los productos en el correo (Nombre y Código)
        foreach ($this->products as $product) {
            $stockActual = $product->storages->sum('quantity');
            $mail->line("• [{$product->code}] {$product->name} (Actual: {$stockActual} | Mín: {$product->min_quantity})");
        }

        return $mail
            ->line('Favor de revisar y gestionar la reposición.')
            ->action('Ver Listado Completo', route('stock-reposition.index'))
            ->salutation('Saludos, Sistema ERP');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Basado en la estructura de ApprovalQuoteNotification
        return [
            'title' => 'Alerta de Inventario',
            'folio' => $this->products->count() . ' Prod.', // Usamos el conteo como referencia corta
            'type' => 'stock-reposition',
            
            // --- CORRECCIÓN ---
            // Usamos 'false' como tercer parámetro para forzar una ruta relativa (e.g., "/stock-reposition")
            // en lugar de absoluta ("http://localhost:8000/stock-reposition").
            // Esto soluciona el problema de CORS entre 127.0.0.1 y localhost.
            'url' => route('stock-reposition.index', [], false), 
            
            'message' => 'Productos con stock mínimo. Da clic para ver el listado completo.',
            'icon' => 'fa-solid fa-triangle-exclamation', // Icono de alerta
        ];
    }
}