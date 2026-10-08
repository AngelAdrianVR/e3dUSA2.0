<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use OwenIt\Auditing\Models\Audit; // Importa el modelo del paquete

class AuditController extends Controller
{
    /**
     * Único usuario autorizado a ver las modificaciones de horas en nómina.
     */
    private const HOURS_AUDIT_USER_ID = 1;

    public function index(Request $request)
    {
        $canViewHoursAudits = auth()->id() === self::HOURS_AUDIT_USER_ID;

        $event = $request->input('event');

        // Las modificaciones de horas en nómina solo son visibles para el usuario autorizado
        if ($event === 'hours_updated' && !$canViewHoursAudits) {
            $event = null;
        }

        // Filtros disponibles: pestaña (event), módulo, usuario, ID del registro auditado y rango de fechas
        $filters = [
            'event' => $event,
            'module' => $request->input('module'),
            'user_id' => $request->input('user_id'),
            'record_id' => is_numeric($request->input('record_id')) ? (int) $request->input('record_id') : null,
            'date_from' => $this->parseDate($request->input('date_from')),
            'date_to' => $this->parseDate($request->input('date_to')),
        ];

        $audits = $this->applyFilters(Audit::query(), $filters, $canViewHoursAudits)
            ->with('user:id,name,profile_photo_path') // Traemos la relación con el usuario para obtener su nombre e imagen
            ->latest() // Ordenamos por los más recientes
            ->when($filters['event'], function ($query, $event) {
                // Filtramos por el evento si se especifica uno
                $query->where('event', $event);
            })
            ->paginate(100) // Paginamos los resultados
            ->withQueryString(); // Mantenemos los filtros en los enlaces de paginación

        return Inertia::render('Audit/Index', [
            'audits' => $audits,
            'filters' => $filters,
            'canViewHoursAudits' => $canViewHoursAudits,
            'modules' => $this->applyVisibilityScope(Audit::query(), $canViewHoursAudits)
                ->select('auditable_type')
                ->distinct()
                ->orderBy('auditable_type')
                ->pluck('auditable_type'),
            'users' => User::query()
                ->whereIn('id', Audit::query()
                    ->select('user_id')
                    ->where('user_type', (new User)->getMorphClass())
                    ->whereNotNull('user_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Oculta las modificaciones de horas en nómina a los usuarios no autorizados.
     */
    private function applyVisibilityScope(Builder $query, bool $canViewHoursAudits): Builder
    {
        if (!$canViewHoursAudits) {
            $query->where('event', '!=', 'hours_updated');
        }

        return $query;
    }

    /**
     * Aplica los filtros de módulo, usuario, registro auditado y rango de fechas.
     */
    private function applyFilters(Builder $query, array $filters, bool $canViewHoursAudits): Builder
    {
        $this->applyVisibilityScope($query, $canViewHoursAudits);

        return $query
            ->when($filters['module'], function ($query, $module) {
                $query->where('auditable_type', $module);
            })
            ->when($filters['user_id'], function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($filters['record_id'], function ($query, $recordId) {
                $query->where('auditable_id', $recordId);
            })
            ->when($filters['date_from'], function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'], function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            });
    }

    /**
     * Valida que la fecha recibida tenga el formato Y-m-d; de lo contrario se ignora el filtro.
     */
    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}