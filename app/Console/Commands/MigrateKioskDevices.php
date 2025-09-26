<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateKioskDevices extends Command
{
    /**
     * The name and signature of the console command.
     * Todo correcto! en cualquier orden
     * @var string
     */
    protected $signature = 'app:migrate-kiosk-devices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los dispositivos de la tabla antigua "kiosk_devices" a la nueva tabla "authorized_devices".';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Dispositivos (Kiosks)...");

        // Confirmación para limpiar la tabla nueva antes de empezar.
        if ($this->confirm('¿Deseas eliminar TODOS los datos de la tabla "authorized_devices" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                // Desactivamos temporalmente la revisión de llaves foráneas para truncar la tabla.
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('authorized_devices')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('La tabla "authorized_devices" ha sido limpiada.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar la tabla: " . $e->getMessage());
                return 1; // Termina el comando con un código de error.
            }
        }

        try {
            // Define las conexiones a las bases de datos (deben estar configuradas en config/database.php)
            $oldDb = DB::connection('mysql_old'); // Conexión a la BD antigua
            $newDb = DB::connection('mysql');     // Conexión por defecto a la BD nueva

            // Usamos una transacción para asegurar que si algo falla, no se guarde ningún dato a medias.
            $newDb->transaction(function () use ($oldDb, $newDb) {
                
                $this->line('');
                $this->info('Migrando dispositivos...');
                
                // 1. Obtener todos los registros de la tabla antigua.
                $old_devices = $oldDb->table('kiosk_devices')->orderBy('id')->get();
                
                // Crear una barra de progreso para una mejor experiencia visual.
                $progressBar = $this->output->createProgressBar($old_devices->count());

                // 2. Iterar sobre cada registro antiguo y crear uno nuevo.
                foreach ($old_devices as $device) {
                    $newDb->table('authorized_devices')->insert([
                        // Datos mapeados según tus requerimientos.
                        'name' => 'Sin nombre asignado',
                        'identifier' => $device->token, // El 'token' antiguo es el 'identifier' nuevo.
                        'is_active' => true,
                        'created_by' => 1,

                        // Se mantienen las fechas originales de creación y actualización.
                        'created_at' => $device->created_at,
                        'updated_at' => $device->updated_at,
                    ]);
                    // Avanzar la barra de progreso.
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->info(' -> Dispositivos migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE DISPOSITIVOS COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los datos han sido transferidos a la nueva base de datos.");

        } catch (Throwable $e) {
            // En caso de cualquier error, se captura, se muestra en consola y se registra en el log.
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de dispositivos (kiosks): ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1; // Termina el comando con un código de error.
        }

        return 0; // Termina el comando exitosamente.
    }
}
