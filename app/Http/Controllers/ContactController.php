<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'charge' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'details' => 'nullable|array',
            'details.*.type' => 'required|string',
            'details.*.value' => 'required|string',
        ]);

        DB::transaction(function () use ($request) {
            $contact = Contact::create($request->only('branch_id', 'name', 'charge', 'birthdate'));
            
            if ($request->has('details')) {
                foreach ($request->details as $detailData) {
                    $contact->details()->create($detailData);
                }
            }
        });

        return back()->with('success', 'Contacto creado correctamente.');
    }

    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'charge' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'details' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $contact) {
            $contact->update($request->only('name', 'charge', 'birthdate'));
            
            // Elimina detalles viejos y crea los nuevos
            $contact->details()->delete();
            if ($request->has('details')) {
                foreach ($request->details as $detailData) {
                    $contact->details()->create($detailData);
                }
            }
        });
        
        return back()->with('success', 'Contacto actualizado.');
    }

    public function destroy(Contact $contact)
    {
        $contact->details()->delete();
        $contact->delete();
        return back()->with('success', 'Contacto eliminado.');
    }
}
