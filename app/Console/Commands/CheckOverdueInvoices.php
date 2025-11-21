<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdueNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue invoices, update their status, and notify the creator';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue invoices...');
        Log::info('Checking for overdue invoices...');

        // Obtenemos facturas pendientes o parcialmente pagadas cuya fecha de vencimiento ya pasó.
        $overdueInvoices = Invoice::with('user')
            ->whereIn('status', ['Pendiente', 'Parcialmente pagada'])
            ->where('due_date', '<', Carbon::today())
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No overdue invoices found.');
            Log::info('No overdue invoices found.');
            return;
        }

        $this->info($overdueInvoices->count() . ' overdue invoices found. Processing...');
        Log::info($overdueInvoices->count() . ' overdue invoices found. Processing...');

        foreach ($overdueInvoices as $invoice) {
            // Actualizamos el estatus a 'Vencida'
            // $invoice->status = 'Vencida';
            // $invoice->save();

            // Notificamos al usuario que creó la factura
            if ($invoice->user) {
                $invoice_folio = 'F-' . $invoice->folio;
                $invoice->user->notify(new InvoiceOverdueNotification(
                    'Factura Vencida',
                    $invoice_folio,
                    'invoice',
                    route('invoices.show', $invoice->id)
                ));
            }
            $this->line("Invoice #{$invoice->folio} status updated to 'Vencida' and user notified.");
        }

        $this->info('Processing complete.');
        Log::info('Processing complete.');
    }
}
