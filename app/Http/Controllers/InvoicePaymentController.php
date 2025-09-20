<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class InvoicePaymentController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request, Invoice $invoice)
    {
        // 1. Validar los datos de entrada
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', function ($attribute, $value, $fail) use ($invoice) {
                // El monto no puede ser mayor a lo que falta por pagar
                // Se usa round() para evitar problemas de precisión con decimales
                if (round($value, 2) > round($invoice->getPendingAmountAttribute(), 2)) {
                    $fail('El monto del pago no puede ser mayor al saldo pendiente.');
                }
            }],
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'media' => 'nullable|array', // Valida que 'media' sea un arreglo si existe
            // 'media.*' => 'file|mimes:jpg,jpeg,png,pdf,xml|max:4096' // Valida cada archivo en el arreglo
        ]);

        // 2. Crear el registro del pago
        $payment = $invoice->payments()->create([
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'],
        ]);
        
        // 3. Adjuntar archivos al pago si existen
        // Nota: Asegúrate de que tu modelo InvoicePayment use el trait HasMedia de Spatie.
        if ($request->hasFile('media')) {
            $payment->addMultipleMediaFromRequest(['media'])->each(function ($fileAdder) {
                $fileAdder->toMediaCollection('payment_proofs');
            });
        }

        // 4. Recalcular montos y actualizar estado de la factura
        $invoice->refresh(); // Recarga la relación de pagos para incluir el recién creado
        $pendingAmount = $invoice->getPendingAmountAttribute();

        if ($pendingAmount <= 0) {
            $invoice->update([
                'status' => 'Pagada',
                'paid_at' => now(), // Marcar la fecha de pago completo
            ]);
        } else {
            // Si aún queda monto pendiente, se marca como 'Parcialmente pagada'
            $invoice->update([
                'status' => 'Parcialmente pagada',
            ]);
        }

        // 5. Redireccionar a la vista de detalles de la factura con un mensaje de éxito
        return Redirect::route('invoices.show', $invoice->id)->with('success', 'Pago registrado correctamente.');
    }

    public function show(InvoicePayment $invoicePayment)
    {
        //
    }

    public function edit(InvoicePayment $invoicePayment)
    {
        //
    }

    public function update(Request $request, InvoicePayment $invoicePayment)
    {
        //
    }

    public function destroy(InvoicePayment $invoicePayment)
    {
        //
    }
}
