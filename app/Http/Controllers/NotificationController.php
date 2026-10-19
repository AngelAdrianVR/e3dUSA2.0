<?php

namespace App\Http\Controllers;

use App\Support\NotificationCategories;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Vista completa de notificaciones generales (100 por página).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'category' => ['nullable', 'in:' . implode(',', array_keys(NotificationCategories::DEFINITIONS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'id' => ['nullable', 'string', 'max:100'],
        ]);

        $filters = [
            'category' => $validated['category'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'id' => $validated['id'] ?? null,
        ];

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationCategories::paginated($user, $filters),
            'categories' => NotificationCategories::meta($user),
            'filters' => $filters,
        ]);
    }

    /**
     * Últimas 30 notificaciones para el dropdown, filtradas por categoría.
     */
    public function dropdown(Request $request)
    {
        $user = Auth::user();
        $category = $request->query('category');

        if (! is_string($category) || ! array_key_exists($category, NotificationCategories::DEFINITIONS)) {
            $category = null;
        }

        return response()->json([
            'notifications' => NotificationCategories::latest($user, $category),
            'categories' => NotificationCategories::meta($user),
        ]);
    }

    /**
     * Marca una notificación específica como leída.
     */
    public function read(Request $request, DatabaseNotification $notification)
    {
        // Asegurarse de que el usuario solo pueda marcar sus propias notificaciones
        if (auth()->id() === $notification->notifiable_id) {
            $notification->markAsRead();
        }

        return $this->notificationActionResponse($request);
    }

    /**
     * Marca todas las notificaciones no leídas del usuario como leídas.
     *
     * El dropdown general envía general_only para no marcar las tareas de proyectos,
     * que se administran en su propio panel.
     */
    public function readAll(Request $request)
    {
        $query = auth()->user()->unreadNotifications();

        if ($request->boolean('general_only')) {
            $query->whereNotIn('type', NotificationCategories::PROJECT_TASK_TYPES);
        }

        $query->update(['read_at' => now()]);

        return $this->notificationActionResponse($request);
    }

    /**
     * Respuesta para acciones ejecutadas con axios (sin recarga de Inertia).
     */
    private function notificationActionResponse(Request $request)
    {
        if (! $request->header('X-Inertia') && ($request->expectsJson() || $request->ajax())) {
            return response()->noContent();
        }

        return back();
    }

    /**
     * Marca notificaciones específicas (por ID) como leídas.
     * Ideal para marcar como leídas solo las de un dropdown específico al abrirlo.
     */
    public function readSelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
        ]);

        $ids = $request->input('ids');

        // Buscamos y marcamos como leídas solo las notificaciones seleccionadas del usuario.
        Auth::user()
            ->unreadNotifications()
            ->whereIn('id', $ids)
            ->update(['read_at' => now()]);

        return $this->notificationActionResponse($request);
    }

    /**
     * Elimina una notificación específica.
     *
     * @param  string  $notificationId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, $notificationId)
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

        if (! $request->header('X-Inertia') && ($request->expectsJson() || $request->ajax())) {
            return response()->noContent();
        }

        // Redirigimos hacia atrás. Inertia se encargará de actualizar el componente.
        return back()->with('success', 'Notificación eliminada.');
    }

    public function destroySelected(Request $request)
    {
        // Validamos que 'ids' sea un arreglo y que sea requerido.
        $request->validate([
            'ids' => 'required|array',
        ]);

        $ids = $request->input('ids');

        // Buscamos y eliminamos solo las notificaciones que pertenecen al usuario autenticado.
        Auth::user()->notifications()->whereIn('id', $ids)->delete();

        if (! $request->header('X-Inertia') && ($request->expectsJson() || $request->ajax())) {
            return response()->noContent();
        }

        return back()->with('success', 'Notificaciones seleccionadas eliminadas.');
    }
}