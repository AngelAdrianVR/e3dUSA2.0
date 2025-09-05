<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\SupplierContact;
use Illuminate\Http\Request;

class SupplierContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        // Si se marca como principal, desmarcar cualquier otro contacto del mismo proveedor
        if ($request->is_primary) {
            SupplierContact::where('supplier_id', $request->supplier_id)
                           ->update(['is_primary' => false]);
        }

        SupplierContact::create($validated);

        return back()->with('success', 'Contacto creado exitosamente.');
    }

    public function update(Request $request, SupplierContact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
        ]);

        // Si se marca como principal, desmarcar cualquier otro contacto del mismo proveedor
        if ($request->is_primary) {
             SupplierContact::where('supplier_id', $contact->supplier_id)
                   ->where('id', '!=', $contact->id)
                   ->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return back()->with('success', 'Contacto actualizado exitosamente.');
    }

    public function destroy(SupplierContact $contact)
    {
        $contact->delete();

        return back()->with('success', 'Contacto eliminado exitosamente.');
    }
}
