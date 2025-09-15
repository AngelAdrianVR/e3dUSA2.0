<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un backup de la base de datos MySQL.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando backup de la base de datos...');

        try {
            // Obtener configuración de la base de datos de forma segura
            $connection = config('database.default');
            $dbConfig = config("database.connections.{$connection}");

            if ($dbConfig['driver'] !== 'mysql') {
                $this->error('Este comando solo soporta bases de datos MySQL.');
                return Command::FAILURE;
            }

            $dbName = $dbConfig['database'];
            $dbUser = $dbConfig['username'];
            $dbPass = $dbConfig['password'];
            $dbHost = $dbConfig['host'];
            $dbPort = $dbConfig['port'];

            // Crear el directorio de backups si no existe
            $backupPath = storage_path('app/backups/database');
            File::ensureDirectoryExists($backupPath);

            // Nombre del archivo de backup con fecha y hora
            $fileName = sprintf('%s/%s_%s.sql', $backupPath, $dbName, now()->format('Y-m-d_H-i-s'));
            
            // Construir el comando de mysqldump
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s %s > %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                escapeshellarg($fileName)
            );

            // Ejecutar el proceso pasando la contraseña como variable de entorno por seguridad
            $process = Process::forever()->env(['MYSQL_PWD' => $dbPass])->run($command);

            if ($process->successful()) {
                $this->info('Backup de la base de datos completado exitosamente.');
                $this->info("Guardado en: {$fileName}");
                Log::info("Backup de la base de datos creado exitosamente: {$fileName}");
                return Command::SUCCESS;
            } else {
                throw new Exception($process->errorOutput());
            }
        } catch (Exception $e) {
            $this->error('El backup de la base de datos falló.');
            $this->error("Error: {$e->getMessage()}");
            Log::error("Fallo en el backup de la base de datos: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
