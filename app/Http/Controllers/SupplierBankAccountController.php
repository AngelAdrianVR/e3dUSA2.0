<?php

namespace App\Http\Controllers;

use App\Models\SupplierBankAccount;
use Illuminate\Http\Request;

class SupplierBankAccountController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_holder' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'clabe' => 'nullable|string|max:22',
            'currency' => 'required|string|in:MXN,USD',
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        SupplierBankAccount::create($validated);

        return back()->with('success', 'Cuenta bancaria creada exitosamente.');
    }

    public function update(Request $request, SupplierBankAccount $bankAccount)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_holder' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'clabe' => 'nullable|string|max:22',
            'currency' => 'required|string|in:MXN,USD',
        ]);

        $bankAccount->update($validated);

        return back()->with('success', 'Cuenta bancaria actualizada exitosamente.');
    }

    public function destroy(SupplierBankAccount $bankAccount)
    {
        $bankAccount->delete();

        return back()->with('success', 'Cuenta bancaria eliminada exitosamente.');
    }
}
