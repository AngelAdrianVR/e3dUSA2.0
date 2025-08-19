<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Marca una notificación específica como leída.
     */
    public function read(DatabaseNotification $notification)
    {
        // Asegurarse de que el usuario solo pueda marcar sus propias notificaciones
        if (auth()->id() === $notification->notifiable_id) {
            $notification->markAsRead();
        }

        return back();
    }

    /**
     * Marca todas las notificaciones no leídas del usuario como leídas.
     */
    public function readAll()
    {
        auth()->user()->unreadNotifications->markAsRead();
        
        return back();
    }

    /**
     * Elimina una notificación específica.
     *
     * @param  string  $notificationId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($notificationId)
    {
        // Buscamos la notificación que pertenece al usuario autenticado
        $notification = Auth::user()
                            ->notifications()
                            ->where('id', $notificationId)
                            ->first();

        // Si la encontramos, la eliminamos
        if ($notification) {
            $notification->delete();
        }

        // Redirigimos hacia atrás. Inertia se encargará de actualizar el componente.
        return back()->with('success', 'Notificación eliminada.');
    }
}
