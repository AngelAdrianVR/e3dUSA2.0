<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClearOldNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:clear-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes notifications that are older than 40 days.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to clear old notifications...');

        // Establecer la fecha límite (hace 40 días)
        $cutoffDate = Carbon::now()->subDays(40);

        // Eliminar las notificaciones antiguas usando el Query Builder para mejor rendimiento
        $deletedCount = DB::table('notifications')
            ->where('created_at', '<=', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            $this->info("Successfully deleted {$deletedCount} notifications older than 40 days.");
        } else {
            $this->info('No old notifications found to delete.');
        }

        $this->info('Notification cleanup process completed.');

        return self::SUCCESS;
    }
}
