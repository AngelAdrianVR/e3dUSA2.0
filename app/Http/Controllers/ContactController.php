<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContactController extends Controller
{
    /**
     * Almacena un nuevo contacto y lo asocia a un modelo padre (contactable).
     */
    public function store(Request $request)
    {
        $request->validate([
            // Validamos que el tipo de modelo y el ID existan.
            'contactable_id' => 'required|integer',
            'contactable_type' => 'required|string', // Debe ser el namespace completo del modelo, ej: 'App\\Models\\Branch'
            'name' => 'required|string|max:255',
            'charge' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'details' => 'nullable|array',
            'details.*.type' => 'required|string',
            'details.*.value' => 'required|string',
        ]);

        try {
            // Obtenemos la clase del modelo padre a partir del request.
            $contactableModelClass = $request->contactable_type;

            // Verificamos que la clase exista para evitar errores.
            if (!class_exists($contactableModelClass)) {
                return back()->with('error', 'El tipo de modelo relacionado no es válido.');
            }
            
            // Buscamos la instancia del modelo padre (ej. la sucursal o el cliente).
            $contactable = $contactableModelClass::findOrFail($request->contactable_id);

            DB::transaction(function () use ($request, $contactable) {
                // Creamos el contacto usando la relación polimórfica.
                // Eloquent se encargará de asignar contactable_id y contactable_type automáticamente.
                $contact = $contactable->contacts()->create($request->only('name', 'charge', 'birthdate'));
                
                if ($request->has('details')) {
                    foreach ($request->details as $detailData) {
                        $contact->details()->create($detailData);
                    }
                }
            });

        } catch (ModelNotFoundException $e) {
            return back()->with('error', 'El registro padre al que intentas asociar el contacto no existe.');
        }


        return back()->with('success', 'Contacto creado correctamente.');
    }

    /**
     * Actualiza un contacto existente.
     * La lógica aquí no necesita cambiar mucho, ya que solo modifica
     * los datos del contacto y sus detalles, no su relación padre.
     */
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
            
            // Elimina detalles viejos y crea los nuevos para mantenerlos sincronizados.
            $contact->details()->delete();
            if ($request->has('details')) {
                foreach ($request->details as $detailData) {
                    $contact->details()->create($detailData);
                }
            }
        });
        
        return back()->with('success', 'Contacto actualizado.');
    }

    /**
     * Elimina un contacto y sus detalles.
     */
    public function destroy(Contact $contact)
    {
        // Usamos una transacción por si falla la eliminación de detalles.
        DB::transaction(function () use ($contact) {
            $contact->details()->delete();
            $contact->delete();
        });

        return back()->with('success', 'Contacto eliminado.');
    }
}
