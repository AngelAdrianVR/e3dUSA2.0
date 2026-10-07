<?php

namespace App\Traits;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;

trait RecordsAuthorizationAudit
{
    /**
     * Toma una "foto" legible de los campos indicados del modelo (las fechas se formatean).
     */
    protected function authorizationSnapshot(Auditable $model, array $fields): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $value = $model->{$field};

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $snapshot[$field] = $value;
        }

        return $snapshot;
    }

    /**
     * Registra la autorización como una acción propia ("authorized") en el historial de acciones,
     * para que aparezca en la pestaña de Autorizaciones.
     */
    protected function recordAuthorizationAudit(Auditable $model, array $oldValues, array $newValues): void
    {
        $model->auditEvent = 'authorized';
        $model->isCustomEvent = true;
        $model->auditCustomOld = $oldValues;
        $model->auditCustomNew = $newValues;

        event(new AuditCustom($model));
    }
}
