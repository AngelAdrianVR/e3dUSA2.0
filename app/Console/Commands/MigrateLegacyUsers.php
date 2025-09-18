<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateLegacyUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates users and their employee details from the old database to the new structure.';

    /**
     * Mapeo de días de la semana de la estructura antigua (numérica) a la nueva (string).
     * @var array
     */
    private $dayMapping = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de usuarios desde la base de datos antigua...");

        try {
            // Usamos las conexiones definidas en config/database.php
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql'); // Conexión por defecto

            $old_users = $oldDb->table('users')
                ->orderBy('id')
                ->where('id', '>', 3) // omite los primeros 3
                ->get();

            if ($old_users->isEmpty()) {
                $this->warn("No se encontraron usuarios en la base de datos antigua. Proceso terminado.");
                return 0;
            }

            $this->info("Se encontraron " . $old_users->count() . " usuarios para migrar.");
            $progressBar = $this->output->createProgressBar($old_users->count());

            // Usamos una transacción para asegurar la integridad de los datos.
            $newDb->transaction(function () use ($old_users, $newDb, $progressBar) {
                foreach ($old_users as $user) {
                    
                    // --- 1. Migrar a la tabla `users` ---
                    $active_alerts = null;

                    $newDb->table('users')->insert([
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'password' => $user->password,
                        // ... (resto de los campos de usuario)
                        'two_factor_secret' => $user->two_factor_secret,
                        'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                        'remember_token' => $user->remember_token,
                        'current_team_id' => $user->current_team_id,
                        'profile_photo_path' => $user->profile_photo_path,
                        'is_active' => $user->is_active,
                        'active_alerts' => $active_alerts,
                        'disabled_at' => $user->disabled_at,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'active_status' => $user->active_status,
                        'avatar' => $user->avatar,
                        'dark_mode' => $user->dark_mode,
                        'messenger_color' => $user->messenger_color
                    ]);

                    // --- 2. Migrar a la tabla `employee_details` ---
                    if (!empty($user->employee_properties)) {
                        $employee_data = json_decode($user->employee_properties, true);

                        if (json_last_error() === JSON_ERROR_NONE) {
                            
                            $transformedWorkDays = null;
                            if (isset($employee_data['work_days']) && is_array($employee_data['work_days'])) {
                                $transformedWorkDays = $this->transformWorkDays($employee_data['work_days']);
                            }

                            $newDb->table('employee_details')->insert([
                                'user_id' => $user->id,
                                'week_salary' => $employee_data['salary']['week'] ?? 0,
                                'birthdate' => $employee_data['birthdate']['raw'] ?? null,
                                'join_date' => $employee_data['join_date'] ?? null,
                                'job_position' => $employee_data['job_position'] ?? null,
                                'department' => $employee_data['department'] ?? null,
                                'hours_per_week' => $employee_data['hours_per_week'] ?? 0,
                                'work_days' => json_encode($transformedWorkDays),
                                'vacations' => isset($employee_data['vacations']) ? json_encode($employee_data['vacations']) : null,
                                'created_at' => $user->created_at,
                                'updated_at' => $user->updated_at,
                            ]);
                        } else {
                            $this->warn("\nADVERTENCIA: JSON inválido para el usuario ID {$user->id}. Se omite la migración de sus detalles.");
                            Log::warning("Error al decodificar JSON para usuario antiguo ID: {$user->id}");
                        }
                    }
                    $progressBar->advance();
                }
            });
            
            $progressBar->finish();
            $this->info("\n\n¡MIGRACIÓN COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los usuarios y sus detalles han sido transferidos a la nueva base de datos.");

        } catch (\Exception $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de usuarios legacy: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * Transforma el array de work_days de la estructura antigua a la nueva.
     *
     * @param array $oldWorkDays
     * @return array
     */
    private function transformWorkDays(array $oldWorkDays): array
    {
        $newWorkDays = [];

        // Aseguramos que los 7 días de la semana estén presentes en la nueva estructura
        for ($i = 0; $i < 7; $i++) {
            $dayName = $this->dayMapping[$i] ?? 'Desconocido';
            $newWorkDays[$dayName] = [
                'day' => $dayName,
                'works' => false,
                'start_time' => null,
                'end_time' => null,
                'break_minutes' => 0,
            ];
        }

        foreach ($oldWorkDays as $day) {
            $dayIndex = $day['day'] ?? -1;
            if (array_key_exists($dayIndex, $this->dayMapping)) {
                $dayName = $this->dayMapping[$dayIndex];
                
                // Un empleado trabaja si tiene hora de entrada y salida
                $works = !empty($day['check_in']) && !empty($day['check_out']);

                $newWorkDays[$dayName] = [
                    'day' => $dayName,
                    'works' => $works,
                    'start_time' => $day['check_in'] ?? null,
                    'end_time' => $day['check_out'] ?? null,
                    'break_minutes' => $day['break'] ?? 0,
                ];
            }
        }
        // Devolvemos solo los valores para tener un array indexado numéricamente, como en el Factory.
        return array_values($newWorkDays);
    }
}

