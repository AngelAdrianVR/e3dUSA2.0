<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa las notificaciones generales para el dropdown y la vista completa.
 */
class NotificationCategories
{
    public const VENTAS = 'ventas';

    public const PRODUCCION = 'produccion';

    public const DISENO = 'diseno';

    public const REQUISICIONES = 'requisiciones';

    public const OTROS = 'otros';

    /**
     * Orden y etiqueta visible de cada categoría.
     */
    public const DEFINITIONS = [
        self::VENTAS => 'Ventas',
        self::PRODUCCION => 'Producción',
        self::DISENO => 'Diseño',
        self::REQUISICIONES => 'Requisiciones',
        self::OTROS => 'Otros',
    ];

    /**
     * Notificaciones de tareas de proyectos: tienen su propio dropdown.
     */
    public const PROJECT_TASK_TYPES = [
        'App\Notifications\ProjectAssignedNotification',
        'App\Notifications\ProjectTaskAssignedNotification',
        'App\Notifications\ProjectTaskMentionedNotification',
        'App\Notifications\ProjectTaskDueSoonNotification',
    ];

    /**
     * Clases de notificación asignadas a cada categoría.
     */
    private const TYPES = [
        self::VENTAS => [
            'App\Notifications\ApprovalQuoteNotification',
            'App\Notifications\NewQuoteForApprovalNotification',
            'App\Notifications\SaleAuthorizedNotification',
            'App\Notifications\BillingNotification',
            'App\Notifications\InvoiceOverdueNotification',
            'App\Notifications\NewSampleTrackingNotification',
            'App\Notifications\SampleAuthorizedNotification',
            'App\Notifications\InactiveClientsNotification',
        ],
        self::PRODUCCION => [
            'App\Notifications\PmsTaskAssignedNotification',
            'App\Notifications\TaskAssignedNotification',
            'App\Notifications\NewMaintenanceNotification',
        ],
        self::DISENO => [
            'App\Notifications\designOrderAuthorizedNotification',
            'App\Notifications\DesignOrderAuthorizedNotification',
            'App\Notifications\DesignOrderFinishedNotification',
            'App\Notifications\NewDesignOrderAssignedNotification',
        ],
    ];

    /**
     * Categoría a la que pertenece una notificación según su clase.
     */
    public static function categoryFor(?string $type): string
    {
        if (! $type) {
            return self::OTROS;
        }

        $type = ltrim($type, '\\');

        foreach (self::TYPES as $category => $types) {
            if (in_array($type, $types, true)) {
                return $category;
            }
        }

        // Todas las notificaciones del módulo de requisiciones comparten prefijo.
        if (str_starts_with($type, 'App\Notifications\Requisition')) {
            return self::REQUISICIONES;
        }

        return self::OTROS;
    }

    /**
     * Las tareas de proyectos no forman parte de las notificaciones generales.
     */
    public static function isGeneral(?string $type): bool
    {
        if (! $type) {
            return true;
        }

        return ! in_array(ltrim($type, '\\'), self::PROJECT_TASK_TYPES, true);
    }

    /**
     * Notificaciones generales del usuario, más recientes primero.
     */
    public static function generalNotifications(User $user)
    {
        return $user->notifications()
            ->whereNotIn('type', self::PROJECT_TASK_TYPES)
            ->latest();
    }

    /**
     * Aplica el filtro de categoría a una consulta de notificaciones.
     */
    public static function applyCategoryFilter($query, ?string $category)
    {
        if (! $category || ! array_key_exists($category, self::DEFINITIONS)) {
            return $query;
        }

        if ($category === self::REQUISICIONES) {
            return $query->where('type', 'like', 'App\\\\Notifications\\\\Requisition%');
        }

        if ($category === self::OTROS) {
            return $query
                ->whereNotIn('type', array_merge(...array_values(self::TYPES)))
                ->where('type', 'not like', 'App\\\\Notifications\\\\Requisition%');
        }

        return $query->whereIn('type', self::TYPES[$category] ?? []);
    }

    /**
     * Últimas notificaciones generales, opcionalmente filtradas por categoría.
     */
    public static function latest(User $user, ?string $category = null, int $limit = 30): array
    {
        $query = self::applyCategoryFilter(self::generalNotifications($user), $category);

        return $query
            ->take($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification) => self::serialize($notification))
            ->values()
            ->all();
    }

    /**
     * Conteo de pendientes por categoría (solo notificaciones generales).
     */
    public static function unreadCounts(User $user): array
    {
        $counts = array_fill_keys(array_keys(self::DEFINITIONS), 0);

        $types = $user->unreadNotifications()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        foreach ($types as $type => $total) {
            if (! self::isGeneral($type)) {
                continue;
            }

            $counts[self::categoryFor($type)] += (int) $total;
        }

        return $counts;
    }

    /**
     * Metadatos de categorías con su conteo de pendientes.
     */
    public static function meta(User $user): array
    {
        $counts = self::unreadCounts($user);

        return collect(self::DEFINITIONS)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'unread' => $counts[$key] ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * Formato uniforme para el frontend.
     */
    public static function serialize(DatabaseNotification $notification): array
    {
        $category = self::categoryFor($notification->type);

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'category' => $category,
            'category_label' => self::DEFINITIONS[$category],
        ];
    }

    /**
     * Notificaciones generales paginadas (vista completa).
     */
    public static function paginated(User $user, array $filters = [], int $perPage = 100)
    {
        $query = self::applyCategoryFilter(
            self::generalNotifications($user),
            $filters['category'] ?? null
        );

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['id'])) {
            $search = '%'.trim($filters['id']).'%';

            // El ID corresponde al registro (folio) que originó la notificación,
            // no al UUID de la notificación.
            $query->where(function ($query) use ($search) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.folio')) LIKE ?", [$search])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.sale_folio')) LIKE ?", [$search])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.entry_key')) LIKE ?", [$search])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.entry_id')) LIKE ?", [$search])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.task_id')) LIKE ?", [$search]);
            });
        }

        $notifications = $query->paginate($perPage)->withQueryString();

        return $notifications->through(fn (DatabaseNotification $notification) => self::serialize($notification));
    }
}
