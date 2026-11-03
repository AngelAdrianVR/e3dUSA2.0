<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

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
            'prefix' => 'nullable|string|max:50',
            'area' => 'required|string|in:Comercial,Finanzas,Pagos',
            'birthdate' => 'nullable|date',
            'details' => 'required|array|min:1',
            'details.*.type' => 'required|string|in:Correo,Teléfono,Whatsapp',
            'details.*.value' => 'required|string',
            'details.*.is_primary' => 'nullable|boolean',
        ]);

        // Validamos que exista al menos un teléfono y un correo en los detalles.
        $this->validateContactDetails($request->input('details', []));

        try {
            // Obtenemos la clase del modelo padre a partir del request.
            $contactableModelClass = $request->contactable_type;

            // Verificamos que la clase exista para evitar errores.
            if (!class_exists($contactableModelClass)) {
                return back()->with('error', 'El tipo de modelo relacionado no es válido.');
            }
            
            // Buscamos la instancia del modelo padre (ej. la sucursal o el cliente).
            $contactable = $contactableModelClass::findOrFail($request->contactable_id);

            // Candado de contactos repetidos (desde el detalle del cliente o proveedor).
            $this->validateNoDuplicatedContactDetails($contactable, $request->input('details', []));

            DB::transaction(function () use ($request, $contactable) {
                // Creamos el contacto usando la relación polimórfica.
                // Eloquent se encargará de asignar contactable_id y contactable_type automáticamente.
                $contact = $contactable->contacts()->create($request->only('name', 'charge', 'birthdate', 'prefix', 'area'));
                
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
            'prefix' => 'nullable|string|max:50',
            'area' => 'required|string|in:Comercial,Finanzas,Pagos',
            'birthdate' => 'nullable|date',
            'details' => 'required|array|min:1',
            'details.*.type' => 'required|string|in:Correo,Teléfono,Whatsapp',
            'details.*.value' => 'required|string',
            'details.*.is_primary' => 'nullable|boolean',
        ]);

        // Validamos que exista al menos un teléfono y un correo en los detalles.
        $this->validateContactDetails($request->input('details', []));

        // Candado de contactos repetidos: se ignora el propio contacto al editar.
        $this->validateNoDuplicatedContactDetails($contact->contactable, $request->input('details', []), $contact->id);

        DB::transaction(function () use ($request, $contact) {
            $contact->update($request->only('name', 'charge', 'birthdate', 'prefix', 'area'));
            
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
     * Valida que los detalles del contacto incluyan al menos un teléfono y un correo.
     */
    private function validateContactDetails(array $details): void
    {
        $collection = collect($details);

        $hasPhone = $collection->contains(fn ($detail) =>
            ($detail['type'] ?? null) === 'Teléfono' && trim((string) ($detail['value'] ?? '')) !== ''
        );

        $hasEmail = $collection->contains(fn ($detail) =>
            ($detail['type'] ?? null) === 'Correo' && trim((string) ($detail['value'] ?? '')) !== ''
        );

        $missing = [];
        if (!$hasPhone) $missing[] = 'un teléfono';
        if (!$hasEmail) $missing[] = 'un correo';

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'details' => 'Debes agregar al menos ' . implode(' y ', $missing) . ' en los detalles de contacto.',
            ]);
        }
    }

    /**
     * Impide repetir el mismo teléfono o correo en otro contacto del mismo cliente
     * cuando el candado está activo (Configuraciones de Roles y Permisos).
     */
    private function validateNoDuplicatedContactDetails(?object $contactable, array $details, ?int $ignoreContactId = null): void
    {
        // El candado aplica a los clientes (branches); otros contactables no se modifican.
        if (!$contactable instanceof \App\Models\Branch || Setting::getBool(Setting::ALLOW_DUPLICATE_CONTACTS)) {
            return;
        }

        $phones = collect($details)
            ->where('type', 'Teléfono')
            ->map(fn ($detail) => trim((string) ($detail['value'] ?? '')))
            ->filter()
            ->values()
            ->all();

        $emails = collect($details)
            ->where('type', 'Correo')
            ->map(fn ($detail) => strtolower(trim((string) ($detail['value'] ?? ''))))
            ->filter()
            ->values()
            ->all();

        if (empty($phones) && empty($emails)) {
            return;
        }

        $siblings = $contactable->contacts()
            ->when($ignoreContactId, fn ($query) => $query->where('id', '!=', $ignoreContactId))
            ->with('details')
            ->get();

        foreach ($siblings as $sibling) {
            foreach ($sibling->details as $detail) {
                $value = trim((string) $detail->value);

                if ($detail->type === 'Teléfono' && in_array($value, $phones, true)) {
                    throw ValidationException::withMessages([
                        'details' => "El teléfono {$value} ya está registrado en el contacto «{$sibling->name}». Corrige el dato para continuar.",
                    ]);
                }

                if ($detail->type === 'Correo' && in_array(strtolower($value), $emails, true)) {
                    throw ValidationException::withMessages([
                        'details' => "El correo {$value} ya está registrado en el contacto «{$sibling->name}». Corrige el dato para continuar.",
                    ]);
                }
            }
        }
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
