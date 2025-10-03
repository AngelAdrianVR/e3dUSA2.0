<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Quote; // Cambiado de Quotation a Quote según tu modelo
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckPendingQuotations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotations:check-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa cotizaciones pendientes con más de 3 días y notifica a su creador.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando la revisión de cotizaciones pendientes...');
        Log::info('Iniciando la revisión de cotizaciones pendientes...');

        $threeDaysAgo = Carbon::now()->subDays(3);

        // 1. Obtener todas las cotizaciones pendientes agrupadas por su creador (user_id)
        $pendingQuotesByUser = Quote::where('status', 'Esperando respuesta')
            ->where('created_at', '<=', $threeDaysAgo)
            ->get()
            ->groupBy('user_id'); // Agrupamos por el ID del creador

        // 2. Obtener los IDs de todos los usuarios que actualmente tienen la alerta activa
        // Usamos JSON_EXTRACT para buscar la clave 'pending_quotations' en la columna active_alerts
        $usersWithAlert = User::where(DB::raw("JSON_EXTRACT(active_alerts, '$.pending_quotations')"), '!=', null)
                                ->pluck('id')
                                ->toArray();
        
        $usersToKeepAlert = [];

        // 3. Procesar las cotizaciones para cada usuario
        if ($pendingQuotesByUser->isNotEmpty()) {
            foreach ($pendingQuotesByUser as $userId => $quotes) {
                $user = User::find($userId);
                if (!$user) {
                    continue; // Si el usuario no existe, saltar al siguiente
                }

                // Guardamos el ID del usuario para saber que su alerta debe permanecer
                $usersToKeepAlert[] = $userId;

                // 4. Construir el contenido de la alerta para este usuario
                $alertContent = [
                    'type' => 'pending_quotations',
                    'title' => 'Cotizaciones Pendientes',
                    'message' => 'Tienes ' . $quotes->count() . ' cotización(es) sin respuesta por más de 3 días.',
                    'quote_ids' => $quotes->pluck('id')->toArray(), // Guardamos solo los IDs
                ];

                // 5. Usar la función del modelo User para agregar o actualizar la alerta
                $user->addActiveAlert('pending_quotations', $alertContent);
                $this->info("Alerta de cotizaciones pendientes actualizada para el usuario: {$user->name} (ID: {$userId})");
                Log::info("Alerta de cotizaciones pendientes actualizada para el usuario: {$user->name} (ID: {$userId})");
            }
        } else {
            $this->info('No se encontraron cotizaciones pendientes que cumplan con el criterio.');
            Log::info('No se encontraron cotizaciones pendientes que cumplan con el criterio.');
        }

        // 6. Determinar qué usuarios ya no tienen cotizaciones pendientes para limpiar su alerta
        $usersToRemoveAlert = array_diff($usersWithAlert, $usersToKeepAlert);

        if (!empty($usersToRemoveAlert)) {
            foreach ($usersToRemoveAlert as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->removeActiveAlert('pending_quotations');
                    $this->info("Alerta de cotizaciones pendientes eliminada para el usuario: {$user->name} (ID: {$userId})");
                    Log::info("Alerta de cotizaciones pendientes eliminada para el usuario: {$user->name} (ID: {$userId})");
                }
            }
        }

        $this->info('Revisión de cotizaciones finalizada.');
        Log::info('Revisión de cotizaciones finalizada.');
    }
}
