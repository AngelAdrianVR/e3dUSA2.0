<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\User;
use App\Notifications\InactiveClientsNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class NotifyInactiveClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:notify-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find inactive clients and notify the assigned sales user.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting the process to notify about inactive clients...');

        // Obtenemos solo los vendedores que tienen clientes asignados para optimizar la consulta.
        $salesUsers = $this->getSalesUsersWithClients();

        if ($salesUsers->isEmpty()) {
            $this->info('No sales users with assigned clients found. Process finished.');
            return self::SUCCESS;
        }

        $this->info("Found {$salesUsers->count()} sales user(s) to check.");

        // Iterar sobre cada vendedor para notificarles sobre sus clientes inactivos
        foreach ($salesUsers as $user) {
            // Obtener los clientes inactivos del vendedor actual
            $inactiveClients = $this->getInactiveClientsForUser($user);

            // Si hay clientes inactivos, enviar notificación al vendedor
            if ($inactiveClients->isNotEmpty()) {
                // Aquí se envía la notificación al usuario.
                // Asegúrate de que el modelo User use el trait Notifiable.
                $user->notify(new InactiveClientsNotification($inactiveClients));
                
                $this->info("Notification sent successfully to: {$user->name} with {$inactiveClients->count()} inactive client(s).");
                Log::info("Inactive clients notification sent to user {$user->id} ({$user->name}). Found {$inactiveClients->count()} inactive client(s).");
            } else {
                 $this->line("User {$user->name} has no inactive clients.");
            }
        }

        $this->info('Inactive clients notification process completed successfully.');
        return self::SUCCESS;
    }

    /**
     * Get all active sales users who have clients.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getSalesUsersWithClients()
    {
        // Se busca por rol o por el departamento en la nueva tabla 'employee_details'.
        return User::query()
            ->where('is_active', true) // Asegurarse que el usuario esté activo
            ->where(function (Builder $query) {
                // Se busca en la tabla relacionada 'employee_details' que el departamento sea 'Ventas'
                $query->whereHas('employeeDetail', function (Builder $q) {
                    $q->where('department', 'Ventas');
                })
                      // O se busca por el rol 'Vendedor' si se usa un sistema de roles
                      ->orWhereHas('roles', fn(Builder $q) => $q->where('name', 'Vendedor'));
            })
            ->whereHas('managedBranches') // Solo usuarios con sucursales asignadas como 'account_manager'
            ->get();
    }

    /**
     * Get all inactive client branches for a given user.
     *
     * @param  User $user
     * @return \Illuminate\Support\Collection
     */
    protected function getInactiveClientsForUser(User $user)
    {
        $inactiveClients = collect();

        // Obtenemos las sucursales que el vendedor gestiona
        $branches = $user->managedBranches ?? collect();

        // Iterar sobre cada sucursal y verificar la inactividad
        foreach ($branches as $branch) {
            $daysToReactivate = (int) $branch->days_to_reactivate;

            // Se verifica inactividad solo si el valor es positivo
            if ($daysToReactivate > 0 && !$this->hasRecentActivity($branch, $daysToReactivate)) {
                $inactiveClients->push($branch);
            }
        }

        return $inactiveClients;
    }

    /**
     * Check if a customer branch has had recent commercial activity.
     *
     * @param  Branch $customerBranch
     * @param  int $daysToReactivate
     * @return bool
     */
    protected function hasRecentActivity(Branch $customerBranch, int $daysToReactivate): bool
    {
        // Se calcula la fecha límite una sola vez
        $threshold = now()->subDays($daysToReactivate)->startOfDay();

        // Se revisa la existencia de actividad en cualquiera de las tres áreas.
        $hasQuotes = $customerBranch->quotes()->where('created_at', '>=', $threshold)->exists();
        if ($hasQuotes) return true;

        $hasSales = $customerBranch->sales()->where('created_at', '>=', $threshold)->exists();
        if ($hasSales) return true;

        // Si no se encontró actividad en ninguna relación, se devuelve false.
        return false;
    }
}
