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
                if ($value > $invoice->getPendingAmountAttribute()) {
                    $fail('El monto del pago no puede ser mayor al saldo pendiente.');
                }
            }],
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // 2. Crear el registro del pago
        $invoice->payments()->create([
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'],
        ]);

        // 3. Actualizar el estado de la factura si ya fue pagada en su totalidad
        if ($invoice->getPendingAmountAttribute() <= 0) {
            $invoice->update([
                'status' => 'Pagada',
                'paid_at' => now(), // Marcar la fecha de pago completo
            ]);
        }

        // 4. Redireccionar con un mensaje de éxito
        return Redirect::route('invoices.index')->with('success', 'Pago registrado correctamente.');
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
