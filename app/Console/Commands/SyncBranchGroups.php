<?php

namespace App\Console\Commands;

use App\Services\BranchGroupService;
use Illuminate\Console\Command;
use Throwable;

class SyncBranchGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-branch-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolida los productos (e historial de precios) de todas las sucursales que comparten un mismo grupo.';

    /**
     * Execute the console command.
     */
    public function handle(BranchGroupService $branchGroups): int
    {
        $this->info('Consolidando productos por grupo de clientes...');

        try {
            $total = $branchGroups->rebalanceAll();

            $this->info("¡Listo! Se consolidaron {$total} grupo(s).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error al consolidar los grupos: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
