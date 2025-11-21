<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Carbon\Carbon;

class MigrateLegacyPayrolls extends Command
{
    /**
     * The name and signature of the console command.
     * N°2. Está todo bien, solo hay que actualizar sueldo , bonos y descuentos de usuarios. hecho.
     * @var string
     */
    protected $signature = 'app:migrate-legacy-payrolls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra datos de nóminas, asistencias e incidencias desde la BD antigua a la nueva estructura.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Nóminas y datos de empleados...");

        // Confirmación para limpiar las tablas nuevas
        $tablesToTruncate = [
            'attendances', 
            'incidents', 
            // 'incident_types',  <- Se omite para no borrar los existentes
            'payrolls', 
            'bonus_employee_detail', 
            'discount_employee_detail', 
            // 'employee_details'
        ];

        if ($this->confirm('¿Deseas limpiar las tablas de nóminas (' . implode(', ', $tablesToTruncate) . ')?', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($tablesToTruncate as $table) {
                    DB::table($table)->truncate();
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas de nóminas han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {

                // Se eliminó la migración de 'incident_types' (justification_events)

                // --- 1. Migrar Nóminas (Payrolls) ---
                $this->line('');
                $this->info('Migrando nóminas...');
                $old_payrolls = $oldDb->table('payrolls')->get();
                $progressBar = $this->output->createProgressBar($old_payrolls->count());
                foreach ($old_payrolls as $payroll) {
                    $newDb->table('payrolls')->insert([
                        'id' => $payroll->id,
                        'week_number' => $payroll->week,
                        'start_date' => $payroll->start_date,
                        'end_date' => Carbon::parse($payroll->start_date)->addDays(6)->toDateString(),
                        'status' => $payroll->is_active ? 'Abierta' : 'Cerrada',
                        'created_at' => $payroll->created_at,
                        'updated_at' => $payroll->updated_at,
                    ]);
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Nóminas migradas.');

                // --- 2. Migrar detalles de empleados, asistencias e incidencias ---
                $this->line('');
                $this->info('Migrando asistencias, incidencias y detalles de empleados...');
                $old_payroll_users = $oldDb->table('payroll_user')->orderBy('date', 'desc')->get();
                $progressBar = $this->output->createProgressBar($old_payroll_users->count());
                
                foreach ($old_payroll_users as $record) {
                    // Decodificamos el JSON con información salarial
                    $additionals = json_decode($record->additionals, true);

                    // Creamos o actualizamos el registro 'employee_details'
                    // Usamos updateOrInsert para tener siempre la info más reciente de salario por empleado
                    $newDb->table('employee_details')->updateOrInsert(
                        ['user_id' => $record->user_id],
                        [
                            'week_salary' => $additionals['salary']['week'] ?? 0,
                            'hours_per_week' => $additionals['hours_per_week'] ?? 0,
                            // 'join_date' podría venir de la tabla 'users', aquí usamos un default
                            'join_date' => now(), 
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ]
                    );

                    // Obtenemos el ID del detalle de empleado que acabamos de crear/actualizar
                    $employeeDetail = $newDb->table('employee_details')->where('user_id', $record->user_id)->first();
                    if (!$employeeDetail) continue;

                    // --- Crear registros de Asistencia (attendances) ---
                    // Entrada
                    if ($record->check_in) {
                        $newDb->table('attendances')->insert([
                            'employee_detail_id' => $employeeDetail->id,
                            'timestamp' => $record->date . ' ' . $record->check_in,
                            'type' => 'entry',
                            'late_minutes' => $record->late > 0 ? $record->late : null,
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ]);
                    }
                    // Salida
                    if ($record->check_out) {
                        $newDb->table('attendances')->insert([
                            'employee_detail_id' => $employeeDetail->id,
                            'timestamp' => $record->date . ' ' . $record->check_out,
                            'type' => 'exit',
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ]);
                    }
                    // Pausas
                    if (!empty($record->pausas)) {
                        $pausas = json_decode($record->pausas, true);
                        if (is_array($pausas)) {
                            foreach ($pausas as $pausa) {
                                if (!empty($pausa['start'])) {
                                     $newDb->table('attendances')->insert([
                                        'employee_detail_id' => $employeeDetail->id,
                                        'timestamp' => $record->date . ' ' . $pausa['start'],
                                        'type' => 'start_break',
                                    ]);
                                }
                                if (!empty($pausa['finish'])) {
                                     $newDb->table('attendances')->insert([
                                        'employee_detail_id' => $employeeDetail->id,
                                        'timestamp' => $record->date . ' ' . $pausa['finish'],
                                        'type' => 'end_break',
                                    ]);
                                }
                            }
                        }
                    }

                    // --- Crear registro de Incidencia ---
                    if ($record->justification_event_id) {
                        $newDb->table('incidents')->insert([
                            'employee_detail_id' => $employeeDetail->id,
                            'payroll_id' => $record->payroll_id,
                            'incident_type_id' => $record->justification_event_id,
                            'date' => $record->date,
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ]);
                    }
                    
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info(' -> Datos de empleados migrados.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE NÓMINAS COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de nóminas: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

