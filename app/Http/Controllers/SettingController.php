<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Configuraciones que se pueden administrar desde la pantalla de Roles y Permisos.
     */
    private const EDITABLE_SETTINGS = [
        Setting::ALLOW_DUPLICATE_CONTACTS,
    ];

    public function update(Request $request, string $key)
    {
        abort_unless(
            $request->user()?->hasRole('Super Administrador'),
            403,
            'Solo el Super Administrador puede modificar las configuraciones.'
        );

        abort_unless(in_array($key, self::EDITABLE_SETTINGS, true), 404);

        $validated = $request->validate([
            'value' => ['required', 'boolean'],
        ]);

        Setting::set($key, $validated['value'] ? '1' : '0');

        return back()->with('success', 'Configuración actualizada correctamente.');
    }
}
