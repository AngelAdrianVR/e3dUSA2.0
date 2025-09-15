<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class BackupMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-media';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un backup de los archivos en la carpeta storage/app/public.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando backup de los medios...');

        try {
            $sourcePath = storage_path('app/public');

            if (!File::isDirectory($sourcePath)) {
                $this->warn('El directorio storage/app/public no existe. No hay nada que respaldar.');
                return Command::SUCCESS;
            }

            // Se crea una carpeta con la fecha actual para organizar los backups
            $backupDate = now()->format('Y-m-d');
            $destinationPath = storage_path("app/backups/media_{$backupDate}");
            
            File::ensureDirectoryExists($destinationPath);

            $directories = File::directories($sourcePath);

            if (empty($directories)) {
                $this->info('No se encontraron directorios en storage/app/public para respaldar.');
                return Command::SUCCESS;
            }

            $this->output->progressStart(count($directories));

            foreach ($directories as $directory) {
                $dirName = basename($directory);
                $zipFile = escapeshellarg("{$destinationPath}/{$dirName}.zip");
                $sourceDir = escapeshellarg($directory);

                // El comando -j en zip guarda los archivos sin la ruta completa
                $process = Process::run("zip -r -j {$zipFile} {$sourceDir}");

                if (!$process->successful()) {
                    $this->error(" Fallo al comprimir el directorio: {$dirName}");
                    Log::error("Fallo en backup de medios para {$dirName}: " . $process->errorOutput());
                }
                
                $this->output->progressAdvance();
            }

            $this->output->progressFinish();
            $this->info('Backup de medios completado.');
            Log::info("Backup de medios completado en: {$destinationPath}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('El backup de medios falló.');
            $this->error("Error: {$e->getMessage()}");
            Log::error("Fallo en el backup de medios: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
