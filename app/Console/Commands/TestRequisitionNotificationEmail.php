<?php

namespace App\Console\Commands;

use App\Mail\RequisitionNotificationMail;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\RequisitionAuthorizedNotification;
use App\Notifications\RequisitionCreatedNotification;
use App\Notifications\RequisitionInvoiceUploadedNotification;
use App\Notifications\RequisitionQuotationUploadedNotification;
use App\Support\RequisitionPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestRequisitionNotificationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requisitions:test-notification-email
                            {--to=angelvazquez470@gmail.com : Correo que recibirá la prueba}
                            {--requisition= : ID de la requisición a usar (por defecto la más reciente)}
                            {--stage=factura : Notificación a simular: factura, creacion, cotizacion o autorizacion}
                            {--preview : Solo genera el HTML en storage/app, sin enviar correo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía (o previsualiza) el correo de notificación del módulo de requisiciones para probar la plantilla y el SMTP sin avisar a los Super Administradores.';

    /**
     * Etapas disponibles y la notificación que representa cada una.
     */
    private const STAGES = ['factura', 'creacion', 'cotizacion', 'autorizacion'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $to = (string) $this->option('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("El correo «{$to}» no es válido.");

            return self::FAILURE;
        }

        $stage = (string) $this->option('stage');

        if (! in_array($stage, self::STAGES, true)) {
            $this->error('Etapa no válida. Usa: ' . implode(', ', self::STAGES) . '.');

            return self::FAILURE;
        }

        $requisition = $this->resolveRequisition();
        $notification = $this->buildNotification($stage, $requisition);

        // Mismo contenido que reciben la gerencia de compras o pagos.
        $payload = $notification->toArray($this->resolveNotifiable($requisition));

        $mailable = new RequisitionNotificationMail(
            requisition: $requisition,
            title: $payload['title'] ?? 'Actualización de requisición',
            bodyHtml: $payload['message'] ?? '',
            actionUrl: route('requisitions.show', ['requisition' => $requisition->id]),
        );

        $this->line("Requisición: <info>{$requisition->folio}</info> ({$requisition->type} · {$requisition->status})");
        $this->line("Etapa simulada: <info>{$stage}</info>");
        $this->line("Botón enlaza a: <info>{$mailable->actionUrl}</info>");

        if ($this->option('preview')) {
            return $this->writePreview($mailable);
        }

        $this->line('Mailer: <info>' . config('mail.default') . '</info> · Host: <info>' . config('mail.mailers.smtp.host') . '</info>');
        $this->line('Remitente: <info>' . config('mail.from.address') . '</info>');

        try {
            Mail::to($to)->send($mailable);
        } catch (Throwable $e) {
            $this->error('No se pudo enviar el correo: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Correo de prueba enviado únicamente a {$to}.");

        return self::SUCCESS;
    }

    /**
     * Requisición real (la indicada o la más reciente); si no hay datos se
     * arma una de ejemplo en memoria para poder revisar la plantilla.
     */
    private function resolveRequisition(): Requisition
    {
        $id = $this->option('requisition');

        if ($id !== null) {
            try {
                $requisition = Requisition::with('user:id,name')->find($id);

                if ($requisition) {
                    return $requisition;
                }

                $this->warn("No existe la requisición #{$id}; se usará la más reciente.");
            } catch (Throwable $e) {
                $this->warn('No se pudo consultar la base de datos: ' . $e->getMessage());
            }
        }

        try {
            $requisition = Requisition::with('user:id,name')->latest('id')->first();

            if ($requisition) {
                return $requisition;
            }
        } catch (Throwable $e) {
            $this->warn('No se pudo consultar la base de datos: ' . $e->getMessage());
        }

        $this->warn('No hay requisiciones registradas; se usará una de ejemplo.');

        $requisition = new Requisition([
            'type' => Requisition::TYPE_LOCAL,
            'origin' => Requisition::ORIGIN_WAREHOUSE,
            'status' => 'Pendiente de pago',
            'invoice_amount' => 12500,
            'invoice_currency' => 'MXN',
        ]);
        $requisition->id = 1;
        $requisition->setRelation('user', new User(['name' => 'Usuario de prueba']));

        return $requisition;
    }

    /**
     * Notificación a usar como base del correo.
     */
    private function buildNotification(string $stage, Requisition $requisition)
    {
        return match ($stage) {
            'creacion' => new RequisitionCreatedNotification($requisition),
            'cotizacion' => new RequisitionQuotationUploadedNotification(
                $requisition,
                $requisition->type === Requisition::TYPE_LOCAL
            ),
            'autorizacion' => new RequisitionAuthorizedNotification($requisition),
            default => new RequisitionInvoiceUploadedNotification($requisition),
        };
    }

    /**
     * Las notificaciones calculan su URL según el usuario que las recibe;
     * aquí solo se necesita el título y el mensaje.
     */
    private function resolveNotifiable(Requisition $requisition): User
    {
        if ($requisition->user) {
            return $requisition->user;
        }

        try {
            $admin = RequisitionPermissions::superAdmins()->first();

            if ($admin) {
                return $admin;
            }
        } catch (Throwable $e) {
            // Sin base de datos disponible: se usa un usuario de ejemplo.
        }

        return new User(['name' => 'Usuario de prueba']);
    }

    /**
     * Guarda la plantilla renderizada en storage/app para abrirla en el navegador.
     */
    private function writePreview(RequisitionNotificationMail $mailable): int
    {
        try {
            $html = $mailable->render();
        } catch (Throwable $e) {
            $this->error('No se pudo renderizar la plantilla: ' . $e->getMessage());

            return self::FAILURE;
        }

        // El logo viaja embebido (cid:) para vista previa se incrusta como data URI.
        $logoPath = public_path('images/logo.png');

        if (is_file($logoPath)) {
            $html = preg_replace(
                '/cid:[^"\']+/',
                'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)),
                $html
            );
        }

        $path = storage_path('app/requisition-email-preview.html');
        file_put_contents($path, $html);

        $this->info("Vista previa generada: {$path}");
        $this->line('Ábrela en el navegador para revisar el diseño (no se envió ningún correo).');

        return self::SUCCESS;
    }
}
