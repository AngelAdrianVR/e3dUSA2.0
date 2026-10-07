<?php

namespace App\Console\Commands;

use App\Models\DesignAuthorization;
use App\Models\DesignOrder;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SampleTracking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;

class MigrateAuthorizationAudits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audits:migrate-authorizations {--dry-run : Solo cuenta los registros afectados sin modificar la base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mueve las autorizaciones antiguas (registradas como "updated") a la pestaña de Autorizaciones (evento "authorized").';

    /**
     * Modelos que cuentan con un flujo de autorización.
     */
    private const AUTHORIZABLE_TYPES = [
        Quote::class,
        Sale::class,
        Purchase::class,
        DesignOrder::class,
        DesignAuthorization::class,
        SampleTracking::class,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;
        $byModule = [];

        $this->info($dryRun
            ? 'Buscando autorizaciones antiguas (modo de prueba)...'
            : 'Migrando autorizaciones antiguas...');

        Audit::query()
            ->where('event', 'updated')
            ->whereIn('auditable_type', self::AUTHORIZABLE_TYPES)
            ->orderBy('id')
            ->chunkById(500, function ($audits) use (&$total, &$byModule, $dryRun) {
                foreach ($audits as $audit) {
                    if (! $this->isAuthorization($audit)) {
                        continue;
                    }

                    if (! $dryRun) {
                        $audit->update(['event' => 'authorized']);
                    }

                    $type = class_basename($audit->auditable_type);
                    $byModule[$type] = ($byModule[$type] ?? 0) + 1;
                    $total++;
                }
            });

        if ($total === 0) {
            $this->info('No se encontraron autorizaciones antiguas por migrar.');
            return;
        }

        foreach ($byModule as $module => $count) {
            $this->line("  - {$module}: {$count}");
        }

        $message = $dryRun
            ? "DRY RUN: se migrarían {$total} autorizaciones (no se modificó ningún registro)."
            : "Listo. Se migraron {$total} autorizaciones al evento \"authorized\".";

        $this->info($message);
        Log::info($message, ['total' => $total, 'por_modulo' => $byModule]);
    }

    /**
     * Una auditoría corresponde a una autorización cuando el registro pasó
     * de "sin autorizar" a "autorizado".
     */
    private function isAuthorization(Audit $audit): bool
    {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];

        return ! empty($newValues['authorized_at']) && empty($oldValues['authorized_at']);
    }
}
