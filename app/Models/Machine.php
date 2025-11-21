<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute; // Importa la clase Attribute

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Machine extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'id',
        'name',
        'serial_number',
        'weight',
        'width',
        'large',
        'height',
        'cost',
        'supplier',
        'adquisition_date',
        'days_next_maintenance',
    ];

    protected $casts = [
        'adquisition_date' => 'date',
    ];

    protected $appends = ['needs_maintenance'];

    /**
     * Determina si la máquina necesita mantenimiento.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function needsMaintenance(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Precaución: Asegúrate de que la relación 'maintenances' esté cargada
                // para evitar el problema N+1.
                if (!$this->relationLoaded('maintenances') || !$this->maintenances->count()) {
                    return true;
                }

                $lastMaintenanceDate = $this->maintenances->max('created_at');
                
                // Si no hay fecha de último mantenimiento, necesita uno.
                if (!$lastMaintenanceDate) {
                    return true;
                }

                $nextMaintenanceDate = $lastMaintenanceDate->addDays($this->days_next_maintenance);

                // Retorna true si la fecha de próximo mantenimiento es hoy o ya pasó.
                return now()->startOfDay()->gte($nextMaintenanceDate->startOfDay());
            }
        );
    }

    // // Método para verificar si necesita mantenimiento
    // public function needsMaintenance()
    // {
    //     if (!$this->maintenances->count()) {
    //         // La máquina no tiene mantenimientos registrados, por lo que necesita mantenimiento.
    //         return true;
    //     }

    //     $lastMaintenanceDate = $this->maintenances->max('created_at');
    //     $daysUntilMaintenance = $lastMaintenanceDate->addDays($this->days_next_maintenance)->diffInDays(now());

    //     return $daysUntilMaintenance <= 0;
    // }

    // relationships
    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function spareParts()
    {
        return $this->hasMany(SparePart::class);
    }

}
